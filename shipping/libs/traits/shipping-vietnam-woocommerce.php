<?php

namespace BluecoralWoo\Traits;

Trait ShippingVnWoo {
	
	public function getDataFormattedShippingVnWoo($raw_metas = []) {
		$data = [];
		// Parse metas
		$metas = [];
		$meta_keys = [
			'_billing_address_1' => self::FIELD_BILLING_ADDRESS,
			'_billing_svw_district' => self::FIELD_BILLING_CITY,
			'_billing_svw_province' => self::FIELD_BILLING_STATE,
			'_billing_country' => self::FIELD_BILLING_COUNTRY,
			'_billing_svw_ward' => self::FIELD_BILLING_WARD,
			'_shipping_address_1' => self::FIELD_SHIPPING_ADDRESS,
			'_shipping_svw_district' => self::FIELD_SHIPPING_CITY,
			'_shipping_svw_province' => self::FIELD_SHIPPING_STATE,
			'_shipping_country' => self::FIELD_SHIPPING_COUNTRY,
			'_shipping_svw_ward' => self::FIELD_SHIPPING_WARD,
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
					$formatted[$key] = $this->getProvinceShippingVnWoo($value);
					break;
					
				case self::FIELD_BILLING_CITY:
					$formatted[$key] = $this->getCityShippingVnWoo($value, $metas[self::FIELD_BILLING_STATE]);
					break;
				
				case self::FIELD_SHIPPING_CITY:
					$formatted[$key] = $this->getCityShippingVnWoo($value, $metas[self::FIELD_SHIPPING_STATE]);
					break;
					
				case self::FIELD_BILLING_WARD:
					$formatted[$key] = $this->getWardShippingVnWoo($value, $metas[self::FIELD_BILLING_CITY]);
					break;
					
				case self::FIELD_SHIPPING_WARD:
					$formatted[$key] = $this->getWardShippingVnWoo($value, $metas[self::FIELD_SHIPPING_CITY]);
					break;
				
				default:
					$formatted[$key] = $value;					
			}
		}
		$data['formatted'] = $formatted;
		return $data;		
	}
	
	function getProvinceShippingVnWoo($raw_province = '') {
		$file = dirname(WP_PLUGIN_DIR . '/' . self::EXT_SHIPPING_VN_WOO) . '/assets/json/cities.json';
		if (!file_exists($file)) {
			return '';
		}
		$provinces = json_decode(file_get_contents($file), TRUE);
		return isset( $provinces[$raw_province] ) ? $provinces[$raw_province] : '';
	}
	
	function getCityShippingVnWoo($raw_city = '', $raw_province = '') {
		$file = dirname(WP_PLUGIN_DIR . '/' . self::EXT_SHIPPING_VN_WOO) . '/assets/json/districts.json';
		if (!file_exists($file)) {
			return '';
		}
		$cities = json_decode(file_get_contents($file), TRUE);
		return isset( $cities[$raw_province] ) && isset( $cities[$raw_province][$raw_city] ) ? $cities[$raw_province][$raw_city] : '';		
	}
	
	function getWardShippingVnWoo($raw_ward = '', $raw_city = '') {
		$file = dirname(WP_PLUGIN_DIR . '/' . self::EXT_SHIPPING_VN_WOO) . '/assets/json/wards.json';
		if (!file_exists($file)) {
			return '';
		}
		$wards = json_decode(file_get_contents($file), TRUE);
		return isset( $wards[$raw_city] ) && isset( $wards[$raw_city][$raw_ward] ) ? $wards[$raw_city][$raw_ward] : '';		
	}
	
}
