<?php
if (!defined('ABSPATH')) {
    exit(); // Exit if accessed directly
}

class Fundiin_Gateway extends WC_Payment_Gateway
{
    /**
     * Unigue ID for this gateway
     */
    const ID = 'fundiin_gateway';

    /**
     * Running environment
     */
    public $environment;

    /**
     * Secret Key
     */
    public $secret_key;

    /**
     * Client ID
     */
    public $client_id;

    /**
     * Merchant ID
     */
    public $merchant_id;

    /**
     * Merchant Name
     */
    public $merchant_name;

    /**
     * Store ID
     */
    public $store_id;

    /**
     * Notification URL
     */
    public $notify_url;

    /**
     * Constructor for the Fundiin Gateway
     */
    public function __construct() {
        $this->id = self::ID;
        $this->icon = apply_filters('woocommerce_custom_gateway_icon', 'https://storage.googleapis.com/fundiin-asset/merchant/logo_long_image_reverse.svg');
        $this->has_fields = false;
        $this->method_title = __('Fundiin Payment Gateway', fundiin()->domain);
        $this->method_description = __('Mua trước trả sau cùng Fundiin', fundiin()->domain);
        $this->supports = ['products', 'refunds'];

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();
        
        // Define user set variables.
		$this->title = __('Fundiin - Mua trả sau 0% lãi', fundiin()->domain);
		$this->description = __('Fundiin - Mua trả sau 0% lãi', fundiin()->domain);
        $this->environment = $this->get_option('environment');
        $this->secret_key = $this->get_option('secret_key');
        $this->client_id = $this->get_option('client_id');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->merchant_name = $this->get_option('merchant_name');
        $this->store_id = $this->get_option('store_id');
        $this->notify_url = $this->get_option('notify_url');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', fundiin()->domain),
                'label' => 'Enable Fundiin Gateway',
                'type' => 'checkbox',
                'default' => 'yes',
                'description' => __('Enable or Disable Fundiin Payment Gateway', fundiin()->domain),
                'desc_tip' => true,
            ],
            'environment' => [
                'title' => __('Environment', fundiin()->domain),
                'label' => 'Choose Environment of plugin',
                'type' => 'select',
                'description' => __(
                    'Depending on the environment, API configuration settings may vary. <br> NOTE: THE SANDBOX ENVIRONMENT IS RESERVED FOR TESTING, AND ALL TRANSACTIONS IN THE SANDBOX HAVE NO VALUE.',
                    fundiin()->domain
                ),
                'default' => 'sandbox',
                'desc_tip' => true,
                'options' => [
                    'production' => __('Production Environment (Live)', fundiin()->domain),
                    'sandbox' => __('Testing Environment (Sandbox)', fundiin()->domain),
                ],
            ],
            'secret_key' => [
                'title' => __('Secret Key', fundiin()->domain),
                'type' => 'password',
                'default' => '',
                'description' => __('Secret key provided by Fundiin', fundiin()->domain),
                'desc_tip' => true,
            ],
            'client_id' => [
                'title' => __('Client Id', fundiin()->domain),
                'type' => 'text',
                'default' => '',
                'description' => __('Client ID provided by Fundiin', fundiin()->domain),
                'desc_tip' => true,
            ],
            'merchant_id' => [
                'title' => __('Merchant Id', fundiin()->domain),
                'type' => 'text',
                'default' => '',
                'description' => __('Merchant ID provided by Fundiin', fundiin()->domain),
                'desc_tip' => true,
            ],
            'merchant_name' => [
                'title' => __('Merchant Name', fundiin()->domain),
                'type' => 'text',
                'default' => '',
                'description' => __(
                    'The business/partner name (your name). It will be attached to the order information (orderInfo) when sent to Fundiin.',
                    fundiin()->domain
                ),
                'desc_tip' => true,
            ],    
            'store_id' => [
                'title' => __('Store ID', fundiin()->domain),
                'type' => 'text',
                'default' => '',
                'description' => __(
                    'Site ID provided by Fundiin. Please leave it empty if you are not using it.',
                    fundiin()->domain
                ),
                'desc_tip' => true,
            ],
            'notify_url' => [
                'title' => __('Notify URL', fundiin()->domain),
                'type' => 'text',
                'default' => '',
                'description' => __(
                    'URL used for callback processing. Please leave it empty if you are not using it.',
                    fundiin()->domain
                ),
                'desc_tip' => true,
            ],
        ];
    }

    public function get_fundiin_checkout_url() {
        return fundiin()->settings->get_fundiin_aio_url();
    }

    public function get_fundiin_refund_url() {
        return fundiin()->settings->get_fundiin_refund_url();
    }

    public function process_payment($order_id) {
        $order = new WC_Order($order_id);

        // Get the selected payment method
        $payment_method = sanitize_text_field($_POST['fundiin_payment_method']);

        switch ($payment_method) {
            default:
                break;
        }

        $order->save();
        $payUrl = $this->fundiin_checkout($order, $payment_method);

        return [
            'result' => 'success',
            'redirect' => $payUrl,
        ];
    }

    public function process_refund($order_id, $amount = null, $reason = '') {
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

    private function fundiin_checkout($order, $payment_method) {
        Fundiin_Logger::wr_log(
            'Start checked out for order ' . $order->get_id()
        );

        $merchant = json_encode([
            'clientId' => $this->client_id,
            'merchantId' => $this->merchant_id,
            'secretKey' => $this->secret_key,
        ]);
        
        Fundiin_Logger::wr_log('Merchant Information: ' . $merchant);

        $successful_url = $order->get_checkout_order_received_url();
        $unsucessful_url = $order->get_view_order_url();
        $notify_url = $this->notify_url !== ''
            ? $this->notify_url
            : get_home_url() . '/wp-json/fundiin_payment_' . $this->client_id . '/notify';
        $amount = strval(round(WC()->cart->total));
        $order_id = $order->get_id();
        $now = round(microtime(true) * 1000);
        $order_info = __('Thanh toán đơn hàng ', fundiin()->domain) . $this->merchant_name;
        $items = WC()->cart->get_cart();
        $gateway_items = [];

        $order = wc_get_order($order_id);

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
                    $category_list_string = join(',', $categories);

                    $new_name = strip_tags($product->get_name(), '');

                    $gateway_item = [
                        'productId' => $product->get_id(),
                        'productName' => $new_name,
                        'description' => $new_name,
                        'price' => $product->get_sale_price(),
                        'currency' => 'VND',
                        'quantity' => intval($item->get_quantity()),
                        'totalAmount' => floatval($item->get_total()),
                        'category' => $category_list_string,
                    ];
                    $gateway_items[] = $gateway_item;
                }
            }

            $shipping = [
                'city' => $order->get_shipping_city() ?: $order->get_billing_city(),
                'zipCode' => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
                'district' => $order->get_shipping_state() ?: $order->get_billing_state(),
                'ward' => $order->get_shipping_address_2() ?: $order->get_billing_address_2(),
                'street' => $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
                'streetNumber' => '',
                'houseNumber' => '',
                'houseExtension' => null,
                'country' => $order->get_shipping_country() ?: $order->get_billing_country(),
            ];
        } else {
            $shipping = [
                'city' => 'N/A',
                'zipCode' => 'N/A',
                'district' => 'N/A',
                'ward' => 'N/A',
                'street' => 'N/A',
                'streetNumber' => '',
                'houseNumber' => '',
                'houseExtension' => null,
                'country' => 'N/A',
            ];
        }

        $phone_number = $order->get_billing_phone();
        if (strpos($phone_number, '+84') === 0) {
            $phone_number = '0' . substr($phone_number, 3);
        }
        $phone_number = str_replace(' ', '', $phone_number);
        // get number only
        $phone_number = preg_replace("/[^0-9]/", '', $phone_number);

        $customer = [
            'phoneNumber' => $phone_number,
            'email' => $order->get_billing_email(),
            'firstName' => strip_tags($order->get_billing_first_name(), ''),
            'lastName' => strip_tags($order->get_billing_last_name(), ''),
        ];

        try {
            $url = $this->get_fundiin_checkout_url();

            $data = [
                'merchantId' => $this->merchant_id,
                'platformId' => 'WOOCOMMERCE',
                'requestType' => 'installment',
                'successRedirectUrl' => $successful_url,
                'unSuccessRedirectUrl' => $unsucessful_url,
                'storeId' => $this->store_id,
                'notifyUrl' => $this->notify_url,
                'description' => $order_info,
                'paymentMethod' => 'BNPL',
                'referenceId' => $order_id . '_' . $now,
                'extraData' => $order_info,
                'amount' => [
                    'value' => $amount,
                    'currency' => 'VND',
                ],
                'shipping' => $shipping,
                'customer' => $customer,
                'items' => $gateway_items,
            ];
            $signature = bin2hex(
                hash_hmac("sha256", json_encode($data), $this->secret_key, true)
            );
            $header = [
                'Content-Type' => 'application/json',
                'Signature' => $signature,
                'Client-Id' => $this->client_id,
            ];
            $data_encode = json_encode($data);
            Fundiin_Logger::wr_log('Order ID ' . $order->get_id());
            Fundiin_Logger::wr_log('Request data ' . $data_encode);
            Fundiin_Logger::wr_log('Request header ' . json_encode($header));

            $response = wp_remote_post($url, [
                'headers' => $header,
                'timeout' => 10,
                'body' => $data_encode,
                'sslverify' => false,
            ]);
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                Fundiin_Logger::wr_log(
                    'Error message at request ' . $error_message
                );
                wc_add_notice(
                    __($error_message, fundiin()->domain),
                    'error'
                );

                throw new Exception('error_message');
            } else {
                $result = json_decode($response['body']);

                if ($result->resultStatus != 'APPROVED') {
                    wc_add_notice(
                        _(
                            'Không thể tạo đơn hàng và thanh toán qua Fundiin \n'
                        ),
                        'error'
                    );
                    Fundiin_Logger::wr_log('Fail at Fundiin ' . $response['body']);
                    throw new Exception();
                }
                Fundiin_Logger::wr_log('Initial payment success at Fundiin ' . $response['body']);
                return $result->paymentUrl;
            }
            wc_add_notice(__('Yêu cầu không hợp lệ', fundiin()->domain), 'error');
            throw new Exception(__('Yêu cầu không hợp lệ', fundiin()->domain));
        } catch (Exception $ex) {
            Fundiin_Logger::wr_log('ERROR AT CODE ' . $ex->getMessage());
            throw new Exception();
        }
    }
}