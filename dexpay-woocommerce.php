<?php
/**
 * Plugin Name: DEXPAY for WooCommerce
 * Plugin URI: https://dexpay.africa
 * Description: Accept Mobile Money payments (Wave, Orange Money, MTN, Moov) with DEXPAY payment gateway for WooCommerce.
 * Version: 1.1.0
 * Author: DEXPAY
 * Author URI: https://dexpay.africa
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: dexpay-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.4
 * WC requires at least: 7.0
 * WC tested up to: 8.5
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('DEXPAY_WC_VERSION', '1.1.0');
define('DEXPAY_WC_PLUGIN_FILE', __FILE__);
define('DEXPAY_WC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DEXPAY_WC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DEXPAY_WC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// API URLs
define('DEXPAY_API_URL', 'https://api.dexpay.africa/api/v1');
define('DEXPAY_SANDBOX_API_URL', 'https://api-sandbox.dexpay.africa/api/v1');

/**
 * Main DEXPAY WooCommerce Class
 */
final class DexPay_WooCommerce {

    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Get single instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Check if WooCommerce is active
        add_action('plugins_loaded', array($this, 'init'));
        
        // Register activation/deactivation hooks
        register_activation_hook(DEXPAY_WC_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(DEXPAY_WC_PLUGIN_FILE, array($this, 'deactivate'));
        
        // Add settings link
        add_filter('plugin_action_links_' . DEXPAY_WC_PLUGIN_BASENAME, array($this, 'add_settings_link'));
        
        // HPOS compatibility declaration
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Check if WooCommerce is installed and active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Load text domain
        load_plugin_textdomain('dexpay-woocommerce', false, dirname(DEXPAY_WC_PLUGIN_BASENAME) . '/languages/');

        // Include required files
        $this->includes();

        // Migrations one-time (sur changement de version) — ex: libellés gateway.
        $this->maybe_upgrade();

        // Register payment gateway
        add_filter('woocommerce_payment_gateways', array($this, 'add_gateway'));

        // Register webhook endpoint
        add_action('woocommerce_api_dexpay_webhook', array($this, 'handle_webhook'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Admin scripts
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
    }

    /**
     * Include required files
     */
    private function includes() {
        require_once DEXPAY_WC_PLUGIN_DIR . 'includes/class-dexpay-logger.php';
        require_once DEXPAY_WC_PLUGIN_DIR . 'includes/class-dexpay-api.php';
        require_once DEXPAY_WC_PLUGIN_DIR . 'includes/class-dexpay-gateway.php';
        require_once DEXPAY_WC_PLUGIN_DIR . 'includes/class-dexpay-webhook.php';
        require_once DEXPAY_WC_PLUGIN_DIR . 'includes/class-dexpay-updater.php';
        
        // Initialize updater
        new DexPay_Updater();
    }

    /**
     * Add payment gateway to WooCommerce
     */
    public function add_gateway($gateways) {
        $gateways[] = 'DexPay_Gateway';
        return $gateways;
    }

    /**
     * Handle webhook requests
     */
    public function handle_webhook() {
        $webhook_handler = new DexPay_Webhook();
        $webhook_handler->process();
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        if (is_checkout()) {
            wp_enqueue_style(
                'dexpay-checkout',
                DEXPAY_WC_PLUGIN_URL . 'assets/css/dexpay-checkout.css',
                array(),
                DEXPAY_WC_VERSION
            );
        }
    }

    /**
     * Enqueue admin scripts
     */
    public function admin_enqueue_scripts($hook) {
        if ('woocommerce_page_wc-settings' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'dexpay-admin',
            DEXPAY_WC_PLUGIN_URL . 'assets/css/dexpay-admin.css',
            array(),
            DEXPAY_WC_VERSION
        );
    }

    /**
     * Add settings link on plugins page
     */
    public function add_settings_link($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=wc-settings&tab=checkout&section=dexpay'),
            __('Settings', 'dexpay-woocommerce')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                printf(
                    /* translators: %s: WooCommerce plugin URL */
                    esc_html__('DEXPAY for WooCommerce requires WooCommerce to be installed and active. You can download %s here.', 'dexpay-woocommerce'),
                    '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Declare HPOS compatibility
     */
    public function declare_hpos_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                DEXPAY_WC_PLUGIN_FILE,
                true
            );
        }
    }

    /**
     * Run one-time upgrade migrations when the plugin version changes.
     * Idempotent : ne tourne qu'une fois par version (puis met à jour le flag).
     */
    public function maybe_upgrade() {
        $stored = get_option('dexpay_wc_version');
        if (DEXPAY_WC_VERSION === $stored) {
            return;
        }

        $this->migrate_gateway_labels();

        update_option('dexpay_wc_version', DEXPAY_WC_VERSION);
    }

    /**
     * Met à jour le titre/description du gateway vers le nouveau wording,
     * UNIQUEMENT si la valeur sauvegardée est vide ou correspond encore à une
     * ancienne valeur connue (on ne touche pas une personnalisation marchand).
     */
    private function migrate_gateway_labels() {
        $settings = get_option('woocommerce_dexpay_settings');
        if (!is_array($settings)) {
            return;
        }

        $old_titles = array(
            '',
            'Mobile Money (Wave, Orange Money, MTN...)',
            'Payez avec Mobile Money ou Carte Bancaire (Wave, Orange Money, MTN...)',
        );
        $old_descriptions = array(
            '',
            'Pay securely with Mobile Money via DEXPAY.',
        );

        $new_title       = 'Payez avec DexPay';
        $new_description  = "Paiement sécurisé via Dexpay : Wave, Orange Money, Mixx By Yas etc\n\n";
        $new_description .= "Des frais de transaction peuvent s'appliquer.*";

        $changed = false;

        if (!isset($settings['title']) || in_array($settings['title'], $old_titles, true)) {
            $settings['title'] = $new_title;
            $changed = true;
        }
        if (!isset($settings['description']) || in_array($settings['description'], $old_descriptions, true)) {
            $settings['description'] = $new_description;
            $changed = true;
        }

        if ($changed) {
            update_option('woocommerce_dexpay_settings', $settings);
        }
    }

    /**
     * Activation hook
     */
    public function activate() {
        // Create log table or any required setup
        flush_rewrite_rules();
        
        // Set default options
        if (!get_option('dexpay_wc_installed')) {
            update_option('dexpay_wc_installed', time());
        }
        
        update_option('dexpay_wc_version', DEXPAY_WC_VERSION);
    }

    /**
     * Deactivation hook
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
}

/**
 * Initialize the plugin
 */
function dexpay_woocommerce() {
    return DexPay_WooCommerce::instance();
}

// Start the plugin
dexpay_woocommerce();
