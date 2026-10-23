<?php

if (!defined('ABSPATH')) {
    exit;
}

class Fundiin_Visibility
{
    private $script_printed = false;

    public function __construct()
    {
        add_action('woocommerce_before_add_to_cart_form', array($this, 'fundiin_price_in_product_single'), 13);
        add_action('woocommerce_proceed_to_checkout', array($this, 'fundiin_price_in_cart_page'), 20);
    }

    /** Build scripts only from a fixed HTTPS host and one encoded merchant path segment. */
    private function print_script($context)
    {
        $merchant = fundiin()->settings->merchantId;
        if (!is_string($merchant) || $merchant === '') {
            return;
        }
        $src = fundiin()->settings->get_fundiin_host() . '/merchants/' . $context . '/' . rawurlencode($merchant) . '.js';
        echo '<script type="application/javascript" crossorigin="anonymous" src="' . esc_url($src) . '"></script>';
    }

    public function fundiin_price_in_product_single()
    {
        if ($this->script_printed) {
            return;
        }
        global $product;
        if ($product) {
            echo '<div id="script-general-container"></div>';
            echo '<script type="application/javascript">var price = ' . wp_json_encode((float) $product->get_price()) . ';</script>';
            $this->print_script('productdetailjs');
            $this->script_printed = true;
        }
    }

    public function fundiin_price_in_cart_page()
    {
        if (WC()->cart && WC()->cart->total) {
            echo '<div id="script-general-container"></div>';
            echo '<script type="application/javascript">var price = ' . wp_json_encode((float) WC()->cart->total) . ';</script>';
            $this->print_script('cartjs');
        }
    }

    /** Legacy arbitrary script settings are deliberately no longer rendered. */
    public function fundiin_block()
    {
        $this->fundiin_price_in_product_single();
    }

    public function fundiin_in_checkout()
    {
        $this->print_script('checkoutjs');
        echo '<div id="script-checkout-container"></div>';
    }
}
