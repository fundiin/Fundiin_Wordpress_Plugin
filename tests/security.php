<?php
/** Standalone regression tests: php tests/security.php */
define('ABSPATH', __DIR__);
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

class WP_Error {
    public $code, $message, $data;
    public function __construct($code, $message, $data = array()) { $this->code = $code; $this->message = $message; $this->data = $data; }
}
class WP_REST_Response {
    public $data, $status;
    public function __construct($data, $status) { $this->data = $data; $this->status = $status; }
}
class WP_REST_Request {
    private $body, $headers, $params;
    public function __construct($body = '{}', $headers = array(), $params = array('merchant_id' => 'merchant-test')) {
        $this->body = $body; $this->headers = $headers; $this->params = $params;
    }
    public function get_body() { return $this->body; }
    public function get_header($name) { return isset($this->headers[$name]) ? $this->headers[$name] : null; }
    public function get_url_params() { return $this->params; }
}
class WC_Payment_Gateway {}
class WC_Logger {}
class TestOrder {
    public $status = 'pending', $method = 'fundiin', $total = '100000', $currency = 'VND', $transaction = '';
    public $meta = array('_fundiin_reference_id' => '42_1792728000000'), $notes = array(), $payments = 0;
    public function get_id() { return 42; }
    public function get_payment_method() { return $this->method; }
    public function get_currency() { return $this->currency; }
    public function get_total() { return $this->total; }
    public function get_transaction_id() { return $this->transaction; }
    public function get_meta($key) { return isset($this->meta[$key]) ? $this->meta[$key] : ''; }
    public function update_meta_data($key, $value) { $this->meta[$key] = $value; }
    public function has_status($statuses) { return in_array($this->status, $statuses, true); }
    public function is_paid() { return $this->has_status(array('processing', 'completed')); }
    public function payment_complete($transaction) { $this->payments++; $this->transaction = $transaction; $this->status = 'completed'; }
    public function add_order_note($note) { $this->notes[] = $note; }
    public function update_status($status, $note) { $this->status = $status; $this->notes[] = $note; }
    public function save() {}
    public function get_checkout_order_received_url() { return 'https://shop.example/order/42'; }
    public function get_view_order_url() { return 'https://shop.example/account/order/42'; }
    public function get_items() { return array(); }
    public function __call($name, $args) {
        if (strpos($name, 'get_shipping_') === 0 || strpos($name, 'get_billing_') === 0) { return ''; }
        throw new RuntimeException('Unexpected order method: ' . $name);
    }
}

$GLOBALS['allowed'] = false;
$GLOBALS['routes'] = array();
$GLOBALS['queries'] = array();
$GLOBALS['http'] = array();
$GLOBALS['logs'] = array();
$GLOBALS['option_values'] = array('merchantId' => 'merchant-test', 'debug' => 'no');
function current_user_can($cap) { return $cap === 'manage_woocommerce' && $GLOBALS['allowed']; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function add_action($hook, $callback, $priority = 10) { if ($hook === 'rest_api_init') { $callback(); } }
function register_rest_route($namespace, $route, $config) { $GLOBALS['routes'][$namespace . '/' . $route] = $config; }
function get_option($name, $default = array()) { return $GLOBALS['option_values']; }
function update_option($name, $value) { $GLOBALS['option_values'] = $value; return true; }
function wc_get_order($id) { return $id === 42 ? $GLOBALS['order'] : false; }
function wc_get_orders($args) { $GLOBALS['queries'][] = $args; return array(); }
function wc_get_order_statuses() { return array('wc-pending' => 'Pending', 'wc-processing' => 'Processing'); }
function wc_format_decimal($value, $dp = false) { return $dp === false ? (string) $value : number_format((float) $value, $dp, '.', ''); }
function sanitize_text_field($value) { return strip_tags($value); }
function wp_json_encode($value) { return json_encode($value); }
function esc_url($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function wp_parse_url($value) { return parse_url($value); }
function wc_add_notice($message, $type) {}
function __($message, $domain) { return $message; }
function wc_price($amount) { return (string) $amount; }
function get_home_url() { return 'https://shop.example'; }
function wc_get_logger() { return new class { public function debug($message, $context) { $GLOBALS['logs'][] = $message; } }; }
function wp_remote_post($url, $args) {
    $GLOBALS['http'][] = array('url' => $url, 'args' => $args);
    if (is_wp_error($GLOBALS['http_response'])) { return $GLOBALS['http_response']; }
    $response = $GLOBALS['http_response'];
    if (isset($response['echo_reference'])) {
        $response['body'] = json_encode(array('resultStatus' => 'APPROVED', 'referenceId' => json_decode($args['body'], true)['referenceId'], 'refundTransId' => 'refund-test'));
    }
    return $response;
}
function wp_remote_retrieve_body($response) { return $response['body']; }
function wp_remote_retrieve_response_code($response) { return $response['response']['code']; }
function fundiin() { return $GLOBALS['plugin']; }

require __DIR__ . '/../includes/class-fundiin-plugin.php';
require __DIR__ . '/../includes/class-fundiin-settings.php';
require __DIR__ . '/../includes/class-fundiin-logger.php';
require __DIR__ . '/../includes/class-fundiin-response.php';
require __DIR__ . '/../includes/class-fundiin-api.php';
require __DIR__ . '/../includes/class-fundiin-visibility.php';
require __DIR__ . '/../includes/abstracts/abstract-fundiin.php';
require __DIR__ . '/../includes/class-fundiin-with-aio.php';

$gateway = (new ReflectionClass('Fundiin'))->newInstanceWithoutConstructor();
$gateway->id = 'fundiin';
$gateway->clientId = 'client-test';
$gateway->merchantId = 'merchant-test';
$gateway->secretKey = 'test-only-secret';
$gateway->storeId = '';
$gateway->notifyUrl = '';
$gateway->merchantName = 'Test store';
$settings = new Fundiin_Settings();
$GLOBALS['plugin'] = (new ReflectionClass('Fundiin_Plugin'))->newInstanceWithoutConstructor();
$GLOBALS['plugin']->fundiin = $gateway;
$GLOBALS['plugin']->settings = $settings;
$GLOBALS['order'] = new TestOrder();
$api = new Fundiin_Api();
$response = new Fundiin_Response();
$passed = 0;
function check($condition, $message) {
    if (!$condition) { throw new RuntimeException('FAILED: ' . $message); }
    $GLOBALS['passed']++;
}
function rejected($result, $code) { return $result instanceof WP_Error && $result->code === $code; }
function signed_request($data, $secret = 'test-only-secret', $flags = 0) {
    $body = json_encode($data, $flags);
    return new WP_REST_Request($body, array('signature' => hash_hmac('sha256', $body, $secret)));
}
function notification($changes = array()) {
    return array_merge(array('merchantId' => 'merchant-test', 'referenceId' => '42_1792728000000',
        'amount' => array('value' => 100000, 'currency' => 'VND'), 'paymentTransId' => 'transaction-test',
        'notificationType' => 'PAYMENT_STATUS', 'paymentStatus' => 'SUCCESS'), $changes);
}

// Every data export is protected; shared/forged signature headers grant no privileges.
check(count($GLOBALS['routes']) === 6, 'all expected REST routes registered');
foreach ($GLOBALS['routes'] as $route => $config) {
    check(is_callable($config['permission_callback']), 'callable permission callback: ' . $route);
    if (strpos($route, 'merchant/') === 0) {
        check(rejected(call_user_func($config['permission_callback'], new WP_REST_Request('{}', array('signature' => 'legacy-shared-credential'))), 'fundiin_forbidden'), 'anonymous export blocked');
    }
}
$GLOBALS['allowed'] = true;
check($api->authorize_request(new WP_REST_Request()) === true, 'authorized merchant allowed');
check(rejected($api->authorize_request(new WP_REST_Request('{}', array(), array('merchant_id' => 'other-store'))), 'fundiin_invalid_merchant'), 'cross-merchant access blocked');
$gateway->merchantId = '';
check(rejected($api->authorize_request(new WP_REST_Request()), 'fundiin_invalid_merchant'), 'unconfigured merchant blocked');
$gateway->merchantId = 'merchant-test';

// SQL injection and resource exhaustion are rejected before a query is executed.
foreach (array(array('order' => 'DESC, (SELECT SLEEP(5))'), array('order' => array('DESC')), array('limit' => -1),
    array('limit' => 101), array('limit' => false), array('offset' => -1), array('status' => array()),
    array('date_from' => "2026-01-01' OR 1=1 --"), array('date_from' => array()),
    array('date_from' => '2026-12-01', 'date_to' => '2026-01-01')) as $payload) {
    check(rejected($api->get_orders(new WP_REST_Request(json_encode($payload))), 'fundiin_invalid_query'), 'invalid query rejected');
}
check(rejected($api->get_orders(new WP_REST_Request('{')), 'fundiin_invalid_query'), 'malformed query rejected');
check(count($GLOBALS['queries']) === 0, 'invalid inputs never reach queries');
$api->get_orders(new WP_REST_Request('{"limit":10,"offset":5,"order":"ASC"}'));
check($GLOBALS['queries'][0]['limit'] === 10 && $GLOBALS['queries'][0]['offset'] === 5, 'validated pagination reaches WooCommerce');
check($api->get_order_by_id(new WP_REST_Request('{}', array(), array('order_id' => 999)))->status === 404, 'missing order handled');

// Webhook authentication and payload/order binding.
check(rejected($response->check_fundiin_notify(new WP_REST_Request()), 'fundiin_invalid_signature'), 'unsigned webhook blocked');
check(rejected($response->check_fundiin_notify(signed_request(notification(), 'attacker-secret')), 'fundiin_invalid_signature'), 'wrong key blocked');
check(rejected($response->check_fundiin_notify(signed_request(notification(array('merchantId' => 'other-store')))), 'fundiin_invalid_signature'), 'wrong merchant blocked');
$gateway->secretKey = '';
check(rejected($response->check_fundiin_notify(signed_request(notification(), '')), 'fundiin_invalid_signature'), 'empty configured key blocked');
$gateway->secretKey = 'test-only-secret';
check($response->verify_notification(signed_request(notification(), 'test-only-secret', JSON_PRETTY_PRINT)) === true, 'raw JSON whitespace signature accepted');
$pretty = json_encode(notification(), JSON_PRETTY_PRINT);
check($response->verify_notification(new WP_REST_Request($pretty, array('signature' => hash_hmac('sha256', json_encode(notification()), 'test-only-secret')))) === true, 'legacy normalized signature accepted');
foreach (array(array('referenceId' => array()), array('referenceId' => '42_bad'), array('notificationType' => 'OTHER'),
    array('paymentStatus' => 'UNKNOWN'), array('amount' => array('value' => array(), 'currency' => 'VND')),
    array('amount' => array('value' => -1, 'currency' => 'VND')), array('paymentTransId' => '')) as $payload) {
    check(rejected($response->check_fundiin_notify(signed_request(notification($payload))), 'fundiin_invalid_notification'), 'invalid signed payload blocked');
}
check(rejected($response->check_fundiin_notify(signed_request(notification(array('amount' => array('value' => 1, 'currency' => 'VND'))))), 'fundiin_payment_mismatch'), 'underpayment blocked');
check(rejected($response->check_fundiin_notify(signed_request(notification(array('amount' => array('value' => 100000, 'currency' => 'USD'))))), 'fundiin_payment_mismatch'), 'wrong currency blocked');
$GLOBALS['order']->method = 'cod';
check(rejected($response->check_fundiin_notify(signed_request(notification())), 'fundiin_invalid_order'), 'different gateway blocked');
$GLOBALS['order']->method = 'fundiin';
$GLOBALS['order']->meta['_fundiin_reference_id'] = '';
check(rejected($response->check_fundiin_notify(signed_request(notification())), 'fundiin_payment_mismatch'), 'missing persisted reference blocked');
$GLOBALS['order']->meta['_fundiin_reference_id'] = '42_1792728000001';
check(rejected($response->check_fundiin_notify(signed_request(notification())), 'fundiin_payment_mismatch'), 'stale payment attempt blocked');
$GLOBALS['order']->meta['_fundiin_reference_id'] = '42_1792728000000';
$locks_prop = new ReflectionProperty('Fundiin_Response', 'active_locks');
if (PHP_VERSION_ID < 80100) { $locks_prop->setAccessible(true); }
$locks_prop->setValue(null, array(42 => true));
check(rejected($response->check_fundiin_notify(signed_request(notification())), 'fundiin_notification_busy'), 'concurrent notification lock enforced');
$locks_prop->setValue(null, array());
check($response->check_fundiin_notify(signed_request(notification(array('paymentStatus' => 'PENDING'))))->status === 204 && $GLOBALS['order']->status === 'pending', 'pending event leaves order unpaid');
check($GLOBALS['order']->payments === 0, 'rejected events never complete payment');
check($response->check_fundiin_notify(signed_request(notification(array('paymentTransId' => '<b>tx-1</b>'))))->status === 204 && $GLOBALS['order']->status === 'completed', 'valid payment preserves WooCommerce completed status');
check($GLOBALS['order']->meta['fundiin_transId'] === 'tx-1' && $GLOBALS['order']->transaction === 'tx-1', 'transaction meta sanitized before storage');
$response->check_fundiin_notify(signed_request(notification()));
$response->check_fundiin_notify(signed_request(notification(array('paymentStatus' => 'FAIL'))));
check($GLOBALS['order']->payments === 1 && count($GLOBALS['order']->notes) === 1 && $GLOBALS['order']->status === 'completed', 'retries and late failures are idempotent');
$GLOBALS['order'] = new TestOrder();
check($response->check_fundiin_notify(signed_request(notification(array('paymentStatus' => 'FAIL', 'paymentTransId' => null))))->status === 204 && $GLOBALS['order']->status === 'cancelled', 'failure can omit success-only fields');
$route_count_before = count($GLOBALS['routes']);
$gateway->clientId = 'bad/client id';
$response->register_notify_api();
check(count($GLOBALS['routes']) === $route_count_before, 'malformed clientId does not register route');
$gateway->clientId = 'client-test';

// Stored XSS cannot break out of checkout script attributes or legacy script settings.
$settings->merchantId = '\"><script>alert(1)</script>';
$visibility = new Fundiin_Visibility();
ob_start(); $visibility->fundiin_in_checkout(); $html = ob_get_clean();
check(strpos($html, '<script>alert(1)</script>') === false && strpos($html, '%3Cscript%3E') !== false, 'checkout merchant value encoded as path');
$settings->merchantId = 'merchant-test';
$GLOBALS['option_values']['script'] = '<script>alert(2)</script>';
$settings->load(true);
$GLOBALS['product'] = new class { public function get_price() { return '100000'; } };
ob_start(); $visibility->fundiin_block(); $html = ob_get_clean();
check(strpos($html, 'alert(2)') === false, 'legacy arbitrary script never rendered');

// Debug logs are opt-in and do not use world-readable ad-hoc files.
Fundiin_Logger::wr_log('safe event');
check(count($GLOBALS['logs']) === 0, 'logging disabled by default');
$GLOBALS['option_values']['debug'] = 'yes'; $settings->load(true);
Fundiin_Logger::wh_log('safe event');
check(count($GLOBALS['logs']) === 1, 'legacy logger uses WooCommerce logger when enabled');
$GLOBALS['allowed'] = false;
check($settings->save() === false && $gateway->process_admin_options() === false, 'settings writes require management permission');

// Actual checkout/refund methods issue signed requests with TLS verification and no redirects.
$settings = new class {
    public function get_fundiin_aio_url() { return 'https://gateway-sandbox.fundiin.vn/v2/payments'; }
    public function get_fundiin_refund_url() { return 'https://gateway-sandbox.fundiin.vn/v2/payments/refund'; }
    public function is_logging_enabled() { return true; }
};
$GLOBALS['plugin']->settings = $settings;
$GLOBALS['order'] = new TestOrder();
$GLOBALS['http_response'] = array('response' => array('code' => 200), 'body' => '{"resultStatus":"APPROVED","paymentUrl":"https://fundiin.vn/v2/payment"}');
$result = $gateway->process_payment(42);
check($result['result'] === 'success', 'valid checkout succeeds');
$http = end($GLOBALS['http']); $body = json_decode($http['args']['body'], true);
check($http['args']['sslverify'] === true && $http['args']['redirection'] === 0, 'checkout verifies TLS and refuses HTTP redirects');
check($body['amount']['value'] === '100000' && $body['referenceId'] === $GLOBALS['order']->meta['_fundiin_reference_id'], 'checkout uses order total and persists reference');
check(hash_equals(hash_hmac('sha256', $http['args']['body'], $gateway->secretKey), $http['args']['headers']['Signature']), 'checkout HMAC covers exact body');
$url_method = new ReflectionMethod('Fundiin', 'is_valid_payment_url');
if (PHP_VERSION_ID < 80100) { $url_method->setAccessible(true); }
foreach (array('https://fundiin.vn.evil.example/pay', 'https://evil.example/pay', 'javascript:alert(1)', 'http://fundiin.vn/pay', 'https://user@fundiin.vn/pay', 'https://fundiin.vn:444/pay') as $url) {
    check($url_method->invoke($gateway, $url) === false, 'unsafe redirect rejected: ' . $url);
}
$GLOBALS['http_response']['body'] = '{"resultStatus":"APPROVED","paymentUrl":"https://evil.example/pay"}';
check($gateway->process_payment(42)['result'] === 'failure', 'unsafe provider redirect fails checkout');
$GLOBALS['http_response']['body'] = 'not JSON';
check($gateway->process_payment(42)['result'] === 'failure', 'malformed provider response fails safely');
$GLOBALS['http_response'] = new WP_Error('network', 'sensitive upstream diagnostic');
check($gateway->process_payment(42)['result'] === 'failure', 'network failure handled');
check(strpos(implode('', $GLOBALS['logs']), 'test-only-secret') === false && strpos(implode('', $GLOBALS['logs']), 'sensitive upstream') === false, 'secrets and upstream diagnostics absent from logs');
$GLOBALS['order']->status = 'completed'; $GLOBALS['order']->transaction = 'transaction-test';
check(rejected($gateway->process_refund(42, 100000), 'fundiin_invalid_refund'), 'unauthorized refund blocked');
$GLOBALS['allowed'] = true;
check(rejected($gateway->process_refund(42, 1), 'cannot_refund'), 'partial refund blocked');
$GLOBALS['http_response'] = array('response' => array('code' => 200), 'body' => '', 'echo_reference' => true);
check($gateway->process_refund(42, 100000) === true, 'authorized full refund succeeds');
$http = end($GLOBALS['http']);
check($http['args']['sslverify'] === true && $http['args']['redirection'] === 0, 'refund verifies TLS and refuses redirects');
check($http['args']['headers']['Signature'] === hash_hmac('sha256', $http['args']['body'], $gateway->secretKey), 'refund uses standard hexadecimal HMAC');
echo 'PASS: ' . $passed . " security regression assertions\n";
