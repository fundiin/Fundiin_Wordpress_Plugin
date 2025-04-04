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
        $includes_path = fundiin()->includes_path;

        require_once $includes_path . 'abstracts/abstract-fundiin.php';
        require_once $includes_path . 'class-fundiin-with-aio.php';

        add_filter('woocommerce_payment_gateways', [$this, 'add_fundiin_payment_gateway']);
    }

    /**
     * Register the Fundiin Gateway.
     *
     * @param array $methods Payment methods.
     *
     * @return array Payment methods
     */
    public function add_fundiin_payment_gateway($methods) {
        $methods[] = fundiin()->slug;
        return $methods;
    }
}
