<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Fundiin_Gateway_Blocks extends AbstractPaymentMethodType {
    private $gateway;
    protected $name = 'fundiin_gateway';

    public function initialize() {
        $this->settings = get_option('woocommerce_' . $this->name . '_settings', []);
        $this->gateway = new Fundiin_Gateway();
    }

    public function is_active() {
        return $this->gateway->is_available();
    }

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

    public function get_payment_method_data() {
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->description,
        ];
    }
}