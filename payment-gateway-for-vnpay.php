<?php
/**
 * Plugin Name: Payment Gateway for VNPAY
 * Description: Accept VND payments in WooCommerce through the VNPAY gateway. Independent integration, not affiliated with VNPAY.
 * Version: 1.3.0
 * Author: Futlik
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: payment-gateway-for-vnpay
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PGFV_VERSION', '1.3.0' );
define( 'PGFV_PATH', plugin_dir_path( __FILE__ ) );

// HPOS compatibility.
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        // The gateway supports only the classic (shortcode) checkout, not the Checkout block.
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, false );
    }
} );

add_action( 'plugins_loaded', 'pgfv_init', 11 );

function pgfv_init() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error is-dismissible"><p>'
                . esc_html__( 'Payment Gateway for VNPAY requires WooCommerce to be active.', 'payment-gateway-for-vnpay' )
                . '</p></div>';
        } );
        return;
    }

    $gateway_file  = PGFV_PATH . 'includes/class-pgfv-gateway.php';
    $currency_file = PGFV_PATH . 'includes/class-pgfv-currency-display.php';

    if ( ! file_exists( $gateway_file ) ) {
        return;
    }
    require_once $gateway_file;

    if ( file_exists( $currency_file ) ) {
        require_once $currency_file;
        PGFV_Currency_Display::init();
    }

    // Register the gateway only when the class is actually loaded.
    add_filter( 'woocommerce_payment_gateways', function ( $methods ) {
        $methods[] = 'PGFV_Gateway';
        return $methods;
    } );

    // "Settings" link on the plugins list.
    add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
        $url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=vnpay_gateway' );
        array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'payment-gateway-for-vnpay' ) . '</a>' );
        return $links;
    } );
}
