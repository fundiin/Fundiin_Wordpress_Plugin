<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Fundiin Payment Gateway Plugin.
 */

class Fundiin_Plugin
{

    /**
     * Filepath of main plugin file.
     *
     * @var string
     */
    public $file;

    /**
     * Plugin version.
     *
     * @var string
     */
    public $version;

    /**
     * Absolute plugin path.
     *
     * @var string
     */
    public $plugin_path;

    /**
     * Absolute plugin URL.
     *
     * @var string
     */
    public $plugin_url;

    /**
     * Absolute path to plugin includes dir.
     *
     * @var string
     */
    public $includes_path;

    /**
     * Constructor.
     *
     * @param string $file    Filepath of main plugin file
     * @param string $version Plugin version
     */
    public function __construct($file, $version)
    {
        $this->file = $file;
        $this->version = $version;

        $this->plugin_path = trailingslashit(plugin_dir_path($this->file));
        $this->plugin_url = trailingslashit(plugin_dir_url($this->file));
        $this->includes_path = $this->plugin_path . trailingslashit('includes');
    }

    public function run()
    {
        add_action('plugins_loaded', array($this, 'boot_system'));
        add_filter('allowed_redirect_hosts', array($this, 'whitelist_fundiin_domains_for_redirect'));

        add_action('init', array($this, 'load_plugin_textdomain'));

        add_filter('plugin_action_links_' . plugin_basename($this->file), array($this, 'plugin_action_links'));
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
    }

    public function boot_system()
    {
        try {
            if (function_exists('WC')) {
                $this->_run();
            } else {
                add_action('admin_notices', array($this, 'notice_if_not_woocommerce'));
            }
        } catch (Exception $ex) {
            $ex->getMessage();
        }
    }

    /**
     * Throw a notice if WooCommerce is NOT active
     */
    public function notice_if_not_woocommerce()
    {
        $class = 'notice notice-warning';

        $message = __(
            'Thanh toán qua Fundiin chưa thể sử dụng vì WooCommerce chưa được kích hoạt',
            'woocommerce-gateway-fundiin'
        );

        printf('<div class="%1$s"><p><strong>%2$s</strong></p></div>', $class, $message);
    }

    /**
     * Run the plugin.
     */
    protected function _run()
    {
        // require_once $this->includes_path . 'functions.php';
        $this->_load_handlers();
    }

    protected function _load_handlers()
    {

        // // Load handlers.
        require_once $this->includes_path . 'class-fundiin-settings.php';
        require_once $this->includes_path . 'class-fundiin-logger.php';

        require_once $this->includes_path . 'class-fundiin-gateway-loader.php';
        require_once $this->includes_path . 'class-fundiin-response.php';
        require_once $this->includes_path . 'class-fundiin-visibility.php';
        require_once $this->includes_path . 'abstracts/abstract-fundiin.php';
        require_once $this->includes_path . 'class-fundiin-with-aio.php';
        $this->settings = new Fundiin_Settings();
        $this->response = new fundiin_Response();
        $this->visibility = new Fundiin_Visibility();
        $this->gateway_loader = new Fundiin_Gateway_Loader();

        $this->fundiin = new Fundiin();
        $this->add_cors_plugin();
    }

    /**
     * Checks if the plugin needs to record an update.
     *
     * @return bool Whether the plugin needs to be updated.
     */
    protected function needs_update()
    {
        return version_compare($this->version, get_option('wc_fundiin_version'), '>');
    }
    function add_cors_plugin()
    {
        header("Access-Control-Allow-Origin: '*.fundiin.vn'");

    }

    /**
     * Link to settings screen.
     */
    public function get_admin_setting_link()
    {
        $section_slug = 'fundiin';

        return admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $section_slug);
    }


    public function get_production_domain_fundiin()
    {
        return 'gateway.fundiin.vn';
    }

    public function get_sandbox_domain_fundiin()
    {
        return 'gateway-sandbox.fundiin.vn';
    }

    /**
     * Allow Fundiin domains for redirect.
     *
     * @since 2.0.0
     *
     * @param array $domains Whitelisted domains for `wp_safe_redirect`
     *
     * @return array $domains Whitelisted domains for `wp_safe_redirect`
     */
    public function whitelist_fundiin_domains_for_redirect($domains)
    {
        $domains[] = $this->get_sandbox_domain_fundiin();
        $domains[] = $this->get_production_domain_fundiin();
        return $domains;
    }
    public function load_plugin_textdomain()
    {
        load_plugin_textdomain('woocommerce-gateway-fundiin', false, plugin_basename($this->plugin_path) . '/languages');
    }

    /**
     * Add relevant links to plugins page.
     *
     * @param array $links Plugin action links
     *
     * @return array Plugin action links
     */
    public function plugin_action_links($links)
    {
        $plugin_links = array();

        if (function_exists('WC')) {
            $setting_url = $this->get_admin_setting_link();
            $plugin_links[] = '<a href="' . esc_url($setting_url) . '">' . esc_html__('Cài đặt', 'woocommerce-gateway-fundiin') . '</a>';
        }

        return array_merge($plugin_links, $links);
    }

    /**
     * Plugin page links to support and documentation
     *
     * @param  array  $links List of plugin links.
     * @param  string $file Current file.
     * @return array
     */
    public function plugin_row_meta($links, $file)
    {
        $row_meta = array();

        if (false !== strpos($file, plugin_basename(dirname(__DIR__)))) {
            $row_meta = array(
                'docs' => sprintf('<a href="%s" title="%s">%s</a>', esc_url('https://docs.fundiin.vn/v2/'), esc_attr__('Xem tài liệu hướng dẫn', 'woocommerce-gateway-fundiin'), esc_html__('Tài liệu', 'woocommerce-gateway-fundiin')),
            );
        }

        return array_merge($links, $row_meta);
    }
}