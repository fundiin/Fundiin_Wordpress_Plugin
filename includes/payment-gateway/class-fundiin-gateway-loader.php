<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load Fundiin Payment Gateway for Woocommerce
 */
class Fundiin_Gateway_Loader
{
    /**
     * Constructor
     */
    public function __construct() {
        // Register Fundiin Gateway
        add_filter('woocommerce_payment_gateways', [$this, 'add_fundiin_gateway']);

        // Hook the custom function to the 'before_woocommerce_init' action
        add_action('before_woocommerce_init', [$this, 'declare_cart_checkout_blocks_compatibility']);

        // Hook the custom function to the 'woocommerce_blocks_loaded' action
        add_action('woocommerce_blocks_loaded', [$this, 'register_order_approval_payment_method_type']);
    }

    /**
     * Register the Fundiin Gateway.
     *
     * @param array $methods Payment methods.
     *
     * @return array Payment methods
     */
    public function add_fundiin_gateway($methods) {
        $methods[] = fundiin()->gateway_name;
        return $methods;
    }

    /**
     * Custom function to declare compatibility with cart_checkout_blocks feature 
     */
    public function declare_cart_checkout_blocks_compatibility() {
        // Check if the required class exists
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            // Declare compatibility for 'cart_checkout_blocks'
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
        }
    }

    /**
     * Custom function to register a payment method type
     */
    public function register_order_approval_payment_method_type() {
        // Check if the required class exists
        if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            return;
        }
    
        // Include the custom Blocks Checkout class
        require_once plugin_dir_path(__FILE__) . 'class-checkout-block.php';
    
        // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function(Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                // Register an instance of Fundiin_Gateway_Blocks
                $payment_method_registry->register(new Fundiin_Gateway_Blocks);
            }
        );
    }
}
