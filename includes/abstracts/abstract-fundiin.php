<?php
if (!defined('ABSPATH')) {
    exit(); // Exit if accessed directly.
}

/**
 * WC_Fundiin_Payment_Gateway
 */
abstract class WC_Fundiin_Payment_Gateway extends WC_Payment_Gateway
{
    /**
     * Constructor for the Fundiin Gateway
     */
    public function __construct() {
        $this->id = 'fundiin';
        $this->icon = apply_filters('woocommerce_custom_gateway_icon', 'https://storage.googleapis.com/fundiin-asset/merchant/logo_long_image_reverse.svg');
        $this->has_fields = false;
        $this->method_title = __('Fundiin Payment Gateway', fundiin()->domain);
        $this->method_description = __('Mua trước trả sau cùng Fundiin', fundiin()->domain);

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        $this->supports = ['products', 'refunds'];

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    public function process_admin_options() {
        parent::process_admin_options();
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields() {
        $this->form_fields = include dirname(dirname(__FILE__)) . '/settings/settings-fundiin.php';
    }

    // Display additional fields on the checkout page
    public function payment_fields() {
        fundiin()->visibility->fundiin_in_checkout();
    }

    public function update_payment_method($order, $payment_method) {
        switch ($payment_method) {
            default:
                break;
        }

        $order->save();
    }
}
