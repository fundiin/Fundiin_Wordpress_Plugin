<?php

class Fundiin_Api
{
    public $logger, $helper;

    private $public_key = '-----BEGIN PUBLIC KEY-----
MIGeMA0GCSqGSIb3DQEBAQUAA4GMADCBiAKBgGWLeG5fXvtBj47I6cKlF85/ydNL
HfwZ6vVcr3nyBh0nkN5ePJamn7aTMvWF5Y6itodN92Z6oMqH/X/GBqMXx4c9S2JX
Z5t+TWmlWo8gnGVDLT43VdnYYPYj6rsG4a9IjuFxX7m3ZIymAc+KTDNwKP/fYXWN
YbjPP+CuaH7XNrg1AgMBAAE=
-----END PUBLIC KEY-----';

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
                    'permission_callback' => array($this, 'verify_signature'),
                )
            );

            // Route để lấy đơn hàng theo order_id
            register_rest_route(
                'merchant',
                '/(?P<merchant_id>[A-Za-z0-9_-]+)/api/orders/(?P<order_id>\d+)',
                array(
                    'methods' => 'POST',
                    'callback' => array($this, 'get_order_by_id'),
                    'permission_callback' => array($this, 'verify_signature'),
                )
            );

            register_rest_route(
                'merchant',
                '/(?P<merchant_id>[A-Za-z0-9_-]+)/api/users',
                array(
                    'methods' => 'POST',
                    'callback' => array($this, 'get_users'),
                    'permission_callback' => array($this, 'verify_signature'),
                )
            );

            register_rest_route(
                'merchant',
                '/(?P<merchant_id>[A-Za-z0-9_-]+)/api/products',
                array(
                    'methods' => 'POST',
                    'callback' => array($this, 'get_products'),
                    'permission_callback' => array($this, 'verify_signature'),
                )
            );

            register_rest_route(
                'merchant',
                '/(?P<merchant_id>[A-Za-z0-9_-]+)/api/products/(?P<product_id>\d+)', // Cho phép merchant_id là chuỗi
                array(
                    'methods' => 'POST',
                    'callback' => array($this, 'get_product_id'),
                    'permission_callback' => array($this, 'verify_signature'),
                )
            );
        });
    }

    public function get_orders(WP_REST_Request $request)
    {
        $data = $request->get_body();
        $data_array = json_decode($data, true);

        $limit = isset($data_array['limit']) ? $data_array['limit'] : -1;
        $order = isset($data_array['order']) ? $data_array['order'] : 'DESC';
        $status = isset($data_array['status']) ? $data_array['status'] : 'any';

        $args = array(
            'limit'    => $limit,
            'orderby'  => 'date',
            'order'    => $order,
            'status'   => $status,
        );

        if (isset($data_array['date_from'])) {
            // Kiểm tra xem giá trị có hợp lệ không, nếu không thì gán giá trị mặc định
            $date_after = $data_array['date_from'];
        } else {
            // Thiết lập giá trị mặc định cho date_after, ví dụ là 30 ngày trước
            $date_after = date('Y-m-d H:i:s', strtotime('-30 days'));
        }

        if (isset($data_array['date_to'])) {
            // Kiểm tra xem giá trị có hợp lệ không, nếu không thì gán giá trị mặc định
            $date_before = $data_array['date_to'];
        } else {
            // Thiết lập giá trị mặc định cho date_before, ví dụ là ngày hiện tại
            $date_before = date('Y-m-d H:i:s');
        }

        $args['date_after'] = $date_after;
        $args['date_before'] = $date_before;

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
        $data = $request->get_body();
        $data_array = json_decode($data, true);

        $limit = isset($data_array['limit']) ? $data_array['limit'] : -1;
        $order = isset($data_array['order']) ? $data_array['order'] : 'DESC';
        $date_from = isset($data_array['date_from']) ? $data_array['date_from'] : date('Y-m-d', strtotime('-30 days'));

        // date_to: Nếu không có, lấy ngày hôm nay
        $date_to = isset($data_array['date_to']) ? $data_array['date_to'] : date('Y-m-d');
        $query = "
        SELECT DISTINCT 
            pm_email.meta_value AS email, 
            pm_phone.meta_value AS phone,
            pm_first_name.meta_value AS first_name,
            pm_last_name.meta_value AS last_name,
            p.ID AS post_id
        FROM {$wpdb->prefix}postmeta pm_email
        LEFT JOIN {$wpdb->prefix}postmeta pm_phone ON pm_email.post_id = pm_phone.post_id AND pm_phone.meta_key = '_billing_phone'
        LEFT JOIN {$wpdb->prefix}postmeta pm_first_name ON pm_email.post_id = pm_first_name.post_id AND pm_first_name.meta_key = '_billing_first_name'
        LEFT JOIN {$wpdb->prefix}postmeta pm_last_name ON pm_email.post_id = pm_last_name.post_id AND pm_last_name.meta_key = '_billing_last_name'
        INNER JOIN {$wpdb->prefix}posts p ON pm_email.post_id = p.ID
        WHERE pm_email.meta_key = '_billing_email'
          AND p.post_type = 'shop_order'
    ";

        // Thêm điều kiện lọc theo ngày
        if ($date_from) {
            $query .= $wpdb->prepare(" AND p.post_date >= %s", $date_from);
        }
        if ($date_to) {
            $query .= $wpdb->prepare(" AND p.post_date <= %s", $date_to);
        }

        // Sắp xếp và giới hạn
        $query .= " ORDER BY p.post_date {$order}";
        if ($limit > 0) {
            $query .= $wpdb->prepare(" LIMIT %d", $limit);
        }

        $results = $wpdb->get_results($query);

        $user_data = [];
        foreach ($results as $row) {
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
        $data = $request->get_body();
        $data_array = json_decode($data, true);

        $limit = $data && $data_array['limit'] ? $data_array['limit'] : -1;
        $order = $data && $data_array['order'] ? $data_array['order'] : 'DESC';
        $orderby = $data && $data_array['$orderBy'] ? $data_array['$orderBy'] : 'date';

        $query = new WC_Product_Query(array(
            'limit' => $limit,
            'orderby' => $orderby,
            'order' => $order,
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

        $productId = $request->get_param('product_id');
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

// Hàm lấy danh sách đơn hàng theo email khách hàng
    private function get_orders_by_customer_email($email)
    {
        $orders = wc_get_orders(['billing_email' => $email]);
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

        $order_id = $request->get_param('order_id');
        $order = wc_get_order($order_id);

        if ($order) {
            return new WP_REST_Response(['status' => 'success', 'data' => format_order_data($order)], 200);
        }                                                                                                                               return new WP_REST_Response(array('message' => 'Order not found'), 404);
    }


    private function format_order_data($order)
    {
        return array(
            'order_id' => $order->get_id(),
            'order_key' => $order->get_order_key(),
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

    public function verify_signature(WP_REST_Request $request)
    {
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
                array('status' => 400)
            );
        }

        if (!$timeStamp) {
            return new WP_Error(
                'INVALID_TIMESTAMP',
                $timeStamp . ' Dữ liệu không hợp lệ',
                array('status' => 400)
            );
        }

        $is_valid = $this->verify_rsa_signature($data, $signature, $public_key);
        if (!$is_valid) {
            return new WP_Error('INVALID_SIGNATURE', 'Chữ ký không hợp lệ', ['signature' => $signature, 'data' => $data, 'status' => 401]);
        }

        $date = new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
        $milli_seconds = (int)$date->format('u') / 1000;
        $timestamp_seconds = $date->getTimestamp();
        $current_time_millis = $timestamp_seconds * 1000 + $milli_seconds;

        if ($timeStamp < $current_time_millis) {
            return new WP_Error(
                'INVALID_SIGNATURE',
                'Chữ ký không hợp lệ',
                array('status' => 400)
            );
        }

        return true;
    }

    private function verify_rsa_signature($data, $signature, $public_key)
    {
        $is_valid = openssl_verify($data, base64_decode($signature), $public_key, OPENSSL_ALGO_SHA256);
        return $is_valid === 1;
    }
}