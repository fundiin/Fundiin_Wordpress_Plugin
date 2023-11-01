<?php

if (!function_exists('bluecoral_get_order_address')) {
	function bluecoral_get_order_address($post_id = 0) {
		$plugin = $GLOBALS['plugin_bwpa'];
		return $plugin->get_address_object($post_id);
	}
}
