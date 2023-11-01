<?php

namespace BluecoralWoo\Traits;

Trait VnSaleorForWoo {
	
	function getDataFormattedVnSaleorForWoo($raw_metas = []) {
		$data = [];
		// Parse metas
		$metas = [];
		$meta_keys = [
			'_billing_address_1' => self::FIELD_BILLING_ADDRESS,
			'_billing_city' => self::FIELD_BILLING_CITY,
			'_billing_state' => self::FIELD_BILLING_STATE,
			'_billing_country' => self::FIELD_BILLING_COUNTRY,
			'_shipping_address_1' => self::FIELD_SHIPPING_ADDRESS,
			'_shipping_city' => self::FIELD_SHIPPING_CITY,
			'_shipping_state' => self::FIELD_SHIPPING_STATE,
			'_shipping_country' => self::FIELD_SHIPPING_COUNTRY,
		];
		foreach ($meta_keys as $raw_meta_key => $meta_key) {
			if (!empty($raw_metas[$raw_meta_key])) {
				$metas[$meta_key] = $raw_metas[$raw_meta_key][0] ? $raw_metas[$raw_meta_key][0] : $raw_metas[$raw_meta_key];
			}
		}
		// no ward field
		$metas[self::FIELD_BILLING_WARD] = '';
		if (!empty($metas[self::FIELD_SHIPPING_ADDRESS])) {
			$metas[self::FIELD_SHIPPING_WARD] = '';
		}
		$data['metas'] = $metas;
		// Formatted data
		$formatted = [];
		foreach ($metas as $key => $value) {
			$formatted[$key] = $value;		
		}
		// billing
		$formatted = array_merge($formatted, $this->getDataFormattedItemVnSaleorForWoo($formatted, 'billing'));
		// shipping
		$formatted = array_merge($formatted, $this->getDataFormattedItemVnSaleorForWoo($formatted, 'shipping'));
		$data['formatted'] = $formatted;
		return $data;
	}
	
	function getDataFormattedItemVnSaleorForWoo($data = [], $type = 'billing') {
		global $wpdb;
		$key_state = $type . '_state';
		$key_city = $type . '_city';
		$key_ward = $type . '_ward';
		
		$state = $data[$key_state] ? $data[$key_state] : '';
		$city = $data[$key_city] ? $data[$key_city] : '';
		if (empty($state) || empty($state)) {
			return [];
		}
		$address = $wpdb->get_row('SELECT * FROM ' . $wpdb->prefix . 'vnsfw WHERE state_code = "' . esc_sql($state) . '" AND district_ward_code = "' . esc_sql($city) . '"');		
		if ( isset( $address ) && empty( $address->state_name ) ) {
			return [];
		}
		$state_value = $address->state_name;
		$district_ward_value = explode('-', $address->district_ward_name);
		if (count($district_ward_value) === 3) {
			$city_value = trim($district_ward_value[0]);
			$ward_value = trim($district_ward_value[1]) . ' ' . trim($district_ward_value[2]);
		}
		if (count($district_ward_value) === 2) {
			$city_value = trim($district_ward_value[0]);
			$ward_value = trim($district_ward_value[1]);			
		}
		return [
			$key_state => $state_value,
			$key_city => $city_value,
			$key_ward => $ward_value,
		];
	}
	
}
