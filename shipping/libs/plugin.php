<?php

namespace BluecoralWoo;

require_once dirname(__FILE__) . '/traits/friendstore-for-woocommerce.php';
require_once dirname(__FILE__) . '/traits/shipping-checkout-localized-for-vietnam.php';
require_once dirname(__FILE__) . '/traits/shipping-vietnam-woocommerce.php';
require_once dirname(__FILE__) . '/traits/viet-nam-saleor-for-woocommerce.php';
require_once dirname(__FILE__) . '/traits/woo-viet.php';
require_once dirname(__FILE__) . '/traits/woo-vietnam-checkout.php';

use BluecoralWoo\Traits\FriendstoreForWoo;
use BluecoralWoo\Traits\ShippingCheckoutLocalizedVn;
use BluecoralWoo\Traits\ShippingVnWoo;
use BluecoralWoo\Traits\VnSaleorForWoo;
use BluecoralWoo\Traits\WooViet;
use BluecoralWoo\Traits\WooVnCheckout;
use \WC_Countries;

class Plugin {
	
	use FriendstoreForWoo,
		ShippingCheckoutLocalizedVn,
		ShippingVnWoo,
		VnSaleorForWoo,
		WooViet,
		WooVnCheckout;
		
	const EXT_FRIENDSTORE_FOR_WOO = 'friendstore-for-woocommerce/friendstore-for-woocommerce.php';
	const EXT_SHIPPING_CHECKOUT_LOCALIZED_VN = 'shipping-checkout-localized-for-vietnam/shipping-checkout-localized-for-vietnam.php';
	const EXT_SHIPPING_VN_WOO = 'shipping-viet-nam-woocommerce/shipping-vietnam-woocommerce.php';
	const EXT_VN_SALEOR_FOR_WOO = 'viet-nam-saleor-for-woocommerce/viet-nam-saleor-for-woocommerce.php';
	const EXT_WOO_VIET = 'woo-viet/woo-viet.php';
	const EXT_WOO_VN_CHECKOUT = 'woo-vietnam-checkout/devvn-woo-address-selectbox.php';
	
	const FIELD_BILLING_ADDRESS = 'billing_address';
	const FIELD_BILLING_CITY = 'billing_city';
	const FIELD_BILLING_COUNTRY = 'billing_country';
	const FIELD_BILLING_STATE = 'billing_state';
	const FIELD_BILLING_WARD = 'billing_ward';
	
	const FIELD_SHIPPING_ADDRESS = 'shipping_address';
	const FIELD_SHIPPING_CITY = 'shipping_city';
	const FIELD_SHIPPING_COUNTRY = 'shipping_country';
	const FIELD_SHIPPING_STATE = 'shipping_state';
	const FIELD_SHIPPING_WARD = 'shipping_ward';
	
	private static $_instance;
	public $actives = [];
	public $type = '';
		
	/**
	* Class Construct
	*/
	public function __construct() {	
		$this->getPluginType();
	}
		
		
	/**
	* Functions
	*/		
	public static function instance() {
		if (!empty(static::$_instance)) {
			return static::$_instance;
		}
		return new static();
	}
	
	public function getPluginType() {
		$this->actives = apply_filters('active_plugins', get_option('active_plugins'));
		
		$plugins = [
			self::EXT_FRIENDSTORE_FOR_WOO,
			self::EXT_SHIPPING_CHECKOUT_LOCALIZED_VN,
			self::EXT_SHIPPING_VN_WOO,
			self::EXT_VN_SALEOR_FOR_WOO,
			self::EXT_WOO_VIET,
			self::EXT_WOO_VN_CHECKOUT,
		];
		foreach ($plugins as $plugin) {
			if (in_array($plugin, $this->actives)) {
				$this->type = $plugin;
			}
		}
	}
	
	public function getData(int $id = 0) {
		try {
			$meta = get_post_meta($id);
			switch ($this->type) {
				case self::EXT_FRIENDSTORE_FOR_WOO:
					$data = $this->getDataFormattedFriendstoreForWoo($meta);
					break;
					
				case self::EXT_SHIPPING_CHECKOUT_LOCALIZED_VN:
					$data = $this->getDataFormattedShippingCheckoutLocalizedVn($meta);
					break;
					
				case self::EXT_SHIPPING_VN_WOO:
					$data = $this->getDataFormattedShippingVnWoo($meta);
					break;
					
				case self::EXT_VN_SALEOR_FOR_WOO:
					$data = $this->getDataFormattedVnSaleorForWoo($meta);
					break;
					
				case self::EXT_WOO_VIET:
					$data = $this->getDataFormattedWooViet($meta);
					break;
					
				case self::EXT_WOO_VN_CHECKOUT:
					$data = $this->getDataFormattedWooVnCheckout($meta);
					break;
					
				default:
					$data = [];
			}
			$data = array_merge($data, [
				'order_id' => $id,
				'type' => $this->type,
			]);
		} catch (\Exception $e) {
			throw $e;
		}
		return $data;
	}
	
	function getProvinceByKey($raw_province = '') {
		$state = (new WC_Countries())->get_states('VN');
		return $state[$raw_province] ? $state[$raw_province] : '';
	}
	
	public function setPluginType($type = '') {
		$this->type = $type;
	}
	
}
