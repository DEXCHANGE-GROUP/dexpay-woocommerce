<?php
/**
 * DEXPAY Payment Gateway Class
 *
 * WooCommerce Payment Gateway for DEXPAY
 *
 * @package DEXPAY_WooCommerce
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * DexPay Payment Gateway Class
 */
class DexPay_Gateway extends WC_Payment_Gateway {

    /**
     * API Client
     *
     * @var DexPay_API
     */
    private $api;

    /**
     * Debug mode
     *
     * @var bool
     */
    private $debug;

    /**
     * Sandbox mode
     *
     * @var bool
     */
    private $sandbox;

    /**
     * Whether client pays transaction fees
     *
     * @var bool
     */
    private $client_support_fee;

    /**
     * Constructor
     */
    public function __construct() {
        $this->id                 = 'dexpay';
        $this->icon               = DEXPAY_WC_PLUGIN_URL . 'assets/images/dexpay.png';
        $this->has_fields         = false;
        $this->method_title       = __('DEXPAY', 'dexpay-woocommerce');
        $this->method_description = __('Accept Mobile Money payments (Wave, Orange Money, MTN, Moov) with DEXPAY.', 'dexpay-woocommerce');
        $this->supports           = array(
            'products',
            'refunds',
        );

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

        // Get settings
        $this->title        = $this->get_option('title');
        $this->description  = $this->get_option('description');
        $this->enabled      = $this->get_option('enabled');
        $this->sandbox            = 'yes' === $this->get_option('sandbox');
        $this->client_support_fee = 'yes' === $this->get_option('client_support_fee');
        $this->debug              = 'yes' === $this->get_option('debug');

        // API credentials
        $api_key    = $this->sandbox ? $this->get_option('sandbox_api_key') : $this->get_option('api_key');
        $api_secret = $this->sandbox ? $this->get_option('sandbox_api_secret') : $this->get_option('api_secret');

        // Initialize API client
        $this->api = new DexPay_API($api_key, $api_secret, $this->sandbox);

        // Initialize logger
        DexPay_Logger::init($this->debug);

        // Hooks
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
        
        // Add custom order status
        add_action('init', array($this, 'register_custom_order_statuses'));
        add_filter('wc_order_statuses', array($this, 'add_custom_order_statuses'));
    }

    /**
     * Initialize form fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => __('Enable/Disable', 'dexpay-woocommerce'),
                'label'       => __('Enable DEXPAY', 'dexpay-woocommerce'),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no',
            ),
            'title' => array(
                'title'       => __('Title', 'dexpay-woocommerce'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'dexpay-woocommerce'),
                'default'     => __('Payez avec Mobile Money ou Carte Bancaire (Wave, Orange Money, MTN...)', 'dexpay-woocommerce'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'dexpay-woocommerce'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'dexpay-woocommerce'),
                'default'     => __('Pay securely with Mobile Money via DEXPAY.', 'dexpay-woocommerce'),
                'desc_tip'    => true,
            ),
            'sandbox' => array(
                'title'       => __('Sandbox Mode', 'dexpay-woocommerce'),
                'label'       => __('Enable Sandbox Mode', 'dexpay-woocommerce'),
                'type'        => 'checkbox',
                'description' => __('Place the payment gateway in sandbox mode for testing. Use sandbox API keys.', 'dexpay-woocommerce'),
                'default'     => 'no',
                'desc_tip'    => true,
            ),
            'client_support_fee' => array(
                'title'       => __('Client Pays Fees', 'dexpay-woocommerce'),
                'label'       => __('Enable client support fee', 'dexpay-woocommerce'),
                'type'        => 'checkbox',
                'description' => __('When enabled, the transaction fees will be added to the total amount and paid by the customer instead of the merchant.', 'dexpay-woocommerce'),
                'default'     => 'no',
                'desc_tip'    => true,
            ),
            'api_credentials' => array(
                'title'       => __('Production API Credentials', 'dexpay-woocommerce'),
                'type'        => 'title',
                'description' => sprintf(
                    /* translators: %s: DEXPAY dashboard URL */
                    __('Get your API credentials from your <a href="%s" target="_blank">DEXPAY Dashboard</a>.', 'dexpay-woocommerce'),
                    'https://app.dexpay.africa/api-keys'
                ),
            ),
            'api_key' => array(
                'title'       => __('API Key', 'dexpay-woocommerce'),
                'type'        => 'text',
                'description' => __('Your production API key (pk_live_xxx).', 'dexpay-woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'api_secret' => array(
                'title'       => __('API Secret', 'dexpay-woocommerce'),
                'type'        => 'password',
                'description' => __('Your production API secret (sk_live_xxx).', 'dexpay-woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'sandbox_credentials' => array(
                'title'       => __('Sandbox API Credentials', 'dexpay-woocommerce'),
                'type'        => 'title',
                'description' => __('Enter your sandbox/test API credentials here.', 'dexpay-woocommerce'),
            ),
            'sandbox_api_key' => array(
                'title'       => __('Sandbox API Key', 'dexpay-woocommerce'),
                'type'        => 'text',
                'description' => __('Your sandbox API key (pk_test_xxx).', 'dexpay-woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'sandbox_api_secret' => array(
                'title'       => __('Sandbox API Secret', 'dexpay-woocommerce'),
                'type'        => 'password',
                'description' => __('Your sandbox API secret (sk_test_xxx).', 'dexpay-woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'webhook_section' => array(
                'title'       => __('Webhook Settings', 'dexpay-woocommerce'),
                'type'        => 'title',
                'description' => sprintf(
                    /* translators: %s: Webhook URL */
                    __('Configure this webhook URL in your DEXPAY dashboard: <code>%s</code>', 'dexpay-woocommerce'),
                    home_url('/wc-api/dexpay_webhook/')
                ),
            ),
            'webhook_secret' => array(
                'title'       => __('Webhook Secret', 'dexpay-woocommerce'),
                'type'        => 'password',
                'description' => __('Your webhook secret for signature verification.', 'dexpay-woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'advanced_section' => array(
                'title'       => __('Advanced Settings', 'dexpay-woocommerce'),
                'type'        => 'title',
                'description' => '',
            ),
            'debug' => array(
                'title'       => __('Debug Log', 'dexpay-woocommerce'),
                'label'       => __('Enable logging', 'dexpay-woocommerce'),
                'type'        => 'checkbox',
                'description' => sprintf(
                    /* translators: %s: Log file path */
                    __('Log DEXPAY events inside %s', 'dexpay-woocommerce'),
                    '<code>' . WC_Log_Handler_File::get_log_file_path('dexpay') . '</code>'
                ),
                'default'     => 'no',
            ),
            'order_status_on_payment' => array(
                'title'       => __('Order Status After Payment', 'dexpay-woocommerce'),
                'type'        => 'select',
                'description' => __('Select the order status after successful payment.', 'dexpay-woocommerce'),
                'default'     => 'processing',
                'desc_tip'    => true,
                'options'     => array(
                    'processing' => __('Processing', 'dexpay-woocommerce'),
                    'completed'  => __('Completed', 'dexpay-woocommerce'),
                ),
            ),
        );
    }

    /**
     * Check if gateway is available
     *
     * @return bool
     */
    public function is_available() {
        if ('yes' !== $this->enabled) {
            DexPay_Logger::debug('Gateway disabled');
            return false;
        }

        // Check API credentials
        if ($this->sandbox) {
            $api_key = $this->get_option('sandbox_api_key');
            $api_secret = $this->get_option('sandbox_api_secret');
            if (empty($api_key) || empty($api_secret)) {
                DexPay_Logger::debug('Sandbox API credentials missing');
                return false;
            }
        } else {
            $api_key = $this->get_option('api_key');
            $api_secret = $this->get_option('api_secret');
            if (empty($api_key) || empty($api_secret)) {
                DexPay_Logger::debug('Production API credentials missing');
                return false;
            }
        }

        // Check currency
        $currency = get_woocommerce_currency();
        if (!DexPay_API::is_currency_supported($currency)) {
            DexPay_Logger::debug('Currency not supported: ' . $currency . '. Supported: XOF, XAF, GNF');
            return false;
        }

        return true;
    }

    /**
     * Display admin options with status notices
     */
    public function admin_options() {
        $currency = get_woocommerce_currency();
        $api_key = $this->sandbox ? $this->get_option('sandbox_api_key') : $this->get_option('api_key');
        $api_secret = $this->sandbox ? $this->get_option('sandbox_api_secret') : $this->get_option('api_secret');
        
        // Header
        ?>
        <h2>
            <?php esc_html_e('DEXPAY', 'dexpay-woocommerce'); ?>
            <?php if ($this->sandbox) : ?>
                <span style="background: #ffba00; color: #000; padding: 2px 8px; border-radius: 3px; font-size: 12px; font-weight: normal;">
                    <?php esc_html_e('SANDBOX MODE', 'dexpay-woocommerce'); ?>
                </span>
            <?php endif; ?>
        </h2>
        <p>
            <?php
            printf(
                /* translators: %s: DEXPAY website URL */
                esc_html__('Accept Mobile Money payments with DEXPAY. Need an account? %s', 'dexpay-woocommerce'),
                '<a href="https://app.dexpay.africa/register" target="_blank">' . esc_html__('Sign up here', 'dexpay-woocommerce') . '</a>'
            );
            ?>
        </p>
        <?php
        
        // Status debug info
        echo '<div class="notice notice-info" style="margin: 15px 0;"><p>';
        echo '<strong>Status:</strong> ';
        echo 'Currency: <code>' . esc_html($currency) . '</code> ';
        echo '| Mode: <code>' . ($this->sandbox ? 'Sandbox' : 'Production') . '</code> ';
        echo '| API Key: <code>' . (!empty($api_key) ? '✓' : '✗') . '</code> ';
        echo '| API Secret: <code>' . (!empty($api_secret) ? '✓' : '✗') . '</code>';
        echo '</p></div>';
        
        // Currency warning
        if (!DexPay_API::is_currency_supported($currency)) {
            echo '<div class="notice notice-error" style="margin: 15px 0;"><p>';
            echo sprintf(
                __('<strong>Error:</strong> Currency %s is not supported. Please change to XOF, XAF, or GNF in <a href="%s">WooCommerce Settings</a>.', 'dexpay-woocommerce'),
                '<code>' . esc_html($currency) . '</code>',
                esc_url(admin_url('admin.php?page=wc-settings'))
            );
            echo '</p></div>';
        }

        // API credentials warning
        if (empty($api_key) || empty($api_secret)) {
            echo '<div class="notice notice-warning" style="margin: 15px 0;"><p>';
            if ($this->sandbox) {
                echo __('<strong>Warning:</strong> Sandbox API credentials are missing. Enter them below.', 'dexpay-woocommerce');
            } else {
                echo __('<strong>Warning:</strong> Production API credentials are missing. Enter them below.', 'dexpay-woocommerce');
            }
            echo '</p></div>';
        }
        
        // Settings table
        ?>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
        </table>
        <?php
    }

    /**
     * Get gateway icon
     *
     * @return string
     */
    public function get_icon() {
        $icon_html = '<span style="display: inline-flex; gap: 4px; align-items: center; margin-left: 8px;">';
        $base_url  = 'https://dexchange-s3.s3.eu-north-1.amazonaws.com/icons/';
        
        // Display main payment method icons
        $icons = array(
            'card' => 'card.png',
            'wave' => 'wave.png',
            'om'   => 'om.png',
            'mtn'  => 'mtn.png',
        );

        foreach ($icons as $key => $icon) {
            $icon_url = $base_url . $icon;
            $icon_html .= '<img src="' . esc_url($icon_url) . '" alt="' . esc_attr($key) . '" style="height: 20px; width: auto; border-radius: 3px;" onerror="this.style.display=\'none\'" />';
        }
        
        $icon_html .= '</span>';

        return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
    }

    /**
     * Payment fields on checkout
     */
    public function payment_fields() {
        if ($this->description) {
            $desc = $this->description;
            if ($this->sandbox) {
                $desc .= ' ' . __('(SANDBOX MODE - No real charges)', 'dexpay-woocommerce');
            }
            echo '<p style="margin: 0 0 15px 0; color: #666; font-size: 14px;">' . esc_html($desc) . '</p>';
        }

        // Display accepted payment methods with inline styles for reliability
        echo '<div style="background: #f8f9fa; border-radius: 8px; padding: 12px 15px;">';
        echo '<p style="font-size: 11px; color: #888; margin: 0 0 10px 0; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">' . esc_html__('Accepted Payment Methods', 'dexpay-woocommerce') . '</p>';
        echo '<div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">';
        
        $methods = DexPay_API::get_payment_methods();
        foreach ($methods as $key => $method) {
            echo '<img src="' . esc_url($method['icon']) . '" alt="' . esc_attr($method['name']) . '" title="' . esc_attr($method['name']) . '" style="height: 32px; width: auto; border-radius: 4px; background: #fff; padding: 4px; border: 1px solid #e0e0e0;" onerror="this.style.display=\'none\'" />';
        }
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Process payment
     *
     * @param int $order_id Order ID
     * @return array
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            wc_add_notice(__('Order not found.', 'dexpay-woocommerce'), 'error');
            return array('result' => 'fail');
        }

        DexPay_Logger::info('Processing payment for order #' . $order_id);

        // Generate unique reference
        $reference = $this->generate_reference($order);

        // Build checkout session params
        $params = array(
            'reference'           => $reference,
            'item_name'           => $this->get_order_item_name($order),
            'amount'              => (int) ($order->get_total() * 1), // Amount in smallest unit (no decimals for XOF)
            'currency'            => $order->get_currency(),
            'success_url'         => $this->get_return_url($order),
            'failure_url'         => wc_get_checkout_url(),
            'webhook_url'         => home_url('/wc-api/dexpay_webhook/'),
            'is_one_shot_payment' => true,
            'client_support_fee'  => $this->client_support_fee,
            'metadata'            => array(
                'order_id'     => $order_id,
                'order_key'    => $order->get_order_key(),
                'customer_id'  => $order->get_customer_id(),
                'source'       => 'woocommerce',
                'plugin_version' => DEXPAY_WC_VERSION,
            ),
        );

        // Create checkout session
        $response = $this->api->create_checkout_session($params);

        if (is_wp_error($response)) {
            DexPay_Logger::error('Failed to create checkout session', array(
                'order_id' => $order_id,
                'error'    => $response->get_error_message(),
            ));

            wc_add_notice(
                __('Payment error: ', 'dexpay-woocommerce') . $response->get_error_message(),
                'error'
            );
            return array('result' => 'fail');
        }

        // Get payment URL
        $payment_url = isset($response['data']['payment_url']) ? $response['data']['payment_url'] : null;
        $session_id  = isset($response['data']['id']) ? $response['data']['id'] : null;

        // Handle sandbox mode with sandbox_payment_url
        if ($this->sandbox && isset($response['data']['sandbox_payment_url'])) {
            $payment_url = $response['data']['sandbox_payment_url'];
        }

        if (empty($payment_url)) {
            DexPay_Logger::error('No payment URL in response', array(
                'order_id' => $order_id,
                'response' => $response,
            ));

            wc_add_notice(__('Unable to create payment session. Please try again.', 'dexpay-woocommerce'), 'error');
            return array('result' => 'fail');
        }

        // Store session data in order meta
        $order->update_meta_data('_dexpay_reference', $reference);
        $order->update_meta_data('_dexpay_session_id', $session_id);
        $order->update_meta_data('_dexpay_payment_url', $payment_url);
        $order->update_meta_data('_dexpay_sandbox', $this->sandbox ? 'yes' : 'no');
        $order->save();

        // Add order note
        $order->add_order_note(
            sprintf(
                /* translators: %s: DEXPAY reference */
                __('DEXPAY payment initiated. Reference: %s', 'dexpay-woocommerce'),
                $reference
            )
        );

        // Update order status
        $order->update_status('pending', __('Awaiting DEXPAY payment.', 'dexpay-woocommerce'));

        DexPay_Logger::info('Redirecting to DEXPAY payment page', array(
            'order_id'    => $order_id,
            'reference'   => $reference,
            'payment_url' => $payment_url,
        ));

        // Return redirect
        return array(
            'result'   => 'success',
            'redirect' => $payment_url,
        );
    }

    /**
     * Generate unique reference for order
     *
     * @param WC_Order $order Order object
     * @return string
     */
    private function generate_reference($order) {
        $prefix = apply_filters('dexpay_reference_prefix', 'WC');
        $order_id = $order->get_id();
        $timestamp = time();
        $random = wp_rand(1000, 9999);

        return sprintf('%s_%d_%d_%d', $prefix, $order_id, $timestamp, $random);
    }

    /**
     * Get order item name for checkout session
     *
     * @param WC_Order $order Order object
     * @return string
     */
    private function get_order_item_name($order) {
        $items = $order->get_items();
        $item_names = array();

        foreach ($items as $item) {
            $item_names[] = $item->get_name();
        }

        if (count($item_names) > 3) {
            return sprintf(
                /* translators: 1: First item name, 2: Number of other items */
                __('%1$s and %2$d other items', 'dexpay-woocommerce'),
                $item_names[0],
                count($item_names) - 1
            );
        }

        return implode(', ', $item_names);
    }

    /**
     * Thank you page output
     *
     * @param int $order_id Order ID
     */
    public function thankyou_page($order_id) {
        $order = wc_get_order($order_id);

        if (!$order || $order->get_payment_method() !== $this->id) {
            return;
        }

        $reference = $order->get_meta('_dexpay_reference');
        $sandbox   = $order->get_meta('_dexpay_sandbox') === 'yes';

        if ($sandbox) {
            echo '<div class="woocommerce-info">';
            echo esc_html__('This order was placed in SANDBOX mode. No real payment was processed.', 'dexpay-woocommerce');
            echo '</div>';
        }

        if ($reference) {
            echo '<div class="dexpay-order-reference">';
            echo '<strong>' . esc_html__('DEXPAY Reference:', 'dexpay-woocommerce') . '</strong> ';
            echo '<code>' . esc_html($reference) . '</code>';
            echo '</div>';
        }
    }

    /**
     * Add content to the WC emails
     *
     * @param WC_Order $order         Order object
     * @param bool     $sent_to_admin Sent to admin
     * @param bool     $plain_text    Plain text
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false) {
        if ($order->get_payment_method() !== $this->id) {
            return;
        }

        $reference = $order->get_meta('_dexpay_reference');

        if ($reference) {
            if ($plain_text) {
                echo "\n" . esc_html__('DEXPAY Reference:', 'dexpay-woocommerce') . ' ' . $reference . "\n";
            } else {
                echo '<p><strong>' . esc_html__('DEXPAY Reference:', 'dexpay-woocommerce') . '</strong> <code>' . esc_html($reference) . '</code></p>';
            }
        }
    }

    /**
     * Process refund
     *
     * @param int    $order_id Order ID
     * @param float  $amount   Refund amount
     * @param string $reason   Refund reason
     * @return bool|WP_Error
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = wc_get_order($order_id);

        if (!$order) {
            return new WP_Error('invalid_order', __('Order not found.', 'dexpay-woocommerce'));
        }

        // Note: Refund API to be implemented
        // For now, mark as manual refund needed
        $order->add_order_note(
            sprintf(
                /* translators: 1: Refund amount, 2: Currency, 3: Refund reason */
                __('Refund requested: %1$s %2$s. Reason: %3$s. Please process manually in DEXPAY dashboard.', 'dexpay-woocommerce'),
                $amount,
                $order->get_currency(),
                $reason
            )
        );

        return true;
    }

    /**
     * Register custom order statuses
     */
    public function register_custom_order_statuses() {
        register_post_status('wc-dexpay-pending', array(
            'label'                     => _x('DEXPAY Pending', 'Order status', 'dexpay-woocommerce'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            /* translators: %s: Number of orders */
            'label_count'               => _n_noop('DEXPAY Pending <span class="count">(%s)</span>', 'DEXPAY Pending <span class="count">(%s)</span>', 'dexpay-woocommerce'),
        ));
    }

    /**
     * Add custom order statuses to WooCommerce
     *
     * @param array $order_statuses Existing order statuses
     * @return array
     */
    public function add_custom_order_statuses($order_statuses) {
        $new_statuses = array();

        foreach ($order_statuses as $key => $status) {
            $new_statuses[$key] = $status;

            if ('wc-pending' === $key) {
                $new_statuses['wc-dexpay-pending'] = _x('DEXPAY Pending', 'Order status', 'dexpay-woocommerce');
            }
        }

        return $new_statuses;
    }

}
