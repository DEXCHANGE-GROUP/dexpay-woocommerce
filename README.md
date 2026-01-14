# DEXPAY for WooCommerce

![DEXPAY](assets/images/dexpay.png)

Accept Mobile Money and Card payments in your WooCommerce store with DEXPAY - the leading payment gateway for Africa.

[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-7.0%2B-purple)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2-green)](LICENSE)

## 🚀 Installation

### Method 1: Upload via WordPress Admin

1. Download the latest release from [GitHub Releases](https://github.com/DEXCHANGE-GROUP/dexpay-woocommerce/releases)
2. Go to **WordPress Admin → Plugins → Add New → Upload Plugin**
3. Select the downloaded ZIP file and click **Install Now**
4. Click **Activate Plugin**

### Method 2: Manual Installation

1. Download and extract the plugin:

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/DEXCHANGE-GROUP/dexpay-woocommerce.git
```

2. Activate the plugin in **WordPress Admin → Plugins**

### Method 3: Composer (for developers)

```bash
composer require dexchange-group/dexpay-woocommerce
```

## ⚙️ Configuration

1. Go to **WooCommerce → Settings → Payments**
2. Click **Manage** next to **DEXPAY**
3. Configure your settings:

| Setting              | Description                                             |
| -------------------- | ------------------------------------------------------- |
| **Enable/Disable**   | Turn the payment gateway on/off                         |
| **Title**            | Payment method title shown at checkout                  |
| **Description**      | Description shown at checkout                           |
| **Sandbox Mode**     | Enable for testing (no real charges)                    |
| **API Key**          | Your DEXPAY API Key (`pk_live_xxx` or `pk_test_xxx`)    |
| **API Secret**       | Your DEXPAY API Secret (`sk_live_xxx` or `sk_test_xxx`) |
| **Webhook Secret**   | Secret for webhook signature verification               |
| **Client Pays Fees** | Add transaction fees to customer's total                |
| **Debug Log**        | Enable logging for troubleshooting                      |

4. Click **Save changes**

## 🔑 Getting API Keys

1. Sign up at [app.dexpay.africa](https://app.dexpay.africa/register)
2. Complete your merchant verification
3. Navigate to **API Keys** section
4. Copy your API Key and API Secret

> **💡 Tip:** Use Sandbox keys (`pk_test_xxx`, `sk_test_xxx`) for testing before going live.

## 🔗 Webhook Configuration

Configure this webhook URL in your [DEXPAY Dashboard](https://app.dexpay.africa):

```
https://your-store.com/wc-api/dexpay_webhook/
```

The plugin will automatically update order statuses when payments are completed, failed, or refunded.

## 🧪 Testing in Sandbox Mode

1. Enable **Sandbox Mode** in the plugin settings
2. Use your Sandbox API credentials
3. Make test purchases on your store
4. Check the [DEXPAY Dashboard](https://app.dexpay.africa) to see test transactions

> **Note:** In sandbox mode, no real money is charged. Perfect for development and testing!

## 🔄 Automatic Updates

The plugin checks for updates from GitHub automatically. When a new version is available:

1. Go to **WordPress Admin → Dashboard → Updates**
2. You'll see DEXPAY listed if an update is available
3. Click **Update Now**

Or check manually via **Plugins → DEXPAY for WooCommerce → Check for updates**

## 📋 Requirements

- WordPress 5.8 or higher
- WooCommerce 7.0 or higher
- PHP 7.4 or higher
- SSL certificate (HTTPS required for production)
- Store currency set to XOF, XAF, or GNF

## 🐛 Troubleshooting

### Payment method not showing at checkout?

1. Check that your store currency is XOF, XAF, or GNF
2. Verify API credentials are entered correctly
3. Enable Debug Log and check `wp-content/uploads/wc-logs/dexpay-*.log`

### Webhooks not working?

1. Ensure your webhook URL is accessible (not behind authentication)
2. Verify the Webhook Secret matches your DEXPAY Dashboard
3. Check your server firewall isn't blocking incoming webhooks

### Orders stuck in "Pending"?

1. Check webhook configuration
2. Verify SSL certificate is valid
3. Review debug logs for errors

## 📝 Changelog

### v1.0.0 (2024)

- Initial release
- Support for Card and Mobile Money payments
- Sandbox mode for testing
- Automatic updates from GitHub
- Multi-language support (FR/EN)
- Client support fee option
- Webhook integration
- WooCommerce HPOS compatibility

## 🤝 Contributing

Contributions are welcome! Please read our [Contributing Guide](CONTRIBUTING.md) first.

```bash
# Clone the repository
git clone https://github.com/DEXCHANGE-GROUP/dexpay-woocommerce.git

# Install dependencies (if any)
composer install

# Make your changes and submit a PR
```

## 📄 License

This plugin is licensed under the [GPL v2 or later](LICENSE).

## 🆘 Support

- 📧 **Email:** support@dexpay.africa
- 📖 **Documentation:** [docs.dexpay.africa](https://docs.dexpay.africa)
- 🐛 **Issues:** [GitHub Issues](https://github.com/DEXCHANGE-GROUP/dexpay-woocommerce/issues)
- 💬 **Dashboard:** [app.dexpay.africa](https://app.dexpay.africa)

## 🔗 Links

- [DEXPAY Website](https://dexpay.africa)
- [DEXPAY Dashboard](https://app.dexpay.africa)
- [API Documentation](https://docs.dexpay.africa)
- [Node.js SDK](https://www.npmjs.com/package/@dexchangepay/node)

---

Made with ❤️ by [DEXPAY](https://dexpay.africa) - Built for Africa. Ready for the world.
