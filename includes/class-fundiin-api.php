<?php

if (!defined('ABSPATH')) {
    exit;
}

class Fundiin_Api
{
    public $logger, $helper;

    public function __construct()
    {
        add_action('rest_api_init', function () {
            // Route để lấy danh sách các đơn hàng
            register_rest_route(
                'merchant',
                '/(?P<merchant_id>[A-Za-z0-9_-]+)/api/orders',
                array(
                    'methods' => 'POST',
                    'callback' => array($this, 'get_orders'),
                    'permission_callback' => array($this, 'authorize_request'),
                )
            );

            // Route để lấy đơn hàng theo order_id
            register_rest_route(
                'merchant',
                '/(?P<merchant_id>[A-Za-z0-9_-]+)/api/orders/(?P<order_id>\d+)',
                array(
                    'methods' => 'POST',
                    'callback' => array($this, 'get_order_by_id'),
                    'permission_callback' => array($this, 'authorize_request'),
                )
            );

            register_rest_route(
                'merchant',
                '/(?P<merchant_id>[A-Za-z0-9_-]+)/api/users',
                array(
                    'methods' => 'POST',
                    'callback' => array($this, 'get_users'),
                    'permission_callback' => array($this, 'authorize_request'),
                )
            );

            register_rest_route(
                'merchant',
                '/(?P<merchant_id>[A-Za-z0-9_-]+)/api/products',
                array(
                    'methods' => 'POST',
                    'callback' => array($this, 'get_products'),
                    'permission_callback' => array($this, 'authorize_request'),
                )
            );

            register_rest_route(
                'merchant',
                '/(?P<merchant_id>[A-Za-z0-9_-]+)/api/products/(?P<product_id>\d+)', // Cho phép merchant_id là chuỗi
                array(
                    'methods' => 'POST',
                    'callback' => array($this, 'get_product_id'),
                    'permission_callback' => array($this, 'authorize_request'),
                )
            );
        });
    }

    public function get_orders(WP_REST_Request $request)
    {
        $data_array = $this->query_options($request);
        if (is_wp_error($data_array)) {
            return $data_array;
        }
        $args = array(
            'limit' => $data_array['limit'],
            'offset' => $data_array['offset'],
            'orderby' => 'date',
            'order' => $data_array['order'],
            'status' => $data_array['status'],
            'date_created' => strtotime($data_array['date_from']) . '...' . strtotime($data_array['date_to']),
        );

        // Lấy danh sách đơn hàng
        $orders = wc_get_orders($args);
        $order_data = array();

        foreach ($orders as $order) {
            $order_data[] = $this->format_order_data($order);
        }

        return new WP_REST_Response(['status' => 'success', 'data' => $order_data], 200);
    }





    // Hàm chính để lấy thông tin khách hàng và đơn hàng
    public function get_users(WP_REST_Request $request)
    {
        global $wpdb;
        $data_array = $this->query_options($request);
        if (is_wp_error($data_array)) {
            return $data_array;
        }
        $limit = $data_array['limit'];
        $order = $data_array['order'];
        $date_from = $data_array['date_from'];
        $date_to = $data_array['date_to'];
        $order_sql = $order === 'ASC' ? 'ASC' : 'DESC';
        $query = "
        SELECT 
            pm_email.meta_value AS email, 
            MAX(pm_phone.meta_value) AS phone,
            MAX(pm_first_name.meta_value) AS first_name,
            MAX(pm_last_name.meta_value) AS last_name,
            MAX(p.ID) AS post_id
        FROM {$wpdb->prefix}postmeta pm_email
        LEFT JOIN {$wpdb->prefix}postmeta pm_phone ON pm_email.post_id = pm_phone.post_id AND pm_phone.meta_key = '_billing_phone'
        LEFT JOIN {$wpdb->prefix}postmeta pm_first_name ON pm_email.post_id = pm_first_name.post_id AND pm_first_name.meta_key = '_billing_first_name'
        LEFT JOIN {$wpdb->prefix}postmeta pm_last_name ON pm_email.post_id = pm_last_name.post_id AND pm_last_name.meta_key = '_billing_last_name'
        INNER JOIN {$wpdb->prefix}posts p ON pm_email.post_id = p.ID
        WHERE pm_email.meta_key = '_billing_email'
          AND pm_email.meta_value <> ''
          AND p.post_type = 'shop_order'
    ";

        // Thêm điều kiện lọc theo ngày
        if ($date_from) {
            $query .= $wpdb->prepare(" AND p.post_date >= %s", $date_from);
        }
        if ($date_to) {
            $query .= $wpdb->prepare(" AND p.post_date <= %s", $date_to);
        }

        // Nhóm theo email để tránh truy vấn lặp N+1 cho cùng một khách hàng
        $query .= " GROUP BY pm_email.meta_value ORDER BY MAX(p.post_date) {$order_sql}";
        if ($limit > 0) {
            $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $limit, $data_array['offset']);
        }

        $results = $wpdb->get_results($query);

        $user_data = [];
        $seen_emails = [];
        foreach ($results as $row) {
            if (!isset($row->email) || !is_string($row->email) || $row->email === '' || isset($seen_emails[$row->email])) {
                continue;
            }
            $seen_emails[$row->email] = true;
            $contact_info = $row->phone ?: $row->email; // Ưu tiên số điện thoại, nếu không có thì dùng email
            $user = get_user_by('email', $row->email);

            if ($user) {
                // Khách hàng đã đăng ký
                $customer = new WC_Customer($user->ID);
                $user_data[] = $this->format_customer_data($customer);
            } else {
                // Khách hàng vãng lai
                $orders = $this->get_orders_by_customer_email($row->email);

                $user_data[] = [
                    'id' => null,
                    'first_name' => $row->first_name ?: '',
                    'last_name' => $row->last_name ?: '',
                    'email' => $row->email,
                    'phone' => $row->phone,
                    'orders' => $orders,
                ];
            }
        }

        return new WP_REST_Response(['status' => 'success', 'data' => $user_data], 200);
    }


    public function get_products(WP_REST_Request $request)
    {
        $data_array = $this->query_options($request);
        if (is_wp_error($data_array)) {
            return $data_array;
        }
        $query = new WC_Product_Query(array(
            'limit' => $data_array['limit'],
            'offset' => $data_array['offset'],
            'orderby' => 'date',
            'order' => $data_array['order'],
            'return' => 'objects',
        ));

        $products = $query->get_products();
        $data = [];

        // Xử lý dữ liệu sản phẩm
        foreach ($products as $product) {
            $data[] = [
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

        return new WP_REST_Response(['status' => 'success', 'data' => $data], 200);
    }

    public function get_product_id(WP_REST_Request $request)
    {
        $data = $request->get_body();
        $data_array = json_decode($data, true);

        $params = $request->get_url_params();
        $productId = isset($params['product_id']) ? (int) $params['product_id'] : 0;
        $product = wc_get_product($productId); // Lấy sản phẩm dựa trên ID

        if ($product) {
            $data = [
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

            return new WP_REST_Response(['status' => 'success', 'data' => $data], 200);
        }

        return new WP_REST_Response(array('message' => 'Product not found'), 404);
    }


    private function format_customer_data($customer)
    {
        $orders = $this->get_orders_by_customer_id($customer->get_id());

        return [
            'id' => $customer->get_id(),
            'first_name' => $customer->get_first_name(),
            'last_name' => $customer->get_last_name(),
            'email' => $customer->get_email(),
            'phone' => $customer->get_billing_phone(),
            'orders' => $orders,
        ];
    }

    private function get_orders_by_customer_id($customer_id)
    {
        return $this->format_orders(wc_get_orders(array('customer_id' => $customer_id, 'limit' => 100)));
    }

// Hàm lấy danh sách đơn hàng theo email khách hàng
    private function get_orders_by_customer_email($email)
    {
        $orders = wc_get_orders(['billing_email' => $email, 'limit' => 100]);
        return $this->format_orders($orders);
    }

// Hàm định dạng thông tin đơn hàng
    private function format_orders($orders)
    {
        $formatted_orders = [];
        foreach ($orders as $order) {
            $formatted_orders[] = [
                'id' => $order->get_id(),
                'status' => $order->get_status(),
                'payment_method' => $order->get_payment_method() ? $order->get_payment_method() : 'COD',
                'payment_method_title' => $order->get_payment_method_title() ? $order->get_payment_method_title() : 'COD',
                'total' => $order->get_total(),
                'currency' => $order->get_currency(),
                'date_created' => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : null,
                'items' => $this->format_order_items($order->get_items()),
            ];
        }
        return $formatted_orders;
    }


    private function format_order_items($items)
    {
        $formatted_items = [];
        foreach ($items as $item) {
            $formatted_items[] = [
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total(),
                'product_id' => $item->get_product_id(),
            ];
        }
        return $formatted_items;
    }


    public function get_order_by_id(WP_REST_Request $request)
    {

        $params = $request->get_url_params();
        $order_id = isset($params['order_id']) ? (int) $params['order_id'] : 0;
        $order = wc_get_order($order_id);

        if ($order) {
            return new WP_REST_Response(['status' => 'success', 'data' => $this->format_order_data($order)], 200);
        }
        return new WP_REST_Response(array('message' => 'Order not found'), 404);
    }


    private function format_order_data($order)
    {
        return array(
            'order_id' => $order->get_id(),
            'status' => $order->get_status(),
            'date_created' => $order->get_date_created()->date('Y-m-d H:i:s'),
            'total' => $order->get_total(),
            'payment_method' => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title(),
            'transaction_id' => $order->get_transaction_id(),
            'billing_first_name' => $order->get_billing_first_name(),
            'billing_last_name' => $order->get_billing_last_name(),
            'billing_email' => $order->get_billing_email(),
            'billing_phone' => $order->get_billing_phone(),
            'billing_address_1' => $order->get_billing_address_1(),
            'billing_address_2' => $order->get_billing_address_2(),
            'billing_city' => $order->get_billing_city(),
            'billing_postcode' => $order->get_billing_postcode(),
            'billing_country' => $order->get_billing_country(),
            'billing_state' => $order->get_billing_state(),
            'shipping_first_name' => $order->get_shipping_first_name(),
            'shipping_last_name' => $order->get_shipping_last_name(),
            'shipping_address_1' => $order->get_shipping_address_1(),
            'shipping_address_2' => $order->get_shipping_address_2(),
            'shipping_city' => $order->get_shipping_city(),
            'shipping_postcode' => $order->get_shipping_postcode(),
            'shipping_country' => $order->get_shipping_country(),
            'shipping_state' => $order->get_shipping_state(),
            'items' => $this->get_order_items($order),
        );
    }

    private function get_order_items($order)
    {
        $items = array();
        foreach ($order->get_items() as $item) {
            $items[] = array(
                'product_id' => $item->get_product_id(),
                'product_name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total(),
                'price' => $item->get_subtotal(),
            );
        }
        return $items;
    }

    /** Use WordPress authentication and store-specific capabilities, never a shared credential. */
    public function authorize_request(WP_REST_Request $request)
    {
        if (!current_user_can('manage_woocommerce')) {
            return new WP_Error('fundiin_forbidden', 'WooCommerce management permission required.', array('status' => 403));
        }
        $params = $request->get_url_params();
        $merchant = fundiin()->fundiin->merchantId;
        if (!is_string($merchant) || $merchant === '' || !isset($params['merchant_id']) ||
            !is_string($params['merchant_id']) || !hash_equals($merchant, $params['merchant_id'])) {
            return new WP_Error('fundiin_invalid_merchant', 'Invalid merchant.', array('status' => 403));
        }
        return true;
    }

    /** Validate all values before using them in SQL or WooCommerce queries. */
    private function query_options(WP_REST_Request $request)
    {
        $body = $request->get_body();
        $data = $body === '' ? array() : json_decode($body, true);
        if (!is_array($data)) {
            return new WP_Error('fundiin_invalid_query', 'Expected a JSON object.', array('status' => 400));
        }
        $data = array_merge(array('limit' => 50, 'offset' => 0, 'order' => 'DESC', 'status' => 'any',
            'date_from' => date('Y-m-d H:i:s', strtotime('-30 days')), 'date_to' => date('Y-m-d H:i:s')), $data);
        $limit = filter_var($data['limit'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 100)));
        $offset = filter_var($data['offset'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 0, 'max_range' => 1000000)));
        if ($limit === false || $offset === false || !in_array($data['order'], array('ASC', 'DESC'), true) ||
            !is_string($data['status']) || ($data['status'] !== 'any' &&
                !in_array('wc-' . preg_replace('/^wc-/', '', $data['status']), array_keys(wc_get_order_statuses()), true))) {
            return new WP_Error('fundiin_invalid_query', 'Invalid limit, offset, order or status.', array('status' => 400));
        }
        foreach (array('date_from', 'date_to') as $field) {
            if (!is_string($data[$field]) || !preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}( [0-9]{2}:[0-9]{2}:[0-9]{2})?\z/', $data[$field]) ||
                strtotime($data[$field]) === false) {
                return new WP_Error('fundiin_invalid_query', 'Invalid date range.', array('status' => 400));
            }
        }
        if (strtotime($data['date_from']) > strtotime($data['date_to'])) {
            return new WP_Error('fundiin_invalid_query', 'Invalid date range.', array('status' => 400));
        }
        $data['limit'] = $limit;
        $data['offset'] = $offset;
        return $data;
    }
}
