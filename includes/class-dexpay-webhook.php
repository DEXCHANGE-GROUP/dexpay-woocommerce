<?php
/**
 * DEXPAY Webhook Handler Class
 *
 * Handles incoming webhooks from DEXPAY
 *
 * @package DEXPAY_WooCommerce
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * DexPay Webhook Handler Class
 */
class DexPay_Webhook {

    /**
     * Webhook secret
     *
     * @var string
     */
    private $webhook_secret;

    /**
     * Gateway settings
     *
     * @var array
     */
    private $settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings       = get_option('woocommerce_dexpay_settings', array());
        $this->webhook_secret = isset($this->settings['webhook_secret']) ? $this->settings['webhook_secret'] : '';
        
        // Initialize logger
        $debug = isset($this->settings['debug']) && 'yes' === $this->settings['debug'];
        DexPay_Logger::init($debug);
    }

    /**
     * Process incoming webhook
     */
    public function process() {
        DexPay_Logger::info('Webhook received');

        // Get raw payload
        $payload = file_get_contents('php://input');

        if (empty($payload)) {
            DexPay_Logger::error('Empty webhook payload');
            $this->send_response(400, 'Empty payload');
            return;
        }

        // Verify signature if secret is configured
        if (!empty($this->webhook_secret)) {
            $signature = isset($_SERVER['HTTP_X_DEXPAY_SIGNATURE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_DEXPAY_SIGNATURE'])) : '';

            if (!DexPay_API::verify_webhook_signature($payload, $signature, $this->webhook_secret)) {
                DexPay_Logger::error('Invalid webhook signature', array(
                    'received_signature' => $signature,
                ));
                $this->send_response(401, 'Invalid signature');
                return;
            }

            DexPay_Logger::debug('Webhook signature verified');
        }

        // Parse payload
        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            DexPay_Logger::error('Invalid JSON payload', array('error' => json_last_error_msg()));
            $this->send_response(400, 'Invalid JSON');
            return;
        }

        DexPay_Logger::info('Webhook payload parsed', array('data' => $data));

        // Get event type
        $event = isset($data['event']) ? $data['event'] : '';

        if (empty($event)) {
            DexPay_Logger::error('Missing event type in webhook');
            $this->send_response(400, 'Missing event type');
            return;
        }

        // Process event
        $result = $this->handle_event($event, $data);

        if (is_wp_error($result)) {
            DexPay_Logger::error('Webhook processing failed', array(
                'event' => $event,
                'error' => $result->get_error_message(),
            ));
            $this->send_response(500, $result->get_error_message());
            return;
        }

        DexPay_Logger::info('Webhook processed successfully', array('event' => $event));
        $this->send_response(200, 'OK');
    }

    /**
     * Handle webhook event
     *
     * @param string $event Event type
     * @param array  $data  Event data
     * @return bool|WP_Error
     */
    private function handle_event($event, $data) {
        // Get event data
        $event_data = isset($data['data']) ? $data['data'] : $data;

        switch ($event) {
            case 'CHECKOUT_INITIATED':
                return $this->handle_checkout_initiated($event_data);

            case 'CHECKOUT_COMPLETED':
                return $this->handle_checkout_completed($event_data);

            case 'CHECKOUT_FAILED':
                return $this->handle_checkout_failed($event_data);

            case 'CHECKOUT_CANCELLED':
                return $this->handle_checkout_cancelled($event_data);

            case 'CHECKOUT_REFUNDED':
                return $this->handle_checkout_refunded($event_data);

            default:
                DexPay_Logger::warning('Unknown webhook event', array('event' => $event));
                return true; // Don't fail on unknown events
        }
    }

    /**
     * Handle CHECKOUT_INITIATED event
     *
     * @param array $data Event data
     * @return bool|WP_Error
     */
    private function handle_checkout_initiated($data) {
        $order = $this->get_order_by_reference($data);

        if (!$order) {
            return new WP_Error('order_not_found', 'Order not found');
        }

        $order->add_order_note(
            __('DEXPAY: Payment initiated by customer.', 'dexpay-woocommerce')
        );

        return true;
    }

    /**
     * Handle CHECKOUT_COMPLETED event
     *
     * @param array $data Event data
     * @return bool|WP_Error
     */
    private function handle_checkout_completed($data) {
        $order = $this->get_order_by_reference($data);

        if (!$order) {
            return new WP_Error('order_not_found', 'Order not found');
        }

        // Check if already processed
        if ($order->is_paid()) {
            DexPay_Logger::info('Order already paid', array('order_id' => $order->get_id()));
            return true;
        }

        // Get transaction details
        $transaction_id = isset($data['transaction_id']) ? $data['transaction_id'] : '';
        $payment_method = isset($data['payment_method']) ? $data['payment_method'] : '';
        $operator       = isset($data['operator']) ? $data['operator'] : '';

        // Store transaction details
        if (!empty($transaction_id)) {
            $order->set_transaction_id($transaction_id);
            $order->update_meta_data('_dexpay_transaction_id', $transaction_id);
        }

        if (!empty($payment_method)) {
            $order->update_meta_data('_dexpay_payment_method', $payment_method);
        }

        if (!empty($operator)) {
            $order->update_meta_data('_dexpay_operator', $operator);
        }

        // Complete payment
        $order->payment_complete($transaction_id);

        // Get configured order status
        $order_status = isset($this->settings['order_status_on_payment']) ? $this->settings['order_status_on_payment'] : 'processing';

        // Update to configured status
        $order->update_status(
            $order_status,
            sprintf(
                /* translators: 1: Operator name, 2: Transaction ID */
                __('DEXPAY payment completed via %1$s. Transaction ID: %2$s', 'dexpay-woocommerce'),
                $operator ?: __('Mobile Money', 'dexpay-woocommerce'),
                $transaction_id ?: __('N/A', 'dexpay-woocommerce')
            )
        );

        $order->save();

        DexPay_Logger::info('Payment completed', array(
            'order_id'       => $order->get_id(),
            'transaction_id' => $transaction_id,
            'operator'       => $operator,
        ));

        // Trigger WooCommerce action
        do_action('dexpay_payment_completed', $order, $data);

        return true;
    }

    /**
     * Handle CHECKOUT_FAILED event
     *
     * @param array $data Event data
     * @return bool|WP_Error
     */
    private function handle_checkout_failed($data) {
        $order = $this->get_order_by_reference($data);

        if (!$order) {
            return new WP_Error('order_not_found', 'Order not found');
        }

        // Don't update if already completed or refunded
        if (in_array($order->get_status(), array('completed', 'processing', 'refunded'), true)) {
            DexPay_Logger::info('Order already in final state', array(
                'order_id' => $order->get_id(),
                'status'   => $order->get_status(),
            ));
            return true;
        }

        $error_message = isset($data['error']) ? $data['error'] : '';
        $error_code    = isset($data['error_code']) ? $data['error_code'] : '';

        $order->update_status(
            'failed',
            sprintf(
                /* translators: 1: Error message, 2: Error code */
                __('DEXPAY payment failed. %1$s (Code: %2$s)', 'dexpay-woocommerce'),
                $error_message ?: __('Unknown error', 'dexpay-woocommerce'),
                $error_code ?: __('N/A', 'dexpay-woocommerce')
            )
        );

        $order->update_meta_data('_dexpay_error', $error_message);
        $order->update_meta_data('_dexpay_error_code', $error_code);
        $order->save();

        DexPay_Logger::info('Payment failed', array(
            'order_id' => $order->get_id(),
            'error'    => $error_message,
        ));

        // Trigger WooCommerce action
        do_action('dexpay_payment_failed', $order, $data);

        return true;
    }

    /**
     * Handle CHECKOUT_CANCELLED event
     *
     * @param array $data Event data
     * @return bool|WP_Error
     */
    private function handle_checkout_cancelled($data) {
        $order = $this->get_order_by_reference($data);

        if (!$order) {
            return new WP_Error('order_not_found', 'Order not found');
        }

        // Don't update if already completed
        if (in_array($order->get_status(), array('completed', 'processing', 'refunded'), true)) {
            return true;
        }

        $order->update_status(
            'cancelled',
            __('DEXPAY payment was cancelled.', 'dexpay-woocommerce')
        );

        DexPay_Logger::info('Payment cancelled', array('order_id' => $order->get_id()));

        // Trigger WooCommerce action
        do_action('dexpay_payment_cancelled', $order, $data);

        return true;
    }

    /**
     * Handle CHECKOUT_REFUNDED event
     *
     * @param array $data Event data
     * @return bool|WP_Error
     */
    private function handle_checkout_refunded($data) {
        $order = $this->get_order_by_reference($data);

        if (!$order) {
            return new WP_Error('order_not_found', 'Order not found');
        }

        $refund_amount = isset($data['refund_amount']) ? floatval($data['refund_amount']) : $order->get_total();
        $refund_id     = isset($data['refund_id']) ? $data['refund_id'] : '';

        // Create refund
        $refund = wc_create_refund(array(
            'amount'   => $refund_amount,
            'reason'   => __('Refunded via DEXPAY', 'dexpay-woocommerce'),
            'order_id' => $order->get_id(),
        ));

        if (is_wp_error($refund)) {
            DexPay_Logger::error('Failed to create refund', array(
                'order_id' => $order->get_id(),
                'error'    => $refund->get_error_message(),
            ));
        }

        $order->add_order_note(
            sprintf(
                /* translators: 1: Refund amount, 2: Currency, 3: Refund ID */
                __('DEXPAY refund processed. Amount: %1$s %2$s. Refund ID: %3$s', 'dexpay-woocommerce'),
                $refund_amount,
                $order->get_currency(),
                $refund_id ?: __('N/A', 'dexpay-woocommerce')
            )
        );

        $order->update_meta_data('_dexpay_refund_id', $refund_id);
        $order->save();

        DexPay_Logger::info('Payment refunded', array(
            'order_id'      => $order->get_id(),
            'refund_amount' => $refund_amount,
        ));

        // Trigger WooCommerce action
        do_action('dexpay_payment_refunded', $order, $data);

        return true;
    }

    /**
     * Get order by DEXPAY reference
     *
     * @param array $data Webhook data
     * @return WC_Order|null
     */
    private function get_order_by_reference($data) {
        $reference = isset($data['reference']) ? $data['reference'] : '';

        if (empty($reference)) {
            DexPay_Logger::error('No reference in webhook data');
            return null;
        }

        // Search by meta
        $orders = wc_get_orders(array(
            'meta_key'   => '_dexpay_reference',
            'meta_value' => $reference,
            'limit'      => 1,
        ));

        if (!empty($orders)) {
            return $orders[0];
        }

        // Try to extract order ID from reference (format: WC_ORDERID_TIMESTAMP_RANDOM)
        if (preg_match('/^WC_(\d+)_/', $reference, $matches)) {
            $order_id = intval($matches[1]);
            $order    = wc_get_order($order_id);

            if ($order && $order->get_meta('_dexpay_reference') === $reference) {
                return $order;
            }
        }

        // Check metadata for order ID
        if (isset($data['metadata']['order_id'])) {
            $order = wc_get_order(intval($data['metadata']['order_id']));

            if ($order) {
                return $order;
            }
        }

        DexPay_Logger::error('Order not found for reference', array('reference' => $reference));
        return null;
    }

    /**
     * Send response
     *
     * @param int    $status_code HTTP status code
     * @param string $message     Response message
     */
    private function send_response($status_code, $message) {
        status_header($status_code);
        header('Content-Type: application/json');
        
        echo wp_json_encode(array(
            'status'  => $status_code,
            'message' => $message,
        ));
        
        exit;
    }
}
