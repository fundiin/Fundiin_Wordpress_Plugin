<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handle response from Fundiin (return & notify)
 */
class Fundiin_Response
{
    public function __construct()
    {
        $this->response_action();
        // $this->register_notify_api();
    }

    public function response_action()
    {
        add_action('wp_ajax_fundiin_payment_response_return', array($this, 'fundiin_handle_response_return'));
        add_action('rest_api_init', array($this, 'register_notify_api'));
    }

    public function register_notify_api()
    {
        $fundiin_with_aio = fundiin()->fundiin_with_aio;
        $clientId = $fundiin_with_aio->clientId;

        register_rest_route(
            'fundiin_payment_' . $clientId,
            'notify',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'check_fundiin_notify')
            )
        );

    }

    /**
     * Receive return param from fundiin
     * Please do not edit if not necessary (This function will impact to your Woocommerce order)
     */
    public function fundiin_handle_response_return()
    {
        if (!$this->check_enough_fields_confirm_return()) {
            wc_add_notice(__('Thiếu thông tin xác nhận thanh toán, vui lòng kiểm tra lại', 'woocommerce-gateway-fundiin'), 'error');
        } else {
            $orderId = $_GET['orderId'];
            $localMessage = $_GET['message'];
            $errorCode = $_GET['resultCode'];

            $request = $_GET;

            if (!$this->check_valid_info_confirm_signature($request)) {
                wc_add_notice(__('Sai thông tin xác nhận thanh toán, vui lòng kiểm tra lại', 'woocommerce-gateway-fundiin'), 'error');
            } else {
                WC()->cart->empty_cart();
                $order = $this->get_order($orderId);
                $redirectUrl = wc_get_cart_url();
                if ($errorCode == 0 || $errorCode == 7002) {
                    $order->update_status('on-hold');
                    $order->add_order_note(
                        sprintf(__('Thanh toán đơn hàng: %s thành công bằng %s.', 'your-plugin'), $request['orderId'], $order->get_meta('payment method'))
                    );
                    $order->set_transaction_id($request['paymentTransId']);
                    $order->update_meta_data('fundiin_orderId', $request['orderId']);
                    $order->update_meta_data('fundiin_transId', $request['paymentTransId']);
                    $order->save();
                    $redirectUrl = $order->get_checkout_order_received_url();
                } else {
                    $order->update_status('cancelled');
                    $order->add_order_note(__($localMessage, 'woocommerce'));
                    $key = $this->gen_random_str();
                    $redirectUrl .= '?' . $key . '=' . $localMessage;
                    add_option('localMessage', $key);
                }
                wp_redirect($redirectUrl);
            }
        }
        exit();
    }

    /**
     * Receive IPN Notify from Fundiin
     * You can custom this function base on your business
     */
    public function check_fundiin_notify(WP_REST_Request $request)
    {



        if (!$this->check_enough_fields_confirm_notify(json_decode($request->get_body(), true))) {
            return new WP_REST_RESPONSE(array("message" => "Sai thông tin yêu cầu"), 200);
        }
        if (!$this->check_valid_info_confirm_signature($request)) {
            return new WP_REST_RESPONSE(array("message" => "Sai thông tin chữ ký"), 200);
        }
        // exit;
        $returnBody = json_decode($request->get_body(), 1);
        try {
            $order = wc_get_order($returnBody['referenceId']);

            if ($order->get_status() == 'pending') {
                if ($returnBody['notificationType'] == "PAYMENT_STATUS" || $returnBody['paymentStatus'] == "SUCCESS") {

                    $order->set_transaction_id($request['paymentTransId']);
                    $order->update_meta_data('fundiin_orderId', $request['orderId']);
                    $order->update_meta_data('fundiin_transId', $request['paymentTransId']);
                    $order->update_status('processing', "Thanh toán thành công qua Fundiin với mã số giao dịch " . $returnBody['paymentTransId'] . ".");
                    $order->save();

                    return new WP_REST_Response(array(), 204);
                } else {
                    $order->update_status('cancelled');
                    $order->add_order_note(__($returnBody['resultMsg'], 'woocommerce'));
                    $order->save();

                    return new WP_REST_Response(array(), 400);
                }

            }

            // return new WP_REST_Response(array(), 204);
        } catch (Exception $ex) {
            return new WP_REST_Response(array('message' => $ex->getMessage()), 400);
        }
    }

    /**
     * Generate random string for "localMessage"'s key
     */
    private function gen_random_str($length = 128)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $chars_len = strlen($chars);
        $random_str = '';
        for ($i = 0; $i < $length; $i++) {
            $random_str .= $chars[rand(0, $chars_len - 1)];
        }
        return $random_str;
    }

    /**
     * Check valid signature from request
     */
    private function check_valid_info_confirm_signature($request)
    {
        $fundiin_with_aio = fundiin()->fundiin_with_aio;
        $secretKey = $fundiin_with_aio->secretKey;
        $merchantId = $fundiin_with_aio->merchantId;
        $body = json_encode(json_decode($request->get_body(), true));
        $reSignature = hash_hmac('sha256', $body, $secretKey);
        if (json_decode($body, true)['merchantId'] != $merchantId) {
            return false;
        }
        if ($reSignature !== $request->get_header('signature')) {
            return false;
        }
        return true;
    }

    /**
     * Check enough necessary fields in return
    

    /**
     * Check enough necessary fields in notify
     */
    private function check_enough_fields_confirm_notify($request)
    {
        $requiredFields = array(
            'merchantId',
            'referenceId',
            'amount',
            'paymentTransId',
            'paymentMethod',
            'paymentChannel',
            'paymentStatus',
            'notificationType',
            'resultStatus',
            'resultMsg',
            'customerId',
            'paymentTime'
        );

        $missingFields = array_diff($requiredFields, array_keys($request));
        if (
            !empty($missingFields)
        ) {
            return false;
        }
        return true;
    }

    /**
     * Get order by Order ID
     */
    public function get_order($orderId)
    {
        $order_id = explode("-", $orderId)[1];
        return new WC_Order($order_id);
    }
}