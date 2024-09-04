<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
class Fundiin_Visibility
{
    private $action_registered = false;

    function __construct()
    {
        $this->register_action();
    }

    function register_action()
    {
        if (!$this->action_registered) {
            $this->add_to_single_product();
            $this->action_registered = true;
        }
    }

    public function add_to_single_product()
    {
        add_action('woocommerce_before_add_to_cart_form', array($this, 'fundiin_price_in_product_single'), 13);
    }

    public function fundiin_price_in_product_single()
    {
        if ($this->script_printed) {
            return;
        }

        global $product;
        if ($product) {
            $product_price = (int) $product->get_price();
            $merchantId = fundiin()->settings->merchantId;
            $host = fundiin()->settings->get_fundiin_host();
            echo '<div id="script-general-container"></div>';
            echo '<script type="application/javascript">var price = ' . $product_price . '; </script>';
            echo '<script type="application/javascript" 
                        crossorigin="anonymous" 
                        src="' . $host . '/merchants/productdetailjs/' . $merchantId . '.js">
                </script>';

            $this->script_printed = true;
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


