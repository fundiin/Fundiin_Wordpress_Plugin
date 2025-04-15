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
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->description,
            'supports' => $this->get_supported_features(),
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
}
