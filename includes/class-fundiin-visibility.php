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

        $this->add_to_cart_page();
    }

    public function add_to_single_product()
    {
        add_action('woocommerce_before_add_to_cart_form', array($this, 'fundiin_price_in_product_single'), 13);
    }

    public function add_to_cart_page()
    {
        add_action('woocommerce_proceed_to_checkout', array($this, 'fundiin_price_in_cart_page'), 20);
    }

    public function fundiin_price_in_product_single()
    {
        global $product;

        if ($product) {
            $product_price = (int) $product->get_price();
            $merchantId = fundiin()->settings->merchantId;
            $host = fundiin()->settings->get_fundiin_host();

            echo '<div id="script-general-container"></div>';
            echo '<script type="text/javascript">var fundiinDetailConfig = { data: { amount: ' . $product_price . ' }, style: {} }; </script>';
            echo '<script type="application/javascript" async src="' . $host . '/merchants/productdetailjs/' . $merchantId . '.js"></script>';
        }
    }

    public function fundiin_price_in_cart_page()
    {
        $cart_price = WC()->cart->total;

        if ($cart_price) {
            $merchantId = fundiin()->settings->merchantId;
            $host = fundiin()->settings->get_fundiin_host();
            echo '<div id="script-general-container"></div>';
            echo '<script type="text/javascript">var fundiinCartConfig = { data: { amount: ' . $cart_price . ' }, style: {}; </script>';
            echo '<script type="application/javascript" async src="' . $host . '/merchants/cartjs/' . $merchantId . '.js"></script>';
        }
    }

    public function fundiin_in_checkout()
    {
        $merchantId = fundiin()->settings->merchantId;
        $host = fundiin()->settings->get_fundiin_host();

        echo '<div id="script-checkout-container"></div>';
        echo '<script type="application/javascript" src="' . $host . '/merchants/checkoutjs/' . $merchantId . '.js"></script>';
        echo "
            <script type='text/javascript'>
                var trackingScript = document.createElement('script');
                trackingScript.type = 'text/javascript';
                trackingScript.innerHTML = `
                    (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
                    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
                    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
                    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
                    })(window,document,'script','dataLayer','GTM-TP47LP6K');
                `;
                document.head.appendChild(trackingScript);

                var eventScript = document.createElement('script');
                eventScript.type = 'text/javascript';
                eventScript.innerHTML = `
                    window.dataLayer = window.dataLayer || [];
                    window.addEventListener('DOMContentLoaded', function() {
                        window.dataLayer.push({
                            event: 'view_checkout',
                            event_category: 'mx_site',
                            event_action: 'view',
                            event_label: 'mx_checkout',
                        });
                    });
                `;
                document.body.appendChild(eventScript);
            </script>
        ";
    }
}
