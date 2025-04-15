<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Fundiin_Visibility
{
    function __construct() {
        $this->register_action();
    }

    function register_action() {
        $this->add_to_single_product();
        $this->add_to_cart_page();
    }

    public function add_to_single_product() {
        add_action('woocommerce_before_add_to_cart_form', [$this, 'fundiin_price_in_product_single'], 13);
    }

    public function add_to_cart_page() {
        add_action('woocommerce_proceed_to_checkout', [$this, 'fundiin_price_in_cart_page'], 5);
    }

    public function fundiin_price_in_product_single() {
        global $product;
        $product_price = (int) $product->get_price();

        if (!!$product && is_int($product_price)) {
            echo '<div id="script-general-container"></div>';
            echo '
                <script type="text/javascript">
                    var fundiinDetailConfig = {
                        data: { amount: ' . $product_price . ' },
                        style: {},
                    };
                </script>
            ';
            echo '<script type="application/javascript" defer src="' . $this->generateGatewayUrl('productdetailjs') . '"></script>';
        }
    }

    public function fundiin_price_in_cart_page() {
        $cart_price = WC()->cart->total;

        if ($cart_price) {
            echo '<div id="script-general-container"></div>';
            echo '
                <script type="text/javascript">
                    var fundiinCartConfig = {
                        data: { amount: ' . $cart_price . ' },
                        style: {},
                    };
                </script>
            ';
            echo '<script type="application/javascript" defer src="' . $this->generateGatewayUrl('cartjs') . '"></script>';
        }
    }

    private function generateGatewayUrl($page) {
        $merchantId = fundiin()->settings->merchantId;
        $host = fundiin()->settings->get_fundiin_host();
        return $host . '/merchants/' . $page . '/' . $merchantId . '.js';
    }
}
