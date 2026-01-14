<?php
/**
 * DEXPAY Plugin Updater
 *
 * Handles automatic updates from GitHub releases
 *
 * @package DEXPAY_WooCommerce
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Updater Class
 */
class DexPay_Updater {

    /**
     * GitHub repository owner
     */
    private $github_owner = 'DEXCHANGE-GROUP';

    /**
     * GitHub repository name
     */
    private $github_repo = 'dexpay-woocommerce';

    /**
     * Plugin slug
     */
    private $plugin_slug;

    /**
     * Plugin basename
     */
    private $plugin_basename;

    /**
     * Current version
     */
    private $version;

    /**
     * GitHub API response cache
     */
    private $github_response;

    /**
     * Constructor
     */
    public function __construct() {
        $this->plugin_slug     = 'dexpay-woocommerce';
        $this->plugin_basename = DEXPAY_WC_PLUGIN_BASENAME;
        $this->version         = DEXPAY_WC_VERSION;

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
        
        // Add update check link
        add_filter('plugin_row_meta', array($this, 'add_check_update_link'), 10, 2);
    }

    /**
     * Get GitHub release info
     *
     * @return object|false
     */
    private function get_github_release() {
        if (!empty($this->github_response)) {
            return $this->github_response;
        }

        // Check cache first
        $cached = get_transient('dexpay_github_release');
        if ($cached !== false) {
            $this->github_response = $cached;
            return $cached;
        }

        // Fetch from GitHub API
        $url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->github_owner,
            $this->github_repo
        );

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'DEXPAY-WooCommerce/' . $this->version,
            ),
            'timeout' => 10,
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (empty($data) || !isset($data->tag_name)) {
            return false;
        }

        // Cache for 6 hours
        set_transient('dexpay_github_release', $data, 6 * HOUR_IN_SECONDS);
        
        $this->github_response = $data;
        return $data;
    }

    /**
     * Check for plugin update
     *
     * @param object $transient Update transient
     * @return object
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_github_release();
        if (!$release) {
            return $transient;
        }

        // Get version number (remove 'v' prefix if present)
        $latest_version = ltrim($release->tag_name, 'v');

        // Compare versions
        if (version_compare($this->version, $latest_version, '<')) {
            // Find the zip asset
            $download_url = $this->get_download_url($release);
            
            if ($download_url) {
                $transient->response[$this->plugin_basename] = (object) array(
                    'slug'        => $this->plugin_slug,
                    'plugin'      => $this->plugin_basename,
                    'new_version' => $latest_version,
                    'url'         => 'https://github.com/' . $this->github_owner . '/' . $this->github_repo,
                    'package'     => $download_url,
                    'icons'       => array(
                        '1x' => DEXPAY_WC_PLUGIN_URL . 'assets/images/dexpay.png',
                        '2x' => DEXPAY_WC_PLUGIN_URL . 'assets/images/dexpay.png',
                    ),
                    'banners'     => array(
                        'low'  => DEXPAY_WC_PLUGIN_URL . 'assets/images/banner-772x250.png',
                        'high' => DEXPAY_WC_PLUGIN_URL . 'assets/images/banner-1544x500.png',
                    ),
                    'tested'      => '6.4',
                    'requires_php' => '7.4',
                );
            }
        }

        return $transient;
    }

    /**
     * Get download URL from release
     *
     * @param object $release GitHub release object
     * @return string|false
     */
    private function get_download_url($release) {
        // First, check for a zip asset
        if (!empty($release->assets)) {
            foreach ($release->assets as $asset) {
                if (strpos($asset->name, '.zip') !== false) {
                    return $asset->browser_download_url;
                }
            }
        }

        // Fallback to zipball URL
        if (!empty($release->zipball_url)) {
            return $release->zipball_url;
        }

        return false;
    }

    /**
     * Plugin information for the update screen
     *
     * @param false|object|array $result
     * @param string $action
     * @param object $args
     * @return object|false
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== $this->plugin_slug) {
            return $result;
        }

        $release = $this->get_github_release();
        if (!$release) {
            return $result;
        }

        $latest_version = ltrim($release->tag_name, 'v');

        return (object) array(
            'name'              => 'DEXPAY for WooCommerce',
            'slug'              => $this->plugin_slug,
            'version'           => $latest_version,
            'author'            => '<a href="https://dexpay.africa">DEXPAY</a>',
            'author_profile'    => 'https://dexpay.africa',
            'homepage'          => 'https://dexpay.africa',
            'requires'          => '5.8',
            'tested'            => '6.4',
            'requires_php'      => '7.4',
            'downloaded'        => 0,
            'last_updated'      => $release->published_at,
            'sections'          => array(
                'description'  => $this->get_plugin_description(),
                'installation' => $this->get_installation_instructions(),
                'changelog'    => $this->parse_changelog($release->body),
            ),
            'download_link'     => $this->get_download_url($release),
            'banners'           => array(
                'low'  => DEXPAY_WC_PLUGIN_URL . 'assets/images/banner-772x250.png',
                'high' => DEXPAY_WC_PLUGIN_URL . 'assets/images/banner-1544x500.png',
            ),
        );
    }

    /**
     * Get plugin description
     *
     * @return string
     */
    private function get_plugin_description() {
        return '
            <p>Accept Mobile Money payments in your WooCommerce store with DEXPAY.</p>
            <h4>Supported Payment Methods</h4>
            <ul>
                <li>💳 Card payments (Visa, Mastercard)</li>
                <li>📱 Wave</li>
                <li>🟠 Orange Money</li>
                <li>🟡 MTN Money</li>
                <li>🔵 Moov Money</li>
                <li>🟢 Free Money</li>
                <li>💜 Wizall</li>
            </ul>
            <h4>Supported Countries</h4>
            <p>Senegal, Ivory Coast, Mali, Burkina Faso, Benin, Togo, Niger, Guinea, Cameroon, Gabon, and more.</p>
            <h4>Features</h4>
            <ul>
                <li>Easy setup with API keys</li>
                <li>Sandbox mode for testing</li>
                <li>Automatic order status updates via webhooks</li>
                <li>Client support fee option</li>
                <li>Detailed logging for debugging</li>
            </ul>
        ';
    }

    /**
     * Get installation instructions
     *
     * @return string
     */
    private function get_installation_instructions() {
        return '
            <ol>
                <li>Upload the plugin to the <code>/wp-content/plugins/</code> directory</li>
                <li>Activate the plugin through the "Plugins" menu in WordPress</li>
                <li>Go to WooCommerce → Settings → Payments → DEXPAY</li>
                <li>Enter your API credentials from the <a href="https://app.dexpay.africa" target="_blank">DEXPAY Dashboard</a></li>
                <li>Enable the payment gateway</li>
            </ol>
        ';
    }

    /**
     * Parse changelog from release body
     *
     * @param string $body Release body (markdown)
     * @return string
     */
    private function parse_changelog($body) {
        if (empty($body)) {
            return '<p>No changelog available.</p>';
        }

        // Convert markdown to basic HTML
        $html = nl2br(esc_html($body));
        $html = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $html);
        $html = preg_replace('/^- (.*)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html);

        return $html;
    }

    /**
     * Handle plugin installation
     *
     * @param bool $response
     * @param array $hook_extra
     * @param array $result
     * @return array
     */
    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;

        // Check if this is our plugin
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_basename) {
            return $result;
        }

        // Move to correct directory
        $plugin_dir = WP_PLUGIN_DIR . '/' . $this->plugin_slug;
        $wp_filesystem->move($result['destination'], $plugin_dir);
        $result['destination'] = $plugin_dir;

        // Reactivate plugin
        if (is_plugin_active($this->plugin_basename)) {
            activate_plugin($this->plugin_basename);
        }

        return $result;
    }

    /**
     * Add check for update link
     *
     * @param array $links
     * @param string $file
     * @return array
     */
    public function add_check_update_link($links, $file) {
        if ($file !== $this->plugin_basename) {
            return $links;
        }

        $links[] = sprintf(
            '<a href="%s">%s</a>',
            wp_nonce_url(admin_url('update-core.php?force-check=1'), 'upgrade-core'),
            __('Check for updates', 'dexpay-woocommerce')
        );

        return $links;
    }

    /**
     * Clear update cache
     */
    public static function clear_cache() {
        delete_transient('dexpay_github_release');
        delete_site_transient('update_plugins');
    }
}
