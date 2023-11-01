<?php

namespace BluecoralWoo\Traits;

Trait WooVnCheckout {
	
	function getDataFormattedWooVnCheckout($raw_metas = []) {
		$data = [];
		// Parse metas
		$metas = [];
		$meta_keys = [
			'_billing_address_1' => self::FIELD_BILLING_ADDRESS,
			'_billing_city' => self::FIELD_BILLING_CITY,
			'_billing_state' => self::FIELD_BILLING_STATE,
			'_billing_country' => self::FIELD_BILLING_COUNTRY,
			'_billing_address_2' => self::FIELD_BILLING_WARD,
			'_shipping_address_1' => self::FIELD_SHIPPING_ADDRESS,
			'_shipping_city' => self::FIELD_SHIPPING_CITY,
			'_shipping_state' => self::FIELD_SHIPPING_STATE,
			'_shipping_country' => self::FIELD_SHIPPING_COUNTRY,
			'_shipping_address_2' => self::FIELD_SHIPPING_WARD,
		];
		foreach ($meta_keys as $raw_meta_key => $meta_key) {
			if (!empty($raw_metas[$raw_meta_key])) {
				$metas[$meta_key] = $raw_metas[$raw_meta_key][0] ? $raw_metas[$raw_meta_key][0] : $raw_metas[$raw_meta_key];
			}
		}
		$data['metas'] = $metas;
		// Formatted data
		$formatted = [];
		foreach ($metas as $key => $value) {
			switch ($key) {
				case self::FIELD_BILLING_STATE:
				case self::FIELD_SHIPPING_STATE:
					$formatted[$key] = $this->getProvinceByKey($value);
					break;
					
				case self::FIELD_BILLING_CITY:
				case self::FIELD_SHIPPING_CITY:
					$formatted[$key] = $this->getCityWooVnCheckout($value);
					break;
					
				case self::FIELD_BILLING_WARD:
				case self::FIELD_SHIPPING_WARD:
					$formatted[$key] = $this->getWardWooVnCheckout($value);
					break;
				
				default:
					$formatted[$key] = $value;					
			}
		}
		$data['formatted'] = $formatted;
		return $data;
	}
	
	function getCityWooVnCheckout($raw_city = '') {
		$file = dirname(WP_PLUGIN_DIR . '/' . self::EXT_WOO_VN_CHECKOUT) . '/cities/quan_huyen.php';
		if (!file_exists($file)) {
			return '';
		}
		include $file;
		$index = array_search($raw_city, array_column($quan_huyen, 'maqh'));
		return $quan_huyen[$index]['name'] ? $quan_huyen[$index]['name'] : '';
	}
	
	function getWardWooVnCheckout($raw_ward = '') {
		$file = dirname(WP_PLUGIN_DIR . '/' . self::EXT_WOO_VN_CHECKOUT) . '/cities/xa_phuong_thitran.php';
		if (!file_exists($file)) {
			return '';
		}
		include $file;
		$ward = sprintf("%05d", intval($raw_ward));
		$index = array_search($ward, array_column($xa_phuong_thitran, 'xaid'));
		return $xa_phuong_thitran[$index]['name'] ? $xa_phuong_thitran[$index]['name'] : '';
	}
	
}
