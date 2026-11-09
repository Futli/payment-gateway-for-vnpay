# Payment Gateway for VNPAY

Accept payments in Vietnamese dong (VND) in your WooCommerce store through the VNPAY payment gateway: QR code, Vietnamese bank cards and accounts, and international cards.

> **Disclaimer.** This is an independent, community-made integration. It is not affiliated with, endorsed by or sponsored by VNPAY. VNPAY is a trademark of its respective owner and is used here only to say which service the plugin connects to. To accept real payments you need your own merchant contract with VNPAY.

## Features

- Redirect payment to the VNPAY page, with HMAC-SHA512 signed requests
- Signed IPN (server-to-server notification) handling with amount and order checks
- Separate sandbox and live credentials, switchable with one checkbox
- Return URL handler with clear messages for declined payments
- Configurable payment page language, default payment method (QR / local card / international card), payment window, success and failure redirect URLs
- Optional approximate USD price shown next to VND prices (display only, manual exchange rate; customers are always charged in VND)
- HPOS (custom order tables) compatible
- Translations: English, Tiếng Việt, Русский

## Requirements

- WordPress 6.5+ and WooCommerce 7.0+
- PHP 7.4+
- Store currency set to **VND**
- The **classic checkout**: on the Checkout page use the `[woocommerce_checkout]` shortcode. The Checkout block is not supported yet.
- A publicly reachable HTTPS site, so VNPAY can deliver IPN notifications

## Installation

1. Download this repository as a ZIP and upload it in **Plugins → Add New → Upload Plugin**, or copy the folder into `wp-content/plugins/`.
2. Activate **Payment Gateway for VNPAY**.
3. Open **WooCommerce → Settings → Payments → VNPAY**.
4. Enter your Sandbox TmnCode and HashSecret and keep **Test mode** enabled.
5. Copy the **IPN URL** shown at the top of the settings page and register it in the VNPAY merchant portal.
6. Make a test order, then switch to your Live credentials.

## How it works

1. The customer picks VNPAY at checkout and places the order.
2. The plugin builds a signed request and redirects the customer to the VNPAY payment page.
3. After payment VNPAY calls your **IPN URL**. The plugin verifies the signature, the amount and the order status, and marks the order as paid.
4. The customer is sent back to your store and sees the order confirmation or a clear error message.

## Troubleshooting

| Problem | What to check |
|---|---|
| VNPAY is not shown at checkout | Store currency is VND; TmnCode and HashSecret are filled in for the selected mode; the Checkout page uses `[woocommerce_checkout]`, not the block |
| Order stays *Pending payment* | The IPN URL is not registered in the VNPAY portal, or the site is not reachable over public HTTPS (local sites need a tunnel) |
| "Invalid checksum" | HashSecret does not match the selected mode (sandbox and live keys differ), or has extra spaces |
| Wrong amount | Only VND is supported; the optional USD price is display-only |

## Uninstall

Deleting the plugin from the Plugins screen removes the gateway settings, including credentials.

## Checking the plugin before release

Install the official [Plugin Check](https://wordpress.org/plugins/plugin-check/) plugin, open **Tools → Plugin Check**, select *Payment Gateway for VNPAY* and run all categories. Fix every error before submitting to WordPress.org.

## Testing checklist

- Successful payment moves the order to *Processing* and stores the VNPAY transaction number
- Cancelled, expired and failed payments show a message and let the customer retry
- IPN with a wrong signature or a changed amount is rejected and the order is not changed
- A repeated IPN does not pay the order twice

## Roadmap

See the issues of this repository. A Pro version with extra features is planned.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
