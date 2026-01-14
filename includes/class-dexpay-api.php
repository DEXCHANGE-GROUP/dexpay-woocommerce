<?php
/**
 * DEXPAY API Client Class
 *
 * Handles all API communications with DEXPAY
 *
 * @package DEXPAY_WooCommerce
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * DexPay API Client Class
 */
class DexPay_API {

    /**
     * API Key
     *
     * @var string
     */
    private $api_key;

    /**
     * API Secret
     *
     * @var string
     */
    private $api_secret;

    /**
     * Sandbox mode
     *
     * @var bool
     */
    private $sandbox;

    /**
     * API Base URL
     *
     * @var string
     */
    private $base_url;

    /**
     * Constructor
     *
     * @param string $api_key    API Key
     * @param string $api_secret API Secret
     * @param bool   $sandbox    Sandbox mode
     */
    public function __construct($api_key, $api_secret, $sandbox = false) {
        $this->api_key    = $api_key;
        $this->api_secret = $api_secret;
        $this->sandbox    = $sandbox;
        $this->base_url   = $sandbox ? DEXPAY_SANDBOX_API_URL : DEXPAY_API_URL;
    }

    /**
     * Create a checkout session
     *
     * @param array $params Session parameters
     * @return array|WP_Error
     */
    public function create_checkout_session($params) {
        return $this->request('POST', '/checkout-sessions', $params);
    }

    /**
     * Get checkout session by ID
     *
     * @param string $session_id Session ID
     * @return array|WP_Error
     */
    public function get_checkout_session($session_id) {
        return $this->request('GET', '/checkout-sessions/' . $session_id);
    }

    /**
     * Get checkout session by reference
     *
     * @param string $reference Session reference
     * @return array|WP_Error
     */
    public function get_checkout_session_by_reference($reference) {
        return $this->request('GET', '/checkout-sessions/reference/' . $reference);
    }

    /**
     * Cancel checkout session
     *
     * @param string $session_id Session ID
     * @return array|WP_Error
     */
    public function cancel_checkout_session($session_id) {
        return $this->request('DELETE', '/checkout-sessions/' . $session_id);
    }

    /**
     * Make API request
     *
     * @param string $method   HTTP method
     * @param string $endpoint API endpoint
     * @param array  $data     Request data
     * @return array|WP_Error
     */
    private function request($method, $endpoint, $data = array()) {
        $url = $this->base_url . $endpoint;

        DexPay_Logger::log_api_request($method, $endpoint, $data);

        $args = array(
            'method'  => $method,
            'timeout' => 60,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'x-api-key'     => $this->api_key,
                'x-api-secret'  => $this->api_secret,
                'User-Agent'    => 'DEXPAY-WooCommerce/' . DEXPAY_WC_VERSION,
            ),
        );

        if (!empty($data) && in_array($method, array('POST', 'PUT', 'PATCH'), true)) {
            $args['body'] = wp_json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            DexPay_Logger::error('API Request Failed', array(
                'endpoint' => $endpoint,
                'error'    => $response->get_error_message(),
            ));
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body        = wp_remote_retrieve_body($response);
        $decoded     = json_decode($body, true);

        DexPay_Logger::log_api_response($endpoint, $status_code, $decoded);

        if ($status_code < 200 || $status_code >= 300) {
            $error_message = isset($decoded['message']) ? $decoded['message'] : __('Unknown API error', 'dexpay-woocommerce');
            
            DexPay_Logger::error('API Error Response', array(
                'endpoint'    => $endpoint,
                'status_code' => $status_code,
                'message'     => $error_message,
            ));

            return new WP_Error(
                'dexpay_api_error',
                $error_message,
                array('status_code' => $status_code, 'response' => $decoded)
            );
        }

        return $decoded;
    }

    /**
     * Verify webhook signature
     *
     * @param string $payload   Raw payload
     * @param string $signature Signature from header
     * @param string $secret    Webhook secret
     * @return bool
     */
    public static function verify_webhook_signature($payload, $signature, $secret) {
        if (empty($signature) || empty($secret)) {
            return false;
        }

        $expected_signature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($expected_signature, $signature);
    }

    /**
     * Get supported currencies
     *
     * @return array
     */
    public static function get_supported_currencies() {
        return array('XOF', 'XAF', 'GNF');
    }

    /**
     * Check if currency is supported
     *
     * @param string $currency Currency code
     * @return bool
     */
    public static function is_currency_supported($currency) {
        return in_array(strtoupper($currency), self::get_supported_currencies(), true);
    }

    /**
     * Get supported countries
     *
     * @return array
     */
    public static function get_supported_countries() {
        return array(
            'SN' => __('Senegal', 'dexpay-woocommerce'),
            'CI' => __('Ivory Coast', 'dexpay-woocommerce'),
            'ML' => __('Mali', 'dexpay-woocommerce'),
            'BF' => __('Burkina Faso', 'dexpay-woocommerce'),
            'BJ' => __('Benin', 'dexpay-woocommerce'),
            'TG' => __('Togo', 'dexpay-woocommerce'),
            'NE' => __('Niger', 'dexpay-woocommerce'),
            'GW' => __('Guinea-Bissau', 'dexpay-woocommerce'),
            'GN' => __('Guinea', 'dexpay-woocommerce'),
            'CM' => __('Cameroon', 'dexpay-woocommerce'),
            'GA' => __('Gabon', 'dexpay-woocommerce'),
            'CG' => __('Congo', 'dexpay-woocommerce'),
            'TD' => __('Chad', 'dexpay-woocommerce'),
            'CF' => __('Central African Republic', 'dexpay-woocommerce'),
            'GQ' => __('Equatorial Guinea', 'dexpay-woocommerce'),
        );
    }

    /**
     * Get payment methods
     *
     * @return array
     */
    /**
     * Get payment methods with icons
     *
     * @return array
     */
    public static function get_payment_methods() {
        $base_url = 'https://dexchange-s3.s3.eu-north-1.amazonaws.com/icons/';
        
        return array(
            'card'         => array(
                'name' => 'Carte Bancaire',
                'icon' => $base_url . 'card.png',
            ),
            'wave'         => array(
                'name' => 'Wave',
                'icon' => $base_url . 'wave.png',
            ),
            'orange_money' => array(
                'name' => 'Orange Money',
                'icon' => $base_url . 'om.png',
            ),
            'mtn_money'    => array(
                'name' => 'MTN Money',
                'icon' => $base_url . 'mtn.png',
            ),
            'moov_money'   => array(
                'name' => 'Moov Money',
                'icon' => $base_url . 'moov.png',
            ),
            'free_money'   => array(
                'name' => 'Free Money',
                'icon' => $base_url . 'fm.png',
            ),
            'wizall'       => array(
                'name' => 'Wizall',
                'icon' => $base_url . 'wizall.png',
            ),
        );
    }
}
