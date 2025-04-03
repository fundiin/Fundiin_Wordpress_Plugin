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

    public function fundiin_in_checkout() {
        $checkoutConfig = $this->checkoutConfigFactory();

        echo '<div id="script-checkout-container"></div>';
        echo '
            <script type="text/javascript">
                var fundiinCheckoutConfig = {
                    data: {
                        cartItems: [' . $checkoutConfig['cart_items'] . '],
                        referenceId: "' . $checkoutConfig['ref_id'] . '",
                        orderId: "' . $checkoutConfig['order_id'] . '",
                    },
                };
            </script>
        ';
        echo '<script type="application/javascript" defer src="' . $this->generateGatewayUrl('checkoutjs') . '"></script>';
    }

    private function generateGatewayUrl($page) {
        $merchantId = fundiin()->settings->merchantId;
        $host = fundiin()->settings->get_fundiin_host();
        return $host . '/merchants/' . $page . '/' . $merchantId . '.js';
    }

    private function checkoutConfigFactory() {
        global $wpdb;

        $cart_hash = WC()->cart->get_cart_hash();
        $cart_items = '';
        $query = '';
        $order_id = '';
        $ref_id = '';

        if (!empty($cart_hash)) {
            $query = "
                SELECT o.id
                FROM {$wpdb->prefix}wc_order_operational_data od
                RIGHT JOIN {$wpdb->prefix}wc_orders o ON od.order_id = o.id
                WHERE od.cart_hash = '" . $cart_hash . "'
                ORDER BY o.date_created_gmt desc
                LIMIT 1
            ";
            $query_result = $wpdb->get_results($query);

            if (is_array($query_result) && count($query_result) > 0) {
                $order_id = $query_result[0]->id;
            }

            if (!empty($order_id)) {
                $order = wc_get_order($order_id);
                $ref_id = $order->get_id() . '_' . $order->get_date_created()->format('U');
            }
        }

        foreach (WC()->cart->cart_contents as $cart_item) {
            $cart_items .= '{' .
                'id:' . $cart_item['data']->id . ',' .
                'name: "' . $cart_item['data']->name . '",' .
                'variantId: ' . $cart_item['variation_id'] . ',' .
                'price:' . $cart_item['data']->price . ',' .
                'regularPrice:' . $cart_item['data']->regular_price . ',' .
                'quantity:' . $cart_item['quantity'] . ',' .
                'slug: "' . $cart_item['data']->slug . '",' .
                'sku: "' . $cart_item['data']->sku . '"' .
            '},';
        }

        return [
            'cart_items' => $cart_items,
            'ref_id' => $ref_id,
            'order_id' => $order_id,
        ];
    }
}
