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
        $order = new WC_Order($order_id);

        // Get the selected payment method
        $payment_method = sanitize_text_field($_POST["fundiin_payment_method"]);
        $this->update_payment_method($order, $payment_method);
        $payUrl = $this->fundiin_checkout($order, $payment_method);

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
        $merchant = json_encode([
            "clientId" => $this->clientId,
            "merchantId" => $this->merchantId,
            "secretKey" => $this->secretKey,
        ]);
        Fundiin_Logger::wr_log("Merchant Information: " . $merchant);
        $clientId = $this->clientId;
        $merchantId = $this->merchantId;
        $secretKey = $this->secretKey;
        $storeId = $this->storeId;

        $notifyUrl =
            $this->notifyUrl !== ""
                ? $this->notifyUrl
                : get_home_url() .
                    "/wp-json/fundiin_payment_" .
                    $clientId .
                    "/notify";
        $successfulUrl = $order->get_checkout_order_received_url();
        $unsucessfulUrl = $order->get_view_order_url();
        $amount = strval(round(WC()->cart->total));
        $orderId = $order->get_id();
        $now = round(microtime(true) * 1000);

        $orderInfo =
            __("Thanh toán đơn hàng ", "woocommerce-gateway-fundiin") .
            $this->merchantName;
        $items = WC()->cart->get_cart();
        $gwItems = [];
        foreach ($items as $item => $values) {
            $_product = wc_get_product($values["data"]->get_id());
            $category = [];
            $terms = get_the_terms($_product->get_id(), "product_cat");
            foreach ($terms as $term) {
                array_push($category, $term->name);
            }
            $categoryListString = join(",", $category);
            $gwItem = [
                "productId" => $_product->get_id(),
                "productName" => $_product->get_name(),
                "description" => $_product->get_name(),
                "price" => $_product->get_sale_price(),
                "currency" => "VND",
                "quantity" => $values["quantity"],
                "totalAmount" =>
                    $_product->get_regular_price() * $values["quantity"],
                "category" => $categoryListString,
            ];
            array_push($gwItems, $gwItem);
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
            "firstName" => $order->get_billing_first_name(),
            "lastName" => $order->get_billing_last_name(),
        ];

        // Assuming $order is a WooCommerce order object
        $shipping = [
            "city" => $order->get_shipping_city(),
            "zipCode" => $order->get_shipping_postcode(),
            "district" => $order->get_shipping_state(),
            "ward" => $order->get_shipping_address_2(),
            "street" => $order->get_shipping_address_1(),
            "streetNumber" => "",
            "houseNumber" => "",
            // You may need to get this from custom order fields
            "houseExtension" => null,
            // You may need to get this from custom order fields
            "country" => $order->get_shipping_country(),
        ];

        try {
            $url = $this->get_fundiin_checkout_url();

            $data = [
                "merchantId" => $merchantId,
                "requestType" => "installment",
                "successRedirectUrl" => $successfulUrl,
                "unSuccessRedirectUrl" => $unsucessfulUrl,
                "storeId" => $storeId,
                "notifyUrl" => $notifyUrl,
                "description" => $orderInfo,
                "paymentMethod" => "BNPL",
                "referenceId" => $orderId . "_" . $now,
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
            Fundiin_Logger::wr_log("Request data " . $data_encode);
            Fundiin_Logger::wr_log("Request header " . json_encode($header));

            $response = wp_remote_post($url, [
                "headers" => $header,
                "timeout" => 10,
                "body" => $data_encode,
                "sslverify" => false,
            ]);
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                Fundiin_Logger::wr_log(
                    "Error message at request " . $error_message
                );
                wc_add_notice(
                    __($error_message, "woocommerce-gateway-fundiin"),
                    "error"
                );

                throw new Exception("error_message");
            } else {
                $result = json_decode($response["body"]);

                if ($result->resultStatus != "APPROVED") {
                    wc_add_notice(
                        _(
                            "Không thể tạo đơn hàng và thanh toán qua Fundiin \n"
                        ),
                        "error"
                    );
                    Fundiin_Logger::wr_log(
                        "Fail at Fundiin " . $response["body"]
                    );
                    throw new Exception();
                }
                Fundiin_Logger::wr_log(
                    "Initial payment success at Fundiin " . $response["body"]
                );
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
            Fundiin_Logger::wr_log("ERROR AT CODE " . $ex->getMessage());

            throw new Exception();
        }
    }

    // Perform refund for an order
    function process_refund($order_id, $amount = null, $reason = "")
    {
        // Get the order object
        $order = wc_get_order($order_id);

        $clientId = $this->clientId;
        $merchantId = $this->merchantId;
        $secretKey = $this->secretKey;
        $storeId = $this->storeId;
        $now = round(microtime(true) * 1000);
        $orderId = $clientId . "_REFUND_" . $order_id . "_" . $now;
        $transId = $order->get_transaction_id();
        if ($transId === null or !isset($transId)) {
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
            $amount === null or
            !isset($amount) or
            $order->get_total() != $amount
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

        if ($order->get_status() != "processing") {
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
            $signature = bin2hex(hash_hmac("sha256", $data_encode, $secretKey));

            $response = wp_remote_post($url, [
                "headers" => [
                    "Content-Type" => "application/json",
                    "Signature" => $signature,
                    "Client-Id" => $clientId,
                ],
                "timeout" => 10,
                "body" => $data_encode,
                "sslverify" => false,
            ]);
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                wc_add_notice(
                    __($error_message, "woocommerce-gateway-fundiin"),
                    "error"
                );
                return false;
            } else {
                $result = json_decode($response["body"]);
                if ($result->resultStatus != "APPROVED") {
                    wc_add_notice(
                        $result->resultMsg,
                        "woocommerce-gateway-fundiin"
                    );
                    $error = new WP_Error("cannot_refund", $result->resultMsg);

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
            wc_add_notice($ex->getMessage(), "error");
            return false;
        }
    }
}
