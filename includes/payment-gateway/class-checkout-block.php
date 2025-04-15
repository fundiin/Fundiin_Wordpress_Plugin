<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Fundiin_Gateway_Blocks extends AbstractPaymentMethodType {
    /**
	 * Payment method name defined by payment methods extending this class.
	 *
	 * @var string
	 */
    protected $name = Fundiin_Gateway::ID;

    /**
	 * An instance of the Fundiin Gateway
	 *
	 * @var Fundiin_Gateway
	 */
    private $gateway;

    /**
	 * Constructor
	 */
    public function __construct() {
        $this->gateway = new Fundiin_Gateway();
    }

    /**
	 * Initializes the payment method type.
	 */
    public function initialize() {
        $this->settings = get_option('woocommerce_' . $this->name . '_settings', []);
    }

    /**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
    public function is_active() {
        return $this->gateway->is_available();
    }

    /**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
    public function get_payment_method_script_handles() {
        wp_register_script(
            $this->name . '-blocks-integration',
            plugin_dir_url(__FILE__) . 'checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );

        if(function_exists('wp_set_script_translations')) {
            wp_set_script_translations($this->name . '-blocks-integration');            
        }

        return [
            $this->name . '-blocks-integration'
        ];
    }

    /**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
    public function get_payment_method_data() {
        $merchantId = fundiin()->settings->merchantId;
        $host = fundiin()->settings->get_fundiin_host();

        return [
            'title' => $this->gateway->title,
            'supports' => $this->get_supported_features(),
            'data' => $this->checkoutConfigFactory(),
            'gatewayUrl' => $host . '/merchants/checkoutjs/' . $merchantId . '.js',
        ];
    }

    /**
	 * Returns an array of supported features.
	 *
	 * @return string[]
	 */
    public function get_supported_features() {
        $features = array_filter($this->gateway->supports, [$this->gateway, 'supports']);

        /**
		 * Filter to control what features are available for each payment gateway.
		 *
		 * @since 4.4.0
		 *
		 * @example See docs/examples/payment-gateways-features-list.md
		 *
		 * @param array $features List of supported features.
		 * @param string $name Gateway name.
		 * @return array Updated list of supported features.
		 */
        return apply_filters('__experimental_woocommerce_blocks_payment_gateway_features_list', $features, $this->get_name());
    }

    /**
     * Generate necessary checkout data
     * 
     * @return array
     */
    private function checkoutConfigFactory() {
        global $wpdb;

        $cart_hash = WC()->cart->get_cart_hash();
        $cart_items = '';
        $query = '';
        $order_id = '';
        $ref_id = '';

        if (!empty($cart_hash)) {
            $query = "
                SELECT o.id
                FROM {$wpdb->prefix}wc_order_operational_data od
                RIGHT JOIN {$wpdb->prefix}wc_orders o ON od.order_id = o.id
                WHERE od.cart_hash = '" . $cart_hash . "'
                ORDER BY o.date_created_gmt desc
                LIMIT 1
            ";
            $query_result = $wpdb->get_results($query);

            if (is_array($query_result) && count($query_result) > 0) {
                $order_id = $query_result[0]->id;
            }

            if (!empty($order_id)) {
                $order = wc_get_order($order_id);
                $ref_id = $order->get_id() . '_' . $order->get_date_created()->format('U');
            }
        }

        foreach (WC()->cart->cart_contents as $cart_item) {
            $cart_items .= '{' .
                'id:' . $cart_item['data']->id . ',' .
                'name: "' . $cart_item['data']->name . '",' .
                'variantId: ' . $cart_item['variation_id'] . ',' .
                'price:' . $cart_item['data']->price . ',' .
                'regularPrice:' . $cart_item['data']->regular_price . ',' .
                'quantity:' . $cart_item['quantity'] . ',' .
                'slug: "' . $cart_item['data']->slug . '",' .
                'sku: "' . $cart_item['data']->sku . '"' .
            '},';
        }

        return [
            'cart_items' => $cart_items,
            'ref_id' => $ref_id,
            'order_id' => $order_id,
        ];
    }
}
