# Documents

Simple document.

## PHP Usage

Standalone
```php
// Requires all files from folder libraries
$files = glob(dirname(__FILE__).'/libraries/*.php');
		
foreach ($files as $file) {	
	require_once $file;
}

// Create instance object
$plugin = \BluecoralWoo\Plugin::instance();

// Get address data
$post_id = 123;
$address = $plugin->getData($post_id);

// Debug data
var_dump($address);
```

Activate plugin
```php
$post_id = 123;
// Use function
$address = bluecoral_get_order_address($post_id);

// Call from plugin
$plugin = $GLOBALS['plugin_bwpa'];
$address = $plugin->get_address_object($post_id);
```

## Wordpress REST Api

Activate plugin
```php
{SITE_URL}/wp-json/bluecoral/order/{ORDER_ID}
```
Example: https://example.com/wp-json/bluecoral/order/59

Standalone
Check [Wordpress Document](https://developer.wordpress.org/rest-api/) for more information
```php
// Check bluecoral-woo-parse-address.php line 132, register rest api hook action
add_action('rest_api_init', [$this, 'plugin_rest_api_init']);

// Check bluecoral-woo-parse-address.php line 139, rest api method
function plugin_rest_api_init() {
	// pattern /wp-json/bluecoral/order/{id}
	register_rest_route('bluecoral', '/order/(?P<id>[^\s]+)', []);
	// pattern /wp-json/example/{id}
	register_rest_route('example', '/(?P<id>[^\s]+)', []);
}

// Check bluecoral-woo-parse-address.php line 155, rest api callback method
function plugin_rest_api_order(\WP_REST_Request $request) {
	// retrieve order id from request
	$id = (int) $request->get_param('id') ?? 0;
	// logic is same
	// ...
}
```

## License
[MIT](https://choosealicense.com/licenses/mit/)