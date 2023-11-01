<?php

namespace BluecoralWoo\Traits;

Trait ShippingCheckoutLocalizedVn {
	
	public function getDataFormattedShippingCheckoutLocalizedVn($raw_metas = []) {
		$data = [];
		// Parse metas
		$metas = [];
		$meta_keys = [
			'_billing_address_1' => self::FIELD_BILLING_ADDRESS,
			'_billing_city' => self::FIELD_BILLING_CITY,
			'_billing_state' => self::FIELD_BILLING_STATE,
			'_billing_country' => self::FIELD_BILLING_COUNTRY,
			'_billing_ward' => self::FIELD_BILLING_WARD,
			'_shipping_address_1' => self::FIELD_SHIPPING_ADDRESS,
			'_shipping_city' => self::FIELD_SHIPPING_CITY,
			'_shipping_state' => self::FIELD_SHIPPING_STATE,
			'_shipping_country' => self::FIELD_SHIPPING_COUNTRY,
			'_shipping_ward' => self::FIELD_SHIPPING_WARD,
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
				
				default:
					$formatted[$key] = $value;					
			}
		}
		$data['formatted'] = $formatted;		
		return $data;		
	}
	
}
