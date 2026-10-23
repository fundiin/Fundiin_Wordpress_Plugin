<?php

if (!defined("ABSPATH")) {
    exit(); // Exit if accessed directly
}

class Fundiin extends WC_Gateway_Fundiin
{
    public function __construct()
    {
        parent::__construct();
    }

    public function get_fundiin_checkout_url()
    {
        return fundiin()->settings->get_fundiin_aio_url();
    }

    public function get_fundiin_refund_url()
    {
        return fundiin()->settings->get_fundiin_refund_url();
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order || $order->get_payment_method() !== $this->id || $order->is_paid() ||
            $order->get_currency() !== 'VND' || $this->secretKey === '' || $this->merchantId === '' || $this->clientId === '') {
            wc_add_notice(__('Không thể thanh toán đơn hàng qua Fundiin.', 'woocommerce-gateway-fundiin'), 'error');
            return array('result' => 'failure');
        }

        // Get the selected payment method
        $payment_method = "BNPL";
        $this->update_payment_method($order, $payment_method);
        try {
            $payUrl = $this->fundiin_checkout($order, $payment_method);
        } catch (Exception $ex) {
            wc_add_notice(__('Không thể tạo thanh toán qua Fundiin.', 'woocommerce-gateway-fundiin'), 'error');
            return array('result' => 'failure');
        }

        return [
            "result" => "success",
            "redirect" => $payUrl,
        ];
    }

    private function fundiin_checkout($order, $payment_method)
    {
        Fundiin_Logger::wr_log(
            "Start checked out for order " . $order->get_id()
        );
        $clientId = $this->clientId;
        $merchantId = $this->merchantId;
        $secretKey = $this->secretKey;
        $storeId = $this->storeId;
//        $orderExpiredTime = $this->orderExpiredTime;

        $notifyUrl =
            $this->notifyUrl !== ""
                ? $this->notifyUrl
                : get_home_url() .
                    "/wp-json/fundiin_payment_" .
                    $clientId .
                    "/notify";
        $successfulUrl = $order->get_checkout_order_received_url();
        $unsucessfulUrl = $order->get_view_order_url();
        $amount = wc_format_decimal($order->get_total());
        $orderId = $order->get_id();
        $now = round(microtime(true) * 1000);
        $referenceId = $order->get_id() . "_" . $now;
        // Persist before the HTTP call so even an immediate webhook can be checked.
        $order->update_meta_data('_fundiin_reference_id', $referenceId);
        $order->save();

        $orderInfo =
            __("Thanh toán đơn hàng ", "woocommerce-gateway-fundiin") .
            $this->merchantName;
        $gwItems = [];

        $order = wc_get_order($orderId);

        if ($order) {
            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();
                if ($product) {
                    // Lấy danh mục sản phẩm
                    $categories = [];
                    $terms = get_the_terms($product->get_id(), 'product_cat');
                    if ($terms && !is_wp_error($terms)) {
                        foreach ($terms as $term) {
                            $categories[] = $term->name;
                        }
                    }
                    $categoryListString = join(',', $categories);

                    $newName = strip_tags($product->get_name(), "");

                    $gwItem = [
                        "productId" => $product->get_id(),
                        "productName" => $newName,
                        "description" => $newName,
                        "price" => $product->get_sale_price(),
                        "currency" => "VND",
                        "quantity" => intval($item->get_quantity()),
                        "totalAmount" => floatval($item->get_total()),
                        "category" => $categoryListString,
                    ];
                    $gwItems[] = $gwItem;
                }
            }


                $shipping = [
                    "city" => $order->get_shipping_city() ?: $order->get_billing_city(),
                    "zipCode" => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
                    "district" => $order->get_shipping_state() ?: $order->get_billing_state(),
                    "ward" => $order->get_shipping_address_2() ?: $order->get_billing_address_2(),
                    "street" => $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
                    "streetNumber" => "",
                    "houseNumber" => "",
                    "houseExtension" => null,
                    "country" => $order->get_shipping_country() ?: $order->get_billing_country(),
                ];
            } else {
            $shipping = [
                "city" => 'N/A',
                "zipCode" => 'N/A',
                "district" => 'N/A',
                "ward" => 'N/A',
                "street" => 'N/A',
                "streetNumber" => "",
                "houseNumber" => "",
                "houseExtension" => null,
                "country" => 'N/A',
            ];
        }


        $phone_number = $order->get_billing_phone();
        if (strpos($phone_number, "+84") === 0) {
            $phone_number = "0" . substr($phone_number, 3);
        }
        $phone_number = str_replace(" ", "", $phone_number);
        // get number only
        $phone_number = preg_replace("/[^0-9]/", "", $phone_number);

        $customer = [
            "phoneNumber" => $phone_number,
            "email" => $order->get_billing_email(),
            "firstName" => strip_tags($order->get_billing_first_name(), ""),
            "lastName" => strip_tags($order->get_billing_last_name(), ""),
        ];




        try {
            $url = $this->get_fundiin_checkout_url();

            $data = [
                "merchantId" => $merchantId,
//                "orderExpiredTime" => $orderExpiredTime,
                "platformId" => "WOOCOMMERCE",
                "requestType" => "installment",
                "successRedirectUrl" => $successfulUrl,
                "unSuccessRedirectUrl" => $unsucessfulUrl,
                "storeId" => $storeId,
                "notifyUrl" => $notifyUrl,
                "description" => $orderInfo,
                "paymentMethod" => "BNPL",
                "referenceId" => $referenceId,
                "extraData" => $orderInfo,
                "amount" => [
                    "value" => $amount,
                    "currency" => "VND",
                ],
                "shipping" => $shipping,
                "customer" => $customer,
                "items" => $gwItems,
            ];
            $signature = bin2hex(
                hash_hmac("sha256", json_encode($data), $secretKey, true)
            );
            $header = [
                "Content-Type" => "application/json",
                "Signature" => $signature,
                "Client-Id" => $clientId,
            ];
            $data_encode = json_encode($data);
            Fundiin_Logger::wr_log("Order ID " . $order->get_id());

            $response = wp_remote_post($url, [
                "headers" => $header,
                "timeout" => 10,
                "body" => $data_encode,
                "sslverify" => true,
                "redirection" => 0,
            ]);
            if (is_wp_error($response)) {
                $error_message = __('Không thể kết nối Fundiin.', 'woocommerce-gateway-fundiin');
                Fundiin_Logger::wr_log(
                    "Error message at request " . $error_message
                );
                wc_add_notice(
                    __($error_message, "woocommerce-gateway-fundiin"),
                    "error"
                );

                throw new Exception("error_message");
            } else {
                $result = json_decode(wp_remote_retrieve_body($response));

                if (wp_remote_retrieve_response_code($response) < 200 || wp_remote_retrieve_response_code($response) >= 300 ||
                    !is_object($result) || !isset($result->resultStatus) || $result->resultStatus !== "APPROVED") {
                    wc_add_notice(
                        __("Không thể tạo đơn hàng và thanh toán qua Fundiin.", "woocommerce-gateway-fundiin"),
                        "error"
                    );
                    Fundiin_Logger::wr_log(
                        "Payment initiation declined for order " . $order->get_id()
                    );
                    throw new Exception();
                }
                Fundiin_Logger::wr_log(
                    "Payment initiated for order " . $order->get_id()
                );
                if (!isset($result->paymentUrl) || !$this->is_valid_payment_url($result->paymentUrl)) {
                    throw new Exception('Invalid payment redirect URL.');
                }
                return $result->paymentUrl;
            }
            wc_add_notice(
                __("Yêu cầu không hợp lệ", "woocommerce-gateway-fundiin"),
                "error"
            );
            throw new Exception(
                __("Yêu cầu không hợp lệ", "woocommerce-gateway-fundiin")
            );
        } catch (Exception $ex) {
            Fundiin_Logger::wr_log("Payment initiation failed for order " . $order->get_id());

            throw new Exception();
        }
    }

    private function is_valid_payment_url($url)
    {
        if (!is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $parts = wp_parse_url($url);
        return is_array($parts) && isset($parts['scheme'], $parts['host']) && $parts['scheme'] === 'https' &&
            (strtolower($parts['host']) === 'fundiin.vn' || substr(strtolower($parts['host']), -11) === '.fundiin.vn') &&
            !isset($parts['user']) && !isset($parts['pass']) && (!isset($parts['port']) || $parts['port'] === 443);
    }

    // Perform refund for an order
    function process_refund($order_id, $amount = null, $reason = "")
    {
        // Get the order object
        $order = wc_get_order($order_id);
        if (!$order || $order->get_payment_method() !== $this->id || $order->get_currency() !== 'VND' ||
            !current_user_can('manage_woocommerce') || $this->secretKey === '' || $this->merchantId === '' || $this->clientId === '') {
            return new WP_Error('fundiin_invalid_refund', 'Invalid Fundiin refund.');
        }

        $clientId = $this->clientId;
        $merchantId = $this->merchantId;
        $secretKey = $this->secretKey;
        $storeId = $this->storeId;
        $now = round(microtime(true) * 1000);
        $orderId = $clientId . "_REFUND_" . $order_id . "_" . $now;
        $transId = $order->get_transaction_id();
        if (!is_string($transId) || $transId === "") {
            $error = new WP_Error(
                "transaction_not_found",
                __(
                    "Đơn hàng chưa được thanh toán nên không thể hoàn tiền.",
                    "woocommerce-gateway-fundiin"
                )
            );
            return $error;
        }
        if (
            !is_scalar($amount) || is_bool($amount) || !is_numeric($amount) ||
            !is_finite((float) $amount) || (float) $amount <= 0 ||
            wc_format_decimal($order->get_total(), 2) !== wc_format_decimal($amount, 2)
        ) {
            $error = new WP_Error(
                "cannot_refund",
                __(
                    "Hoàn tiền thất bại. Bạn phải hoàn tiền toàn bộ đơn hàng.",
                    "woocommerce-gateway-fundiin"
                )
            );

            return $error;
        }

        if (!$order->is_paid()) {
            $error = new WP_Error(
                "cannot_refund",
                __(
                    "Đơn hàng chưa được thanh toán nên không thể hoàn tiền.",
                    "woocommerce-gateway-fundiin"
                )
            );
            return $error;
        }
        try {
            $url = $this->get_fundiin_refund_url();

            $data = [
                "merchantId" => $merchantId,
                "referenceId" => $orderId,
                "paymentTransId" => $transId,
                "lang" => "vi",
                "description" => $reason,
                "amount" => [
                    "value" => $amount,
                    "currency" => "VND",
                ],
            ];
            $data_encode = json_encode($data);
            $signature = hash_hmac("sha256", $data_encode, $secretKey);

            $response = wp_remote_post($url, [
                "headers" => [
                    "Content-Type" => "application/json",
                    "Signature" => $signature,
                    "Client-Id" => $clientId,
                ],
                "timeout" => 10,
                "body" => $data_encode,
                "sslverify" => true,
                "redirection" => 0,
            ]);
            if (is_wp_error($response)) {
                $error_message = __('Không thể kết nối Fundiin.', 'woocommerce-gateway-fundiin');
                wc_add_notice(
                    __($error_message, "woocommerce-gateway-fundiin"),
                    "error"
                );
                return false;
            } else {
                $result = json_decode(wp_remote_retrieve_body($response));
                if (wp_remote_retrieve_response_code($response) < 200 || wp_remote_retrieve_response_code($response) >= 300 ||
                    !is_object($result) || !isset($result->resultStatus, $result->referenceId, $result->refundTransId) ||
                    $result->resultStatus !== "APPROVED" || !is_string($result->referenceId) ||
                    !hash_equals($orderId, $result->referenceId) || !is_string($result->refundTransId)) {
                    $error = new WP_Error("cannot_refund", __('Hoàn tiền qua Fundiin thất bại.', 'woocommerce-gateway-fundiin'));

                    return $error;
                }
                $order->add_order_note(
                    sprintf(
                        __(
                            "Đơn hàng đã hoàn tiền số tiền %s qua Fundiin.",
                            "woocommerce-gateway-fundiin"
                        ),
                        wc_price($amount)
                    )
                );
                $order->update_meta_data(
                    "fundiin_refund_orderId",
                    $result->referenceId
                );
                $order->update_meta_data(
                    "fundiin_refund_transId",
                    $result->refundTransId
                );

                // Save changes to the order
                $order->save();
                return true;
            }
        } catch (Exception $ex) {
            wc_add_notice(__("Hoàn tiền qua Fundiin thất bại.", "woocommerce-gateway-fundiin"), "error");
            return false;
        }
    }
}
