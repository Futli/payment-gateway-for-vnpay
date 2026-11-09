<?php
/**
 * Removes plugin data when the plugin is deleted from the Plugins screen.
 *
 * @package Payment_Gateway_For_VNPAY
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Gateway settings (credentials and options).
delete_option( 'woocommerce_vnpay_gateway_settings' );
