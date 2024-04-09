<?php

/**
 * Settings for Fundiin Gateway.
 */

if (!defined("ABSPATH")) {
    exit();
}

$settings = [
    "enabled" => [
        "title" => __("Enable/Disable", "woocommerce-gateway-fundiin"),
        "label" => "Enable/Disable buy now pay later with Fundiin",
        "type" => "checkbox",
        "default" => "no",
        "description" => __(
            "Enable or Disable Fundiin Payment Gateway",
            "woocommerce-gateway-fundiin"
        ),
        "desc_tip" => true,
    ],
    "environment" => [
        "title" => __("Environment", "woocommerce-gateway-fundiin"),
        "label" => "Choose Environment of plugin",
        "type" => "select",
        "description" => __(
            "Depending on the environment, API configuration settings may vary. <br> NOTE: THE SANDBOX ENVIRONMENT IS RESERVED FOR TESTING, AND ALL TRANSACTIONS IN THE SANDBOX HAVE NO VALUE.",
            "woocommerce-gateway-fundiin"
        ),
        "default" => "sandbox",
        "desc_tip" => true,
        "options" => [
            "production" => __(
                "Production Environment (Live)",
                "woocommerce-gateway-fundiin"
            ),
            "sandbox" => __(
                "Testing Environment  (Sandbox)",
                "woocommerce-gateway-fundiin"
            ),
        ],
    ],
    "merchant_name" => [
        "title" => __("Merchant Name", "woocommerce-gateway-fundiin"),
        "type" => "text",
        "default" => "",
        "description" => __(
            "The business/partner name (your name). It will be attached to the order information (orderInfo) when sent to Fundiin.",
            "woocommerce-gateway-fundiin"
        ),
        "desc_tip" => true,
    ],
    "clientId" => [
        "title" => __("Client Id", "woocommerce-gateway-fundiin"),
        "type" => "text",
        "default" => "",
        "description" => __(
            "Client ID provided by Fundiin",
            "woocommerce-gateway-fundiin"
        ),
        "desc_tip" => true,
    ],
    "merchantId" => [
        "title" => __("Merchant Id", "woocommerce-gateway-fundiin"),
        "type" => "text",
        "default" => "",
        "description" => __(
            "Merchant ID provided by Fundiin",
            "woocommerce-gateway-fundiin"
        ),
        "desc_tip" => true,
    ],

    "secretKey" => [
        "title" => __("Secret Key", "woocommerce-gateway-fundiin"),
        "type" => "password",
        "default" => "",
        "description" => __(
            "Secret key provided by Fundiin ",
            "woocommerce-gateway-fundiin"
        ),
        "desc_tip" => true,
    ],

    "storeId" => [
        "title" => __("Store ID", "woocommerce-gateway-fundiin"),
        "type" => "text",
        "default" => "",
        "description" => __(
            "Site ID provided by Fundiin. Please leave it empty if you are not using it.",
            "woocommerce-gateway-fundiin"
        ),
        "desc_tip" => true,
    ],

    "notify_url" => [
        "title" => __("Notify URL", "woocommerce-gateway-fundiin"),
        "type" => "text",
        "default" => "",
        "description" => __(
            "URL used for callback processing. Please leave it empty if you are not using it.",
            "woocommerce-gateway-fundiin"
        ),
        "desc_tip" => true,
    ],
];

return apply_filters("woocommerce_fundiin_settings", $settings);
