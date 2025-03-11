<?php

if (!defined("ABSPATH")) {
    exit(); // Exit if accessed directly.
}

/**
 * WC_Gateway_Fundiin
 */

abstract class WC_Gateway_Fundiin extends WC_Payment_Gateway
{
    /**
     * @var string
     */
    public $id;
    public $order_button_text;
    /**
     * @var false
     */
    public $has_fields;
    public $method_title;
    public $method_description;
    /**
     * @var array|string[]
     */
    public $supports;
    public $title;
    public $description;
    public $enabled;
    public $environment;
    public $merchantName;
    public $notifyUrl;
    public $merchantId;
    public $clientId;
    public $secretKey;
    public $storeId;
    /**
     * @var mixed
     */
    public $form_fields;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->id = "fundiin";
        $this->has_fields = false;
        $this->order_button_text = __("Mua trước trả sau", "woocommerce-gateway-fundiin");
        $this->method_title = __("Fundiin Payment Gateway", "woocommerce-gateway-fundiin");
        $this->method_description = __(
            "Thanh toán trả sau cùng Fundiin",
            "woocommerce-gateway-fundiin"
        );

        $this->supports = ["products", "refunds"];

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title = __("Thanh toán trả sau cùng Fundiin", "woocommerce-gateway-fundiin");
        $this->description = __("Thanh toán trả sau cùng Fundiin", "woocommerce-gateway-fundiin");
        $this->enabled = $this->get_option("enabled");
        $this->environment = $this->get_option("environment", "test");
        $this->merchantName = $this->get_option("merchant_name");
        $this->notifyUrl = $this->get_option("notify_url");

        $this->clientId = $this->get_option("clientId");
        $this->merchantId = $this->get_option("merchantId");
        $this->secretKey = $this->get_option("secretKey");
        $this->storeId = $this->get_option("storeId");

        add_action("woocommerce_update_options_payment_gateways_" . $this->id, [
            $this,
            "process_admin_options",
        ]);
        add_action("wp_enqueue_scripts", [$this, "settings_scripts"]);
    }

    public function process_admin_options()
    {
        parent::process_admin_options();
    }

    public function settings_scripts()
    {
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = include dirname(dirname(__FILE__)) .
            "/settings/settings-fundiin.php";
    }

    // Display additional fields on the checkout page
    public function payment_fields()
    {
        fundiin()->visibility->fundiin_in_checkout();
    }

    public function update_payment_method($order, $payment_method)
    {
        switch ($payment_method) {
            default:
                break;
        }

        $order->save();
    }
}
