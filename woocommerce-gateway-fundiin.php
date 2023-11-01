<?php

/**
 * Plugin Name: Fundiin Payment Gateway for WooCommerce
 * Description: Buy Now Pay Later Service for WooCommerce by Fundiin
 * Version: 2.0.1
 * Author: FUNDIIN JSC
 * Author URI: https://fundiin.vn
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: woocommerce-gateway-fundiin
 * Domain Path: /
 */
/**
 * Copyright FUNDIIN JSC 2023
 *
 * CÔNG TY CỔ PHẦN FUNDIIN
 * hoạt động chính trong lĩnh vực mua hàng trước và trả tiền sau
 * Công ty đã được cấp giấy phép đăng ký kinh doanh số 0315563775.
 * Tầng 7, Tòa nhà Lottery, số 77, đường Trần Nhân Tôn, Phường 09, Quận 5, Thành phố Hồ Chí Minh, Việt Nam
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WC_GATEWAY_FUNDIIN_VERSION', '2.0.1');

function fundiin()
{
    static $plugin;


    if (!isset($plugin)) {
        require_once 'includes/class-fundiin-plugin.php';

        $plugin = new Fundiin_Plugin(__FILE__, WC_GATEWAY_FUNDIIN_VERSION);
    }
    return $plugin;
}


function load_shipping_libraries_classes()
{
    $files = glob(dirname(FUNDIIN_PLUGIN_FILE) . '/shipping/libs/*.php');

    foreach ($files as $file) {
        require_once $file;
    }

    // Create instance object
    $plugin = \BluecoralWoo\Plugin::instance();
    $GLOBALS['fdn_shipping'] = $plugin;
}

fundiin()->run();