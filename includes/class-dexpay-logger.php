<?php
/**
 * DEXPAY Logger Class
 *
 * Handles logging for debugging and troubleshooting
 *
 * @package DEXPAY_WooCommerce
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * DexPay Logger Class
 */
class DexPay_Logger {

    /**
     * WooCommerce logger instance
     *
     * @var WC_Logger
     */
    private static $logger = null;

    /**
     * Log source/context
     *
     * @var string
     */
    private static $source = 'dexpay';

    /**
     * Whether logging is enabled
     *
     * @var bool
     */
    private static $enabled = false;

    /**
     * Initialize the logger
     *
     * @param bool $enabled Whether logging is enabled
     */
    public static function init($enabled = false) {
        self::$enabled = $enabled;
    }

    /**
     * Get WooCommerce logger instance
     *
     * @return WC_Logger
     */
    private static function get_logger() {
        if (is_null(self::$logger)) {
            self::$logger = wc_get_logger();
        }
        return self::$logger;
    }

    /**
     * Log a debug message
     *
     * @param string $message Message to log
     * @param array  $context Additional context
     */
    public static function debug($message, $context = array()) {
        self::log('debug', $message, $context);
    }

    /**
     * Log an info message
     *
     * @param string $message Message to log
     * @param array  $context Additional context
     */
    public static function info($message, $context = array()) {
        self::log('info', $message, $context);
    }

    /**
     * Log a warning message
     *
     * @param string $message Message to log
     * @param array  $context Additional context
     */
    public static function warning($message, $context = array()) {
        self::log('warning', $message, $context);
    }

    /**
     * Log an error message
     *
     * @param string $message Message to log
     * @param array  $context Additional context
     */
    public static function error($message, $context = array()) {
        self::log('error', $message, $context);
    }

    /**
     * Log a critical message
     *
     * @param string $message Message to log
     * @param array  $context Additional context
     */
    public static function critical($message, $context = array()) {
        self::log('critical', $message, $context);
    }

    /**
     * Generic log method
     *
     * @param string $level   Log level
     * @param string $message Message to log
     * @param array  $context Additional context
     */
    private static function log($level, $message, $context = array()) {
        if (!self::$enabled && $level !== 'error' && $level !== 'critical') {
            return;
        }

        if (!function_exists('wc_get_logger')) {
            return;
        }

        $logger = self::get_logger();

        // Format context for better readability
        $formatted_message = $message;
        if (!empty($context)) {
            $formatted_message .= ' | Context: ' . wp_json_encode($context, JSON_PRETTY_PRINT);
        }

        $log_context = array('source' => self::$source);

        switch ($level) {
            case 'debug':
                $logger->debug($formatted_message, $log_context);
                break;
            case 'info':
                $logger->info($formatted_message, $log_context);
                break;
            case 'warning':
                $logger->warning($formatted_message, $log_context);
                break;
            case 'error':
                $logger->error($formatted_message, $log_context);
                break;
            case 'critical':
                $logger->critical($formatted_message, $log_context);
                break;
            default:
                $logger->debug($formatted_message, $log_context);
        }
    }

    /**
     * Log API request
     *
     * @param string $method   HTTP method
     * @param string $endpoint API endpoint
     * @param array  $data     Request data
     */
    public static function log_api_request($method, $endpoint, $data = array()) {
        // Mask sensitive data
        $safe_data = self::mask_sensitive_data($data);
        
        self::debug(
            sprintf('API Request: %s %s', $method, $endpoint),
            array('request_data' => $safe_data)
        );
    }

    /**
     * Log API response
     *
     * @param string $endpoint    API endpoint
     * @param int    $status_code HTTP status code
     * @param mixed  $response    Response data
     */
    public static function log_api_response($endpoint, $status_code, $response) {
        self::debug(
            sprintf('API Response: %s (HTTP %d)', $endpoint, $status_code),
            array('response' => $response)
        );
    }

    /**
     * Mask sensitive data for logging
     *
     * @param array $data Data to mask
     * @return array
     */
    private static function mask_sensitive_data($data) {
        $sensitive_keys = array('api_key', 'api_secret', 'password', 'secret', 'token');
        
        $masked = array();
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $masked[$key] = self::mask_sensitive_data($value);
            } elseif (in_array(strtolower($key), $sensitive_keys, true)) {
                $masked[$key] = '***MASKED***';
            } else {
                $masked[$key] = $value;
            }
        }
        
        return $masked;
    }
}
