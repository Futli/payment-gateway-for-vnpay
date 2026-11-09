=== Payment Gateway for VNPAY ===
Contributors: futlik
Tags: vnpay, woocommerce, vietnam, payment gateway, vnd
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept VND payments in WooCommerce through the VNPAY gateway: QR code, Vietnamese bank cards and international cards.

== Description ==

**Payment Gateway for VNPAY** adds VNPAY as a payment method to your WooCommerce store. At checkout the customer is sent to the secure VNPAY page, pays there, and comes back to your site. Your order is confirmed automatically through a signed server-to-server notification (IPN), so a closed browser tab never leaves a paid order unconfirmed.

This is an independent integration. It is not affiliated with, endorsed by or sponsored by VNPAY. VNPAY is a trademark of its respective owner. You need your own VNPAY merchant account to accept real payments.

= How it works =

1. The customer chooses VNPAY at checkout and presses the order button.
2. The plugin creates a signed payment request and redirects the customer to VNPAY.
3. After paying, VNPAY sends the result to your site (IPN). The plugin checks the signature, the amount and the order status, then marks the order as paid.
4. The customer returns to your site and sees the order confirmation, or a clear message if the payment failed.

= Features =

* Redirect payment with signed requests (HMAC-SHA512)
* IPN handling with signature, amount and order status checks
* Separate sandbox and live credentials
* Return URL handler with clear messages for declined payments
* Payment page language, default payment method (QR, local card, international card), payment window and redirect URLs are configurable
* Optional approximate USD price next to VND prices (display only, manual exchange rate)
* HPOS (custom order tables) compatible
* Translations: English, Vietnamese, Russian

= Requirements =

* Store currency: VND
* Classic checkout. On the Checkout page, use the `[woocommerce_checkout]` shortcode. The Checkout block is not supported yet.
* A publicly reachable HTTPS site, so VNPAY can send IPN notifications

= Source code =

The full, human-readable source code is published at https://github.com/YOUR-GITHUB-USERNAME/payment-gateway-for-vnpay

== External services ==

This plugin connects to the VNPAY payment gateway to process payments. When a customer chooses VNPAY at checkout, the plugin sends the following data to VNPAY: order amount and currency, a transaction reference made of the order ID and a timestamp, a short order description, the customer's IP address, the creation and expiry time of the payment, and your merchant terminal code. VNPAY returns the payment result to your site through the return URL and IPN URL.

* Live endpoint: https://pay.vnpay.vn
* Sandbox endpoint (test mode): https://sandbox.vnpayment.vn
* Service provider: VNPAY (Vietnam Payment Solution Joint Stock Company)
* Terms of service: https://vnpay.vn/chinh-sach/dieu-khoan-dieu-kien-sddv-vnpay-bsddv.html

The plugin does not track visitors and does not contact any other external server.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it from the Plugins screen, then activate it.
2. Set the store currency to VND (WooCommerce → Settings → General).
3. Make sure the Checkout page uses the `[woocommerce_checkout]` shortcode instead of the Checkout block.
4. Go to WooCommerce → Settings → Payments → VNPAY.
5. Enter your Sandbox TmnCode and HashSecret and keep Test mode on while testing.
6. Copy the IPN URL shown at the top of the settings page and register it in the VNPAY merchant portal.
7. Place a test order. When everything works, enter your Live credentials and turn Test mode off.

== Frequently Asked Questions ==

= Does it work with the Checkout block? =

Not yet. Replace the block with the `[woocommerce_checkout]` shortcode on the Checkout page.

= Why is VNPAY not shown at checkout? =

Check three things: the store currency is VND, the TmnCode and HashSecret for the selected mode (sandbox or live) are filled in, and the Checkout page uses the shortcode, not the block. The settings page shows a warning for each of these.

= The order stays "Pending payment" after a successful payment. =

VNPAY could not deliver the IPN. Register the IPN URL from the settings page in the VNPAY merchant portal. The site must be reachable from the internet over HTTPS. A local development site needs a public tunnel.

= I get "Invalid checksum". =

The HashSecret does not match the selected mode. Sandbox and live use different keys. Check for extra spaces and re-enter the key.

= Can customers pay in USD? =

No. VNPAY charges in VND. The optional USD amount shown next to prices is only an approximation based on the exchange rate you enter.

= Is this the official VNPAY plugin? =

No. It is an independent integration and is not affiliated with VNPAY.

= What happens to my settings if I delete the plugin? =

The gateway settings, including credentials, are removed when you delete the plugin from the Plugins screen.

== Changelog ==

= 1.3.0 =
* Developer hooks for add-ons: pgfv_form_fields, pgfv_supports, pgfv_currency_supported, pgfv_order_vnd_total, pgfv_payment_args, pgfv_process_refund, pgfv_ipn_processed

= 1.2.0 =
* Separate sandbox and live credentials
* New settings: payment page language, default payment method, payment window, redirect URLs
* Return URL handler with error messages
* Approximate USD price display
* Settings and data are removed on uninstall
* Translations: English, Vietnamese, Russian
* Plugin Check fixes: translators comments, translations are loaded by WordPress itself
* Security hardening: per-order lock for IPN, late declined notices for earlier attempts are ignored, merchant code check, quieter logs for invalid requests

== Upgrade Notice ==

= 1.2.0 =
Sandbox and live credentials are now separate fields. Re-enter your sandbox keys in the new Sandbox fields after updating.
