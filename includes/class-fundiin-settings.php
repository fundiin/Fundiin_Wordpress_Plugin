<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles settings retrieval from the settings API.
 */
class Fundiin_Settings
{
    /**
     * Setting values from get_option.
     *
     * @var array
     */
    protected $_settings = [];

    /**
     * Flag to indicate setting has been loaded from DB.
     *
     * @var bool
     */
    private $_is_setting_loaded = false;

    public function __set($key, $value) {
        if (array_key_exists($key, $this->_settings)) {
            $this->_settings[$key] = $value;
        }
    }

    public function __get($key) {
        if (array_key_exists($key, $this->_settings)) {
            return $this->_settings[$key];
        }

        return null;
    }

    public function __isset($key) {
        return array_key_exists($key, $this->_settings);
    }

    public function __construct() {
        $this->load();
    }

    /**
     * @return Fundiin_Settings Instance of fundiin_Settings
     */
    public function load($force_reload = false) {
        if ($this->_is_setting_loaded && !$force_reload) {
            return $this;
        }

        $this->_settings = (array) get_option('woocommerce_fundiin_settings', []);
        $this->_is_setting_loaded = true;

        return $this;
    }

    public function get_fundiin_host() {
        $url = 'https://';

        if ($this->get_environment() === 'sandbox') {
            return $url . fundiin()->get_sandbox_domain_fundiin();
        }

        return $url . fundiin()->get_production_domain_fundiin();
    }

    public function get_fundiin_aio_url() {
        return $this->get_fundiin_host() . '/v2/payments';
    }

    public function get_fundiin_refund_url() {
        return $this->get_fundiin_host() . '/v2/payments/refund';
    }

    /**
     * Is fundiin enabled.
     *
     * @return bool
     */
    public function is_enabled() {
        return $this->enabled === 'yes';
    }

    /**
     * Is logging enabled.
     *
     * @return bool
     */
    public function is_logging_enabled() {
        return $this->debug === 'yes';
    }

    /**
     * Save current settings.
     */
    public function save() {
        update_option('woocommerce_fundiin_settings', $this->_settings);
    }

    /**
     * Get active environment from setting.
     *
     * @return string
     */
    public function get_environment() {
        return 'sandbox' === $this->environment ? 'sandbox' : 'production';
    }
}
