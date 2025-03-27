<?php
class Fundiin_Api
{
    public $logger, $helper;

    private $public_key = '
        -----BEGIN PUBLIC KEY-----
        MIGeMA0GCSqGSIb3DQEBAQUAA4GMADCBiAKBgGWLeG5fXvtBj47I6cKlF85/ydNL
        HfwZ6vVcr3nyBh0nkN5ePJamn7aTMvWF5Y6itodN92Z6oMqH/X/GBqMXx4c9S2JX
        Z5t+TWmlWo8gnGVDLT43VdnYYPYj6rsG4a9IjuFxX7m3ZIymAc+KTDNwKP/fYXWN
        YbjPP+CuaH7XNrg1AgMBAAE=
        -----END PUBLIC KEY-----
    ';

    public function __construct() {
        add_action('rest_api_init', function () {
            // Route for getting orders with pagination
            register_rest_route(
                'merchant',
                '/(?P<merchant_id>[A-Za-z0-9_-]+)/api/orders',
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'get_orders'],
                    'permission_callback' => [$this, 'verify_signature'],
                    'args' => $this->get_collection_params(),
                ]
            );

            // Route for getting order detail by using order_id
            register_rest_route(
                'merchant',
                '/(?P<merchant_id>[A-Za-z0-9_-]+)/api/orders/(?P<order_id>\d+)',
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'get_order_by_id'],
                    'permission_callback' => [$this, 'verify_signature'],
                ]
            );

            // Route for getting products with pagination
            register_rest_route(
                'merchant',
                '/(?P<merchant_id>[A-Za-z0-9_-]+)/api/products',
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'get_products'],
                    'permission_callback' => [$this, 'verify_signature'],
                    'args' => $this->get_collection_params(),
                ]
            );

            // Route for getting product detail by using product_id
            register_rest_route(
                'merchant',
                '/(?P<merchant_id>[A-Za-z0-9_-]+)/api/products/(?P<product_id>\d+)',
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'get_product_by_id'],
                    'permission_callback' => [$this, 'verify_signature'],
                ]
            );
        });
    }

    public function get_orders(WP_REST_Request $request) {
        $request_params = $request->get_params();
        $args = [
            'page' => $request_params['page'],
            'limit' => $request_params['limit'],
            'order' => $request_params['order'],
            'orderby' => $request_params['orderby'],
            'paginate' => true,
        ];

        $query = new WC_Order_Query($args);
        $result = $query->get_orders();
        $order_data = [];

        foreach ($result->orders as $order) {
            $order_data[] = $this->format_order_data($order);
        }

        return new WP_REST_Response(['status' => 'success', 'data' => [
            "orders" => $order_data,
            "total_items" => $result->total,
            "total_pages" => $result->max_num_pages,
        ]], 200);
    }

    public function get_products(WP_REST_Request $request) {
        $request_params = $request->get_params();
        $args = [
            'page' => $request_params['page'],
            'limit' => $request_params['limit'],
            'order' => $request_params['order'],
            'orderby' => $request_params['orderby'],
            'paginate' => true,
        ];

        $result = wc_get_products($args);
        $product_data = [];

        foreach ($result->products as $product) {
            $product_data[] = $this->format_product_data($product);
        }

        return new WP_REST_Response(['status' => 'success', 'data' => [
            "products" => $product_data,
            "total_items" => $result->total,
            "total_pages" => $result->max_num_pages,
        ]], 200);
    }

    public function get_order_by_id(WP_REST_Request $request) {
        $order_id = $request->get_param('order_id');
        $order = wc_get_order($order_id);

        if (!$order) {
            return new WP_REST_Response(['message' => 'Order not found'], 404);
        }

        return new WP_REST_Response(['status' => 'success', 'data' => $this->format_order_data($order)], 200);
    }

    public function get_product_by_id(WP_REST_Request $request) {
        $productId = $request->get_param('product_id');
        $product = wc_get_product($productId);

        if (!$product) {
            return new WP_REST_Response(['message' => 'Product not found'], 404);
        }

        return new WP_REST_Response(['status' => 'success', 'data' => $this->format_product_data($product)], 200);
    }

    public function verify_signature(WP_REST_Request $request) {
        $fundiin = fundiin()->fundiin;
        $merchantIdSetting = $fundiin->merchantId;
        $data = $request->get_body();

        $data_array = json_decode($data, true);
        $public_key = $this->public_key;
        $merchantId = $request->get_param('merchant_id');
        $signature = $request->get_header('signature');
        $timeStamp = (int) $data_array['timestamp'];

        if (trim((string)$merchantId) !== trim((string)$merchantIdSetting)) {
            return new WP_Error(
                'INVALID_MERCHANT_ID',
                'The merchant is not registered.',
                ['status' => 400]
            );
        }

        if (!$timeStamp) {
            return new WP_Error(
                'INVALID_TIMESTAMP',
                $timeStamp . ' is invalid.',
                ['status' => 400]
            );
        }

        $is_valid = $this->verify_rsa_signature($data, $signature, $public_key);
        if (!$is_valid) {
            return new WP_Error(
                'INVALID_SIGNATURE',
                'The signature is invalid.',
                ['signature' => $signature, 'data' => $data, 'status' => 401]
            );
        }

        $date = new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
        $milli_seconds = (int)$date->format('u') / 1000;
        $timestamp_seconds = $date->getTimestamp();
        $current_time_millis = $timestamp_seconds * 1000 + $milli_seconds;

        if ($timeStamp < $current_time_millis) {
            return new WP_Error(
                'EXPIRED_SIGNATURE',
                'The signature is expired.',
                ['status' => 401]
            );
        }

        return true;
    }

    public function get_collection_params() {
        $params = [];

        $params['page'] = [
			'type'              => 'integer',
			'default'           => 1,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
			'minimum'           => 1,
		];

		$params['limit'] = [
			'type'              => 'integer',
			'default'           => 10,
			'minimum'           => 0,
			'maximum'           => 100,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		];

		$params['order'] = [
			'type'              => 'string',
			'default'           => 'desc',
			'enum'              => ['asc', 'desc'],
			'validate_callback' => 'rest_validate_request_arg',
		];

		$params['orderby'] = [
			'type'              => 'string',
			'default'           => 'date',
			'enum'              => ['date'],
			'validate_callback' => 'rest_validate_request_arg',
		];

        return $params;
    }

    private function verify_rsa_signature($data, $signature, $public_key) {
        $is_valid = openssl_verify($data, base64_decode($signature), $public_key, OPENSSL_ALGO_SHA256);
        return $is_valid === 1;
    }

    private function format_order_data($order) {
        $base_data = $order->get_base_data();

        return [
            'ref_id' => $base_data['id'] . '_' . $base_data['date_created']->format('U'),
            'order_id' => $base_data['id'],
            'status' => $base_data['status'],
            'currency' => $base_data['currency'],
            'order_key' => $base_data['order_key'],
            'prices_include_tax' => $base_data['prices_include_tax'],
            'date_created' => $base_data['date_created']->date('Y-m-d H:i:s'),
            'date_modified' => $base_data['date_modified']->date('Y-m-d H:i:s'),
            'discount' => [
                'total' => $base_data['discount_total'],
                'tax' => $base_data['discount_tax'],
            ],
            'shipping_price' => [
                'total' => $base_data['shipping_total'],
                'tax' => $base_data['shipping_tax']
            ],
            'cart_tax' => $base_data['cart_tax'],
            'total' => $base_data['total'],
            'total_tax' => $base_data['total_tax'],
            'customer_info' => [
                'id' => $base_data['customer_id'],
                'ip_address' => $base_data['customer_ip_address'],
                'user_agent' => $base_data['customer_user_agent'],
            ],
            'billing_info' => $base_data['billing'],
            'shipping_info' => $base_data['shipping'],
            'payment_info' => [
                'method' => $base_data['payment_method'],
                'title' => $base_data['payment_method_title'],
            ],
            'transaction_id' => $base_data['transaction_id'],
            'created_via' => $base_data['created_via'],
            'items' => $this->get_order_items($order),
        ];
    }

    private function get_order_items($order) {
        $items = [];
        foreach ($order->get_items() as $item) {
            $items[] = [
                'product_id' => $item->get_product_id(),
                'product_name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total(),
                'price' => $item->get_subtotal(),
            ];
        }
        return $items;
    }

    private function format_product_data($product) {
        return [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'price' => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'sku' => $product->get_sku(),
            'stock_status' => $product->get_stock_status(),
            'categories' => $product->get_category_ids(),
            'image' => wp_get_attachment_url($product->get_image_id()),
        ];
    }
}
