<?php

/**
 * Settings for Fundiin Gateway.
 */

if (!defined("ABSPATH")) {
    exit();
}

$settings = [
    "enabled" => [
        "title" => __("Enable/Disable", "woocommerce-fundiin-gateway"),
        "label" => "Enable/Disable buy now pay later with Fundiin",
        "type" => "checkbox",
        "default" => "no",
        "description" => __(
            "Enable or Disable Fundiin Payment Gateway",
            "woocommerce-fundiin-gateway"
        ),
        "desc_tip" => true,
    ],
    "environment" => [
        "title" => __("Environment", "woocommerce-fundiin-gateway"),
        "label" => "Choose Environment of plugin",
        "type" => "select",
        "description" => __(
            "Depending on the environment, API configuration settings may vary. <br> NOTE: THE SANDBOX ENVIRONMENT IS RESERVED FOR TESTING, AND ALL TRANSACTIONS IN THE SANDBOX HAVE NO VALUE.",
            "woocommerce-fundiin-gateway"
        ),
        "default" => "sandbox",
        "desc_tip" => true,
        "options" => [
            "production" => __(
                "Production Environment (Live)",
                "woocommerce-fundiin-gateway"
            ),
            "sandbox" => __(
                "Testing Environment (Sandbox)",
                "woocommerce-fundiin-gateway"
            ),
        ],
    ],
    "merchant_name" => [
        "title" => __("Merchant Name", "woocommerce-fundiin-gateway"),
        "type" => "text",
        "default" => "",
        "description" => __(
            "The business/partner name (your name). It will be attached to the order information (orderInfo) when sent to Fundiin.",
            "woocommerce-fundiin-gateway"
        ),
        "desc_tip" => true,
    ],
    "clientId" => [
        "title" => __("Client Id", "woocommerce-fundiin-gateway"),
        "type" => "text",
        "default" => "",
        "description" => __(
            "Client ID provided by Fundiin",
            "woocommerce-fundiin-gateway"
        ),
        "desc_tip" => true,
    ],
    "merchantId" => [
        "title" => __("Merchant Id", "woocommerce-fundiin-gateway"),
        "type" => "text",
        "default" => "",
        "description" => __(
            "Merchant ID provided by Fundiin",
            "woocommerce-fundiin-gateway"
        ),
        "desc_tip" => true,
    ],

    "secretKey" => [
        "title" => __("Secret Key", "woocommerce-fundiin-gateway"),
        "type" => "password",
        "default" => "",
        "description" => __(
            "Secret key provided by Fundiin ",
            "woocommerce-fundiin-gateway"
        ),
        "desc_tip" => true,
    ],

    "storeId" => [
        "title" => __("Store ID", "woocommerce-fundiin-gateway"),
        "type" => "text",
        "default" => "",
        "description" => __(
            "Site ID provided by Fundiin. Please leave it empty if you are not using it.",
            "woocommerce-fundiin-gateway"
        ),
        "desc_tip" => true,
    ],

    "notify_url" => [
        "title" => __("Notify URL", "woocommerce-fundiin-gateway"),
        "type" => "text",
        "default" => "",
        "description" => __(
            "URL used for callback processing. Please leave it empty if you are not using it.",
            "woocommerce-fundiin-gateway"
        ),
        "desc_tip" => true,
    ],
    "orderExpiredTime" => [
        "title" => __("orderExpiredTime", "woocommerce-fundiin-gateway"),
        "type" => "number",
        "default" => 1800,
        "description" => __(
            "Set the expiration time (in seconds) for an order. Once this time elapses, the order will be marked as expired",
            "woocommerce-fundiin-gateway"
        ),
        "desc_tip" => true,
    ],

];

return apply_filters("woocommerce_fundiin_settings", $settings);
