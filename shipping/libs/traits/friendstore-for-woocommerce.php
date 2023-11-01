<?php

namespace BluecoralWoo\Traits;

Trait FriendstoreForWoo {
	
	public function getDataFormattedFriendstoreForWoo($raw_metas = []) {
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
				$metas[$meta_key] = $raw_metas[$raw_meta_key][0] ? $raw_metas[$raw_meta_key][0] :  $raw_metas[$raw_meta_key];
			}
		}
		$data['metas'] = $metas;
		// Formatted data
		$formatted = [];
		foreach ($metas as $key => $value) {
			switch ($key) {
				case self::FIELD_BILLING_STATE:
				case self::FIELD_SHIPPING_STATE:
					$formatted[$key] = $this->getProvinceFriendstoreForWoo($value);
					break;
					
				case self::FIELD_BILLING_CITY:
					$formatted[$key] = $this->getCityFriendstoreForWoo($value, $metas[self::FIELD_BILLING_STATE]);
					break;
				
				case self::FIELD_SHIPPING_CITY:
					$formatted[$key] = $this->getCityFriendstoreForWoo($value, $metas[self::FIELD_SHIPPING_STATE]);
					break;
					
				case self::FIELD_BILLING_WARD:
					$formatted[$key] = $this->getWardFriendstoreForWoo($value, $metas[self::FIELD_BILLING_CITY]);
					break;
					
				case self::FIELD_SHIPPING_WARD:
					$formatted[$key] = $this->getWardFriendstoreForWoo($value, $metas[self::FIELD_SHIPPING_CITY]);
					break;
				
				default:
					$formatted[$key] = $value;					
			}
		}
		$data['formatted'] = $formatted;
		return $data;		
	}
	
	function getProvinceFriendstoreForWoo($raw_province = '') {
		$file = dirname(WP_PLUGIN_DIR . '/' . self::EXT_FRIENDSTORE_FOR_WOO) . '/assets/json/cities.php';
		if (!file_exists($file)) {
			return '';
		}
		include $file;
		return isset( $cities[$raw_province] ) ? $cities[$raw_province] : '';
	}
	
	function getCityFriendstoreForWoo($raw_city = '', $raw_province = '') {
		$file = dirname(WP_PLUGIN_DIR . '/' . self::EXT_FRIENDSTORE_FOR_WOO) . '/assets/json/districts.php';
		if (!file_exists($file)) {
			return '';
		}
		include $file;
		return isset( $districts[$raw_province] ) && isset( $districts[$raw_province][$raw_city] ) ? $districts[$raw_province][$raw_city] : '';		
	}
	
	function getWardFriendstoreForWoo($raw_ward = '', $raw_city = '') {
		$file = dirname(WP_PLUGIN_DIR . '/' . self::EXT_FRIENDSTORE_FOR_WOO) . '/assets/json/wards.php';
		if (!file_exists($file)) {
			return '';
		}
		include $file;
		return isset( $wards[$raw_city] ) && isset( $wards[$raw_city][$raw_ward] ) ? $wards[$raw_city][$raw_ward] : '';		
	}
	
}
