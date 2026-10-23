<?php
defined('ABSPATH') || exit;

if (!class_exists('Fundiin_Logger')) {
    class Fundiin_Logger
    {

        public static $logger;

        /**
         * Utilize WC logger class
         *
         * @since 4.0.0
         * @version 4.0.0
         */
        public static function wr_log($message)
        {
            if (!class_exists('WC_Logger')) {
                return;
            }

            if (!Fundiin_Logger::get_can_write_log()) {
                return;
            }

            $log_file_name = "fundiin-payment-log";
            if (empty(self::$logger)) {
                self::$logger = wc_get_logger();
            }

            $log_entry = "\n" . $message . "\n";

            self::$logger->debug($log_entry, ['source' => $log_file_name]);
        }

        public static function wh_log($log_msg)
        {
            self::wr_log($log_msg);
        }

        private static function get_can_write_log()
        {
            return fundiin()->settings->is_logging_enabled();
        }
    }
}
