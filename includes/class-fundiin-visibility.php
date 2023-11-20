<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
class Fundiin_Visibility
{
    function __construct()
    {
        $this->register_action();
    }

    function register_action()
    {
        $this->add_to_single_product();
    }
    public function add_to_single_product()
    {
        add_action('woocommerce_before_add_to_cart_form', array($this, 'fundiin_price_in_product_single'), 13);
    }
    public function fundiin_price_in_product_single()
    {
        global $product;
        if ($product) {
            $product_price = (int) $product->get_price();
            $merchantId = fundiin()->settings->merchantId;
            $host = fundiin()->settings->get_fundiin_host();
            echo '<div id="script-general-container"></div>

            ';
            echo '<script type="application/javascript">var price = ' . $product_price . '; </script>';
            echo '<script type="application/javascript" 
                        crossorigin="anonymous" 
                        src="' . $host . '/merchants/productdetailjs/' . $merchantId . '.js">
                </script>';

        }
    }
    public function fundiin_in_checkout()
    {
        $merchantId = fundiin()->settings->merchantId;
        $host = fundiin()->settings->get_fundiin_host();
        echo '<script type="application/javascript" 
                        crossorigin="anonymous" 
                        src="' . $host . '/merchants/checkoutjs/' . $merchantId . '.js">
                </script>';
        echo "<div id='script-checkout-container'></div>";
    }

}