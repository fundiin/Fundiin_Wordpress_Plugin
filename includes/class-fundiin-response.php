<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Handle authenticated server-to-server payment notifications. */
class Fundiin_Response
{
    /** @var array<int, bool> In-process lock map to serialize concurrent notifications per order. */
    private static $active_locks = array();

    public function __construct()
    {
        add_action('wp_ajax_fundiin_payment_response_return', array($this, 'fundiin_handle_response_return'));
        add_action('rest_api_init', array($this, 'register_notify_api'));
    }

    public function register_notify_api()
    {
        $client_id = isset(fundiin()->fundiin->clientId) ? (string) fundiin()->fundiin->clientId : '';
        if (!preg_match('/\A[A-Za-z0-9_-]+\z/', $client_id)) {
            return;
        }
        register_rest_route('fundiin_payment_' . $client_id, 'notify', array(
            'methods' => 'POST',
            'callback' => array($this, 'check_fundiin_notify'),
            'permission_callback' => array($this, 'verify_notification'),
        ));
    }

    /** Browser query parameters are never proof of payment. */
    public function fundiin_handle_response_return()
    {
        wp_safe_redirect(wc_get_cart_url());
        exit;
    }

    public function verify_notification(WP_REST_Request $request)
    {
        $body = $request->get_body();
        $data = json_decode($body, true);
        $gateway = fundiin()->fundiin;
        $signature = $request->get_header('signature');

        if (!is_array($data) || !is_string($gateway->secretKey) || $gateway->secretKey === '' ||
            !is_string($gateway->merchantId) || $gateway->merchantId === '' ||
            !isset($data['merchantId']) || !is_string($data['merchantId']) ||
            !hash_equals($gateway->merchantId, $data['merchantId']) ||
            !is_string($signature) || !preg_match('/\A[0-9a-fA-F]{64}\z/', $signature)) {
            return new WP_Error('fundiin_invalid_signature', 'Invalid notification signature.', array('status' => 401));
        }

        // Support both the raw-body protocol and older JSON-normalized signatures.
        $signature = strtolower($signature);
        if (!hash_equals(hash_hmac('sha256', $body, $gateway->secretKey), $signature) &&
            !hash_equals(hash_hmac('sha256', json_encode($data), $gateway->secretKey), $signature)) {
            return new WP_Error('fundiin_invalid_signature', 'Invalid notification signature.', array('status' => 401));
        }
        return true;
    }

    public function check_fundiin_notify(WP_REST_Request $request)
    {
        // Enforce authentication even when called outside the REST dispatcher.
        $verified = $this->verify_notification($request);
        if (is_wp_error($verified)) {
            return $verified;
        }
        $data = json_decode($request->get_body(), true);
        if (!$this->valid_notification($data)) {
            return new WP_Error('fundiin_invalid_notification', 'Invalid notification data.', array('status' => 400));
        }
        $order_id = (int) explode('_', $data['referenceId'])[0];
        $order = wc_get_order($order_id);
        if (!$order || $order->get_payment_method() !== 'fundiin') {
            return new WP_Error('fundiin_invalid_order', 'Invalid payment order.', array('status' => 400));
        }
        $reference = $order->get_meta('_fundiin_reference_id');
        if (!is_string($reference) || $reference === '' || !hash_equals($reference, $data['referenceId']) ||
            $data['amount']['currency'] !== $order->get_currency() ||
            wc_format_decimal($data['amount']['value'], 2) !== wc_format_decimal($order->get_total(), 2)) {
            return new WP_Error('fundiin_payment_mismatch', 'Payment does not match the order.', array('status' => 400));
        }
        if ($data['paymentStatus'] === 'PENDING' || !$order->has_status(array('pending', 'on-hold'))) {
            return new WP_REST_Response(null, 204);
        }
        if (!$this->acquire_order_lock($order_id)) {
            return new WP_Error('fundiin_notification_busy', 'Order notification is already being processed.', array('status' => 409));
        }
        try {
            // Re-check status after acquiring the lock to prevent TOCTOU double-processing.
            $order = wc_get_order($order_id);
            if (!$order || !$order->has_status(array('pending', 'on-hold'))) {
                return new WP_REST_Response(null, 204);
            }
            if ($data['paymentStatus'] === 'SUCCESS') {
                $trans_id = sanitize_text_field($data['paymentTransId']);
                $order->update_meta_data('fundiin_transId', $trans_id);
                if (isset($data['orderId']) && is_scalar($data['orderId'])) {
                    $order->update_meta_data('fundiin_orderId', sanitize_text_field((string) $data['orderId']));
                }
                // WooCommerce owns stock reduction and the processing/completed transition.
                $order->payment_complete($trans_id);
                $order->add_order_note('Thanh toán thành công qua Fundiin với mã số giao dịch ' . $trans_id . '.');
            } else {
                $order->update_status('cancelled', 'Đơn hàng bị huỷ qua Fundiin.');
            }
            $order->save();
            return new WP_REST_Response(null, 204);
        } catch (Exception $ex) {
            Fundiin_Logger::wr_log('Payment notification processing failed for order ' . $order_id);
            return new WP_Error('fundiin_notification_failed', 'Unable to process notification.', array('status' => 500));
        } finally {
            $this->release_order_lock($order_id);
        }
    }

    private function acquire_order_lock($order_id)
    {
        $order_id = (int) $order_id;
        if (isset(self::$active_locks[$order_id])) {
            return false;
        }
        if (function_exists('add_option')) {
            $lock_key = '_fundiin_notify_lock_' . $order_id;
            $now = time();
            if (!add_option($lock_key, (string) $now, '', 'no')) {
                $existing = (int) get_option($lock_key, 0);
                if ($existing > 0 && ($now - $existing) < 30) {
                    return false;
                }
                update_option($lock_key, (string) $now, 'no');
            }
        }
        self::$active_locks[$order_id] = true;
        return true;
    }

    private function release_order_lock($order_id)
    {
        $order_id = (int) $order_id;
        unset(self::$active_locks[$order_id]);
        if (function_exists('delete_option')) {
            delete_option('_fundiin_notify_lock_' . $order_id);
        }
    }

    private function valid_notification($data)
    {
        if (!isset($data['referenceId'], $data['notificationType'], $data['paymentStatus'], $data['amount']) ||
            !is_string($data['referenceId']) || !preg_match('/\A[1-9][0-9]*_[0-9]{13}\z/', $data['referenceId']) ||
            $data['notificationType'] !== 'PAYMENT_STATUS' ||
            !in_array($data['paymentStatus'], array('PENDING', 'SUCCESS', 'FAIL'), true) ||
            !is_array($data['amount']) || !isset($data['amount']['value'], $data['amount']['currency']) ||
            !is_scalar($data['amount']['value']) || is_bool($data['amount']['value']) ||
            !is_numeric($data['amount']['value']) || !is_finite((float) $data['amount']['value']) ||
            (float) $data['amount']['value'] < 0 || !is_string($data['amount']['currency'])) {
            return false;
        }
        return $data['paymentStatus'] !== 'SUCCESS' ||
            (isset($data['paymentTransId']) && is_string($data['paymentTransId']) && trim($data['paymentTransId']) !== '');
    }
}
