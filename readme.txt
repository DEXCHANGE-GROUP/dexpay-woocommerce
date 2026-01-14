=== DEXPAY for WooCommerce ===
Contributors: dexpay
Tags: woocommerce, payment gateway, mobile money, wave, orange money, mtn, africa, senegal
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 8.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept Mobile Money payments (Wave, Orange Money, MTN, Moov) in your WooCommerce store with DEXPAY.

== Description ==

**DEXPAY for WooCommerce** is the official payment gateway plugin that allows you to accept Mobile Money payments from customers across Africa.

= Supported Payment Methods =

* **Wave** - Available in Senegal, Ivory Coast, Mali, Burkina Faso
* **Orange Money** - Available in most West African countries
* **MTN Money** - Available in Cameroon, Ivory Coast, and more
* **Moov Money** - Available in Benin, Togo, Niger
* **Free Money** - Available in Senegal

= Supported Currencies =

* XOF (CFA Franc BCEAO)
* XAF (CFA Franc BEAC)
* GNF (Guinean Franc)

= Features =

* **Easy Setup** - Just enter your API keys and start accepting payments
* **Sandbox Mode** - Test your integration without processing real payments
* **Automatic Order Updates** - Orders are automatically updated via webhooks
* **Secure** - All communications are encrypted and webhook signatures are verified
* **HPOS Compatible** - Works with WooCommerce High-Performance Order Storage
* **Multilingual Ready** - Translation-ready with full i18n support
* **Debug Logging** - Comprehensive logging for troubleshooting

= Requirements =

* WordPress 5.8 or higher
* WooCommerce 7.0 or higher
* PHP 7.4 or higher
* SSL certificate (HTTPS)
* DEXPAY merchant account

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/dexpay-woocommerce/` directory, or install through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to WooCommerce > Settings > Payments > DEXPAY to configure the plugin.
4. Enter your API credentials from your [DEXPAY Dashboard](https://app.dexpay.africa/api-keys).
5. Configure your webhook URL in the DEXPAY dashboard.

= Configuration =

1. **Enable DEXPAY** - Check the box to enable the payment gateway.
2. **Sandbox Mode** - Enable for testing with sandbox API keys.
3. **API Credentials** - Enter your production or sandbox API keys.
4. **Webhook Secret** - Enter your webhook secret for signature verification.

== Frequently Asked Questions ==

= Where do I get my API keys? =

Sign up for a DEXPAY account at [app.dexpay.africa](https://app.dexpay.africa/register) and navigate to the API Keys section.

= Can I test the integration before going live? =

Yes! Enable Sandbox Mode in the plugin settings and use your sandbox API keys. No real payments will be processed.

= What currencies are supported? =

DEXPAY currently supports XOF (CFA Franc BCEAO), XAF (CFA Franc BEAC), and GNF (Guinean Franc).

= How do I configure webhooks? =

In your DEXPAY dashboard, add the webhook URL shown in the plugin settings page. The URL format is: `https://yoursite.com/wc-api/dexpay_webhook/`

= Is it secure? =

Yes! All API communications use HTTPS encryption. Webhook signatures are verified using HMAC SHA256 to ensure authenticity.

= Can I use this with WooCommerce Subscriptions? =

Currently, the plugin supports one-time payments only. Subscription support is planned for a future release.

== Screenshots ==

1. Payment gateway settings page
2. Checkout page with DEXPAY payment option
3. Payment methods displayed at checkout
4. Order details with DEXPAY reference

== Changelog ==

= 1.0.0 =
* Initial release
* Support for Wave, Orange Money, MTN, Moov, and Free Money
* Sandbox mode for testing
* Webhook integration for automatic order updates
* HPOS compatibility
* Debug logging

== Upgrade Notice ==

= 1.0.0 =
Initial release of DEXPAY for WooCommerce.

== Support ==

For support, please visit:

* [DEXPAY Documentation](https://docs.dexpay.africa)
* [DEXPAY Support](https://dexpay.africa/support)
* [GitHub Issues](https://github.com/dexpay/dexpay-woocommerce/issues)

== Privacy ==

This plugin sends data to DEXPAY servers to process payments. This includes:

* Order total and currency
* Customer name and email (for payment receipt)
* Order reference number

For more information, see the [DEXPAY Privacy Policy](https://dexpay.africa/privacy).
