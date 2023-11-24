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
    protected $_settings = array();

    /**
     * Flag to indicate setting has been loaded from DB.
     *
     * @var bool
     */
    private $_is_setting_loaded = false;

    public function __set($key, $value)
    {
        if (array_key_exists($key, $this->_settings)) {
            $this->_settings[$key] = $value;
        }
    }

    public function __get($key)
    {
        if (array_key_exists($key, $this->_settings)) {
            return $this->_settings[$key];
        }
        return null;
    }

    public function __isset($key)
    {
        return array_key_exists($key, $this->_settings);
    }

    public function __construct()
    {
        $this->load();
    }

    /**
     * @return Fundiin_Settings Instance of fundiin_Settings
     */
    public function load($force_reload = false)
    {
        if ($this->_is_setting_loaded && !$force_reload) {
            return $this;
        }

        $this->_settings = (array) get_option('woocommerce_fundiin_settings', array());
        $this->_is_setting_loaded = true;

        return $this;
    }
    public function get_fundiin_host()
    {
        $url = "https://";
        if ('sandbox' !== $this->get_environment()) {
            $url .= fundiin()->get_production_domain_fundiin();
        } else {
            $url .= fundiin()->get_sandbox_domain_fundiin();

        }
        return $url;
    }
    public function get_fundiin_aio_url()
    {

        $url = $this->get_fundiin_host();
        $url .= '/v2/payments';

        return $url;
    }

    public function get_fundiin_refund_url()
    {
        $url = $this->get_fundiin_host();

        $url .= '/v2/payments/refund';

        return $url;
    }

    /**
     * Is fundiin enabled.
     *
     * @return bool
     */
    public function is_enabled()
    {
        return 'yes' === $this->enabled;
    }

    /**
     * Is logging enabled.
     *
     * @return bool
     */
    public function is_logging_enabled()
    {
        return 'yes' === $this->debug;
    }

    /**
     * Save current settings.
     */
    public function save()
    {
        update_option('woocommerce_fundiin_settings', $this->_settings);
    }

    /**
     * Get active environment from setting.
     *
     * @return string
     */
    public function get_environment()
    {
        return 'sandbox' === $this->environment ? 'sandbox' : 'production';
    }
}