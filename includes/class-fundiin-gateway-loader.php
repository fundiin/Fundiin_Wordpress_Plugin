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
    public function __construct()
    {
        $includes_path = fundiin()->includes_path;

        require_once $includes_path . 'abstracts/abstract-fundiin.php';
        require_once $includes_path . 'class-fundiin-with-aio.php';

        add_filter('woocommerce_payment_gateways', array($this, 'payment_gateways'));
    }

    /**
     * Register the Fundiin payment methods.
     *
     * @param array $methods Payment methods.
     *
     * @return array Payment methods
     */
    public function payment_gateways($methods)
    {

        $methods[] = 'Fundiin_With_AIO';

        return $methods;
    }
}