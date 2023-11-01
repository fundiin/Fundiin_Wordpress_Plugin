<?php

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class Fundiin_With_AIO extends WC_Gateway_Fundiin
{
	public function __construct()
	{
		parent::__construct();
	}

	public function get_fundiin_checkout_url()
	{
		return fundiin()->settings->get_fundiin_aio_url();
	}

	public function get_fundiin_refund_url()
	{
		return fundiin()->settings->get_fundiin_refund_url();
	}

	public function process_payment($order_id)
	{
		$order = new WC_Order($order_id);


		// Get the selected payment method
		$payment_method = sanitize_text_field($_POST['fundiin_payment_method']);
		$this->update_payment_method($order, $payment_method);
		$payUrl = $this->fundiin_checkout($order, $payment_method);

		return array(
			'result' => 'success',
			'redirect' => $payUrl
		);
	}

	private function fundiin_checkout($order, $requestType)
	{
		$clientId = $this->clientId;
		$merchantId = $this->merchantId;
		$secretKey = $this->secretKey;

		$notifyUrl = ($this->notifyUrl !== '')
			? $this->notifyUrl
			: get_home_url() . '/wp-json/fundiin_payment_' . $clientId . '/notify';
		$returnUrl = $order->get_checkout_order_received_url();


		$amount = strval(round(WC()->cart->total));
		$now = round(microtime(true) * 1000);
		$orderId = $order->get_id();
		$order_data = $order->get_data();
		$orderInfo = "Thanh toán đơn hàng " . $this->merchantName;
		$items = WC()->cart->get_cart();
		$gwItems = [];
		foreach ($items as $item => $values) {
			$_product = wc_get_product($values['data']->get_id());
			$category = [];
			$terms = get_the_terms($_product->get_id(), 'product_cat');
			foreach ($terms as $term) {
				array_push($category, $term->name);
			}
			$categoryListString = $category . join(',');
			$gwItem = array(
				"productId" => $_product->get_id(),
				"productName" => $_product->get_name(),
				"description" => $_product->get_description(),
				"price" => $_product->get_sale_price(),
				"currency" => "VND",
				"quantity" => $values['quantity'],
				"totalAmount" => ($_product->get_regular_price() * $values['quantity']),
				"category" => $categoryListString,
			);
			array_push($gwItems, $gwItem);
		}
		$customer = array(
			"phoneNumber" => $order->get_billing_phone(),
			"email" => $order->get_billing_email(),
			"firstName" => $order->get_billing_first_name(),
			"lastName" => $order->get_billing_last_name(),
		);

		$shipping_instance = $GLOBALS['fdn_shipping'];
		$address_attr_obj = $shipping_instance->getData($orderId);
		$address_attr = isset($address_attr_obj["formatted"]) ? $address_attr_obj["formatted"] : [];

		$billing_address_obj = array();
		$shipping_address_obj = array();
		if (isset($address_attr["billing_city"])):
			$billing_address_obj = array(
				!empty($address_attr["billing_address"]) ? $address_attr["billing_address"] : '',
				!empty($address_attr["billing_ward"]) ? $address_attr["billing_ward"] : '',
				!empty($address_attr["billing_city"]) ? $address_attr["billing_city"] : '',
				!empty($address_attr["billing_state"]) ? $address_attr["billing_state"] : '',
			);
		endif;
		if (isset($address_attr["shipping_city"])):
			$shipping_address_obj = array(
				!empty($address_attr["shipping_address"]) ? $address_attr["shipping_address"] : '',
				!empty($address_attr["shipping_ward"]) ? $address_attr["shipping_ward"] : '',
				!empty($address_attr["shipping_city"]) ? $address_attr["shipping_city"] : '',
				!empty($address_attr["shipping_state"]) ? $address_attr["shipping_state"] : '',
			);
		endif;
		$billing_address_str = implode(", ", array_filter($billing_address_obj));
		$shipping_address_str = implode(", ", array_filter($shipping_address_obj));
		$shipping_address = "";

		if ($billing_address_str == $shipping_address_str || empty($shipping_address_str)):
			$shipping_address = $billing_address_str;
		elseif (!empty($billing_address_str) && !empty($shipping_address_str)):
			$shipping_address = $shipping_address_str;
		endif;

		if (empty($shipping_address)):
			$city = $order_data['shipping']['city'] . ", " . $order_data['shipping']['state'];
			$shipping_address = $order_data['shipping']['address_1'] . ", " . $city;
			if ($city == ", ") {
				$city = $order_data['billing']['city'] . ", " . $order_data['billing']['state'];
				$shipping_address = $order_data['billing']['address_1'] . ", " . $city;
			}
		endif;
		// Assuming $order is a WooCommerce order object
		$shipping = array(
			"city" => $order_data['billing']['city'],
			"zipCode" => $order->get_shipping_postcode(),
			"district" => $order->get_shipping_state(),
			"ward" => $order->get_shipping_address_2(),
			"street" => $shipping_address_str,
			"streetNumber" => $order->get_shipping_address_2(),
			"houseNumber" => "",
			// You may need to get this from custom order fields
			"houseExtension" => null,
			// You may need to get this from custom order fields
			"country" => $order->get_shipping_country()
		);
		try {
			$url = $this->get_fundiin_checkout_url();

			$data = array(
				"merchantId" => $merchantId,
				"requestType" => "installment",
				"successRedirectUrl" => $returnUrl,
				"unSuccessRedirectUrl" => $returnUrl,
				"notifyUrl" => $notifyUrl,
				"description" => $orderInfo,
				"paymentMethod" => "BNPL",
				"referenceId" => $orderId,
				"extraData" => $orderInfo,
				"amount" => array(
					"value" => $amount,
					"currency" => "VND"
				),
				"shipping" => $shipping,
				"customer" => $customer,
				"items" => $gwItems
			);
			$signature = bin2hex(hash_hmac("sha256", json_encode($data), $secretKey, true));

			$data_encode = json_encode($data);



			$response = wp_remote_post(
				$url,
				array(
					'headers' => array(
						'Content-Type' => 'application/json',
						'Signature' => $signature,
						'Client-Id' => $clientId
					),
					'timeout' => 10,
					'body' => $data_encode,
					'sslverify' => false
				)
			);
			if (is_wp_error($response)) {
				$error_message = $response->get_error_message();
				wc_add_notice(__($error_message, 'woocommerce-gateway-fundiin'), 'error');
			} else {
				$result = json_decode($response['body']);
				if ($result->resultCode != 0) {
					wc_add_notice(__($result->resultMsg, 'woocommerce-gateway-fundiin'), 'error');
					return null;
				}
				return $result->paymentUrl;
			}
			wc_add_notice('Yêu cầu không hợp lệ', 'error');
			return null;
		} catch (Exception $ex) {
			wc_add_notice($ex->getMessage(), 'error');
		}
	}

	// Perform refund for an order
	function process_refund($order_id, $amount = null, $reason = '')
	{
		// Get the order object
		$order = wc_get_order($order_id);

		$clientId = $this->clientId;
		$merchantId = $this->merchantId;
		$secretKey = $this->secretKey;
		$now = round(microtime(true) * 1000);
		$orderId = $clientId . '_REFUND-' . $now;
		$transId = $order->get_transaction_id();
		$requestId = strval($now);
		if (($transId === null) or (!isset($transId))) {

			$error = new WP_Error('transaction_not_found', 'Đơn hàng chưa được thanh toán nên không thể hoàn tiền.');
			return $error;


		}
		if (($amount === null) or (!isset($amount)) or ($order->get_total() != $amount)) {
			// echo 'fail';
			$error = new WP_Error('cannot_refund', 'Hoàn tiền thất bại. Bạn phải hoàn tiền toàn bộ đơn hàng.');

			return $error;


		}

		if ($order->get_status() != 'processing') {
			$error = new WP_Error('cannot_refund', 'Đơn hàng chưa được thanh toán nên không thể hoàn tiền.');
			// echo 'fail';
			return $error;

		}
		try {
			$url = $this->get_fundiin_refund_url();

			$data = array(
				"merchantId" => $merchantId,
				"referenceId" => $transId,
				"paymentTransId" => $transId,
				"lang" => "vi",
				"description" => $reason,
				"amount" => array(
					"value" => $amount,
					"currency" => "VND"
				)
			);
			$data_encode = json_encode($data);
			// echo $data_encode;
			$signature = hash_hmac("sha256", $data_encode, $secretKey);

			$response = wp_remote_post(
				$url,
				array(
					'headers' => array(
						'Content-Type' => 'application/json',
						'Signature' => $signature,
						'Client-Id' => $clientId
					),
					'timeout' => 10,
					'body' => $data_encode,
					'sslverify' => false
				)
			);
			if (is_wp_error($response)) {
				$error_message = $response->get_error_message();
				wc_add_notice(__($error_message, 'woocommerce-gateway-fundiin'), 'error');
				return false;
			} else {
				$result = json_decode($response['body']);
				if ($result->resultStatus != "APPROVED") {
					wc_add_notice(__($result->resultMsg, 'woocommerce-gateway-fundiin'), 'error');
					return false;
				}
				$order->add_order_note(
					sprintf(__('Hoàn tiền %s qua Fundiin.', 'your-plugin'), wc_price($amount))
				);
				$order->update_meta_data('fundiin_refund_orderId', $result->referenceId);
				$order->update_meta_data('fundiin_refund_transId', $result->refundTransId);

				// Save changes to the order
				$order->save();
				return true;
			}
		} catch (Exception $ex) {
			wc_add_notice($ex->getMessage(), 'error');
			return false;
		}
	}
}