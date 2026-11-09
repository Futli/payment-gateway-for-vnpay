<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PGFV_Gateway extends WC_Payment_Gateway {

    const LOG_SOURCE = 'vnpay';

    public $testmode;
    public $tmn_code;
    public $hash_secret;
    public $vnp_locale;
    public $vnp_order_type;
    public $expire_minutes;
    public $bank_code;
    public $order_info_prefix;
    public $success_url;
    public $failure_url;
    public $debug;

    public function __construct() {
        $this->id                 = 'vnpay_gateway';
        $this->icon               = '';
        $this->has_fields         = false;
        $this->method_title       = 'VNPAY';
        $this->method_description = __( 'VNPAY online payment gateway for WooCommerce', 'payment-gateway-for-vnpay' );
        // Add-ons can extend this list, for example with 'refunds'.
        $this->supports           = apply_filters( 'pgfv_supports', array( 'products' ) );

        $this->init_form_fields();
        // Add-ons can append their own settings fields.
        $this->form_fields = apply_filters( 'pgfv_form_fields', $this->form_fields );
        $this->init_settings();

        $this->enabled     = $this->get_option( 'enabled' );
        $this->title       = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        $this->testmode    = 'yes' === $this->get_option( 'testmode' );

        // Credentials depend on the mode: sandbox and live use different ones.
        $this->tmn_code    = trim( $this->get_option( $this->testmode ? 'test_tmn_code' : 'tmn_code' ) );
        $this->hash_secret = trim( $this->get_option( $this->testmode ? 'test_hash_secret' : 'hash_secret' ) );

        $this->vnp_locale        = $this->get_option( 'locale', 'vn' );
        $this->vnp_order_type    = $this->get_option( 'order_type', 'other' );
        $this->expire_minutes    = min( 1440, max( 5, absint( $this->get_option( 'expire_minutes', 15 ) ) ) );
        $this->bank_code         = $this->get_option( 'bank_code', '' );
        $this->order_info_prefix = $this->get_option( 'order_info_prefix', 'Thanh toan don hang ' );
        $this->success_url       = trim( $this->get_option( 'success_url' ) );
        $this->failure_url       = trim( $this->get_option( 'failure_url' ) );
        $this->debug             = 'yes' === $this->get_option( 'debug' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_api_vnpay_gateway', array( $this, 'handle_ipn_response' ) );
        add_action( 'woocommerce_api_vnpay_return', array( $this, 'handle_return' ) );
    }

    /* ---------------------------------------------------------------------
     * Settings
     * ------------------------------------------------------------------ */

    public function init_form_fields() {
        $this->form_fields = array(
            'section_main' => array(
                'title' => __( 'Main', 'payment-gateway-for-vnpay' ),
                'type'  => 'title',
            ),
            'enabled' => array(
                'title'   => __( 'Enable/Disable', 'payment-gateway-for-vnpay' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable VNPAY payment gateway', 'payment-gateway-for-vnpay' ),
                'default' => 'yes',
            ),
            'title' => array(
                'title'       => __( 'Title', 'payment-gateway-for-vnpay' ),
                'type'        => 'text',
                'description' => __( 'Payment method name shown at checkout.', 'payment-gateway-for-vnpay' ),
                'default'     => 'VNPAY',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Description', 'payment-gateway-for-vnpay' ),
                'type'        => 'textarea',
                'description' => __( 'Payment method description shown to the customer.', 'payment-gateway-for-vnpay' ),
                'default'     => __( 'Secure card payment via VNPAY.', 'payment-gateway-for-vnpay' ),
            ),

            'section_credentials' => array(
                'title'       => __( 'VNPAY credentials', 'payment-gateway-for-vnpay' ),
                'type'        => 'title',
                'description' => __( 'VNPAY issues different TmnCode and HashSecret values for the sandbox and for live mode. The ones matching the selected mode are used.', 'payment-gateway-for-vnpay' ),
            ),
            'testmode' => array(
                'title'   => __( 'Test mode (Sandbox)', 'payment-gateway-for-vnpay' ),
                'type'    => 'checkbox',
                'label'   => __( 'Use the VNPAY test gateway', 'payment-gateway-for-vnpay' ),
                'default' => 'yes',
            ),
            'test_tmn_code' => array(
                'title'       => __( 'Sandbox: Merchant ID (TmnCode)', 'payment-gateway-for-vnpay' ),
                'type'        => 'text',
                'description' => __( 'Terminal ID from the VNPAY sandbox email.', 'payment-gateway-for-vnpay' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'test_hash_secret' => array(
                'title'       => __( 'Sandbox: Secret Key (HashSecret)', 'payment-gateway-for-vnpay' ),
                'type'        => 'password',
                'description' => __( 'Sandbox secret key used to sign requests.', 'payment-gateway-for-vnpay' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'tmn_code' => array(
                'title'       => __( 'Live: Merchant ID (TmnCode)', 'payment-gateway-for-vnpay' ),
                'type'        => 'text',
                'description' => __( 'Live terminal ID issued by VNPAY after onboarding.', 'payment-gateway-for-vnpay' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'hash_secret' => array(
                'title'       => __( 'Live: Secret Key (HashSecret)', 'payment-gateway-for-vnpay' ),
                'type'        => 'password',
                'description' => __( 'Live secret key used to sign requests.', 'payment-gateway-for-vnpay' ),
                'default'     => '',
                'desc_tip'    => true,
            ),

            'section_payment' => array(
                'title' => __( 'Payment parameters', 'payment-gateway-for-vnpay' ),
                'type'  => 'title',
            ),
            'locale' => array(
                'title'       => __( 'Payment page language', 'payment-gateway-for-vnpay' ),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __( 'Language of the VNPAY page the customer is sent to.', 'payment-gateway-for-vnpay' ),
                'default'     => 'vn',
                'options'     => array(
                    'vn' => 'Tiếng Việt',
                    'en' => 'English',
                ),
                'desc_tip'    => true,
            ),
            'bank_code' => array(
                'title'       => __( 'Default payment method', 'payment-gateway-for-vnpay' ),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __( 'If a specific method is chosen, the customer goes straight to it and skips the selection on the VNPAY side.', 'payment-gateway-for-vnpay' ),
                'default'     => '',
                'options'     => array(
                    ''        => __( 'Customer chooses on the VNPAY page', 'payment-gateway-for-vnpay' ),
                    'VNPAYQR' => __( 'QR code (VNPAYQR)', 'payment-gateway-for-vnpay' ),
                    'VNBANK'  => __( 'Vietnamese card or account (VNBANK)', 'payment-gateway-for-vnpay' ),
                    'INTCARD' => __( 'International card (INTCARD)', 'payment-gateway-for-vnpay' ),
                ),
                'desc_tip'    => true,
            ),
            'order_type' => array(
                'title'       => __( 'Order category (vnp_OrderType)', 'payment-gateway-for-vnpay' ),
                'type'        => 'text',
                'description' => __( 'Category code from the VNPAY documentation. The value "other" fits any store.', 'payment-gateway-for-vnpay' ),
                'default'     => 'other',
                'desc_tip'    => true,
            ),
            'order_info_prefix' => array(
                'title'       => __( 'Payment description (vnp_OrderInfo)', 'payment-gateway-for-vnpay' ),
                'type'        => 'text',
                'description' => __( 'Text placed before the order number. Use Latin letters without diacritics.', 'payment-gateway-for-vnpay' ),
                'default'     => 'Thanh toan don hang ',
                'desc_tip'    => true,
            ),
            'expire_minutes' => array(
                'title'       => __( 'Payment window, minutes', 'payment-gateway-for-vnpay' ),
                'type'        => 'text',
                'description' => __( 'How many minutes the payment link stays valid (5 to 1440).', 'payment-gateway-for-vnpay' ),
                'default'     => '15',
                'desc_tip'    => true,
            ),

            'section_redirects' => array(
                'title'       => __( 'Redirects after payment', 'payment-gateway-for-vnpay' ),
                'type'        => 'title',
                'description' => __( 'URLs must belong to your site. If empty, the standard WooCommerce pages are used.', 'payment-gateway-for-vnpay' ),
            ),
            'success_url' => array(
                'title'       => __( 'Success URL', 'payment-gateway-for-vnpay' ),
                'type'        => 'text',
                'description' => __( 'Default: the "Order received" page.', 'payment-gateway-for-vnpay' ),
                'default'     => '',
                'placeholder' => 'https://example.com/thank-you/',
            ),
            'failure_url' => array(
                'title'       => __( 'Failure URL', 'payment-gateway-for-vnpay' ),
                'type'        => 'text',
                'description' => __( 'Default: the order payment page, where the customer can try again.', 'payment-gateway-for-vnpay' ),
                'default'     => '',
                'placeholder' => 'https://example.com/payment-failed/',
            ),

            'section_usd' => array(
                'title'       => __( 'USD price display', 'payment-gateway-for-vnpay' ),
                'type'        => 'title',
                'description' => __( 'Shows an approximate dollar amount next to prices in VND. For information only: the customer is always charged in VND.', 'payment-gateway-for-vnpay' ),
            ),
            'usd_display' => array(
                'title'   => __( 'Show USD next to VND', 'payment-gateway-for-vnpay' ),
                'type'    => 'checkbox',
                'label'   => __( 'Show the price in USD next to the price in VND', 'payment-gateway-for-vnpay' ),
                'default' => 'no',
            ),
            'usd_rate' => array(
                'title'       => __( 'Exchange rate (VND per 1 USD)', 'payment-gateway-for-vnpay' ),
                'type'        => 'text',
                'description' => __( 'Enter the rate manually, for example 25000. Nothing is shown while the field is empty.', 'payment-gateway-for-vnpay' ),
                'default'     => '',
                'desc_tip'    => true,
            ),

            'section_debug' => array(
                'title' => __( 'Debugging', 'payment-gateway-for-vnpay' ),
                'type'  => 'title',
            ),
            'debug' => array(
                'title'       => __( 'Detailed log', 'payment-gateway-for-vnpay' ),
                'type'        => 'checkbox',
                'label'       => __( 'Write requests to the WooCommerce log (source: vnpay)', 'payment-gateway-for-vnpay' ),
                'description' => __( 'Enable only while testing. Secret keys and signatures are never logged.', 'payment-gateway-for-vnpay' ),
                'default'     => 'no',
            ),
        );
    }

    /**
     * Keeps only digits from the exchange rate: "25,000" and "25.000" both become 25000.
     */
    public function validate_usd_rate_field( $key, $value ) {
        $value = preg_replace( '/[^0-9.,]/', '', (string) $value );
        if ( preg_match( '/^\d{1,3}([.,]\d{3})+$/', $value ) ) {
            $value = preg_replace( '/[.,]/', '', $value );
        }
        return str_replace( ',', '.', $value );
    }

    public function admin_options() {
        echo '<h2>' . esc_html( $this->get_method_title() ) . '</h2>';
        echo wp_kses_post( wpautop( $this->get_method_description() ) );

        if ( ! $this->is_currency_supported() ) {
            echo '<div class="notice notice-error inline"><p><strong>'
                . esc_html__( 'VNPAY works only with VND.', 'payment-gateway-for-vnpay' )
                . '</strong> '
                /* translators: %s: store currency code, for example USD */
                . esc_html( sprintf( __( 'The store currency is %s, so the gateway is hidden at checkout. Change it in WooCommerce → Settings → General.', 'payment-gateway-for-vnpay' ), get_woocommerce_currency() ) )
                . '</p></div>';
        }

        $shortcode = '<code>[woocommerce_checkout]</code>';
        $page_id   = wc_get_page_id( 'checkout' );

        if ( $page_id > 0 && has_block( 'woocommerce/checkout', $page_id ) ) {
            /* translators: %s: the [woocommerce_checkout] shortcode */
            $message = sprintf( esc_html__( 'The Checkout page currently uses the Checkout block, so VNPAY will not appear at checkout. Replace the block with the %s shortcode.', 'payment-gateway-for-vnpay' ), $shortcode );
            echo '<div class="notice notice-error inline"><p>' . wp_kses_post( $message ) . '</p></div>';
        } else {
            /* translators: %s: the [woocommerce_checkout] shortcode */
            $message = sprintf( esc_html__( 'This gateway supports only the classic checkout. On the Checkout page, use the %s shortcode instead of the Checkout block.', 'payment-gateway-for-vnpay' ), $shortcode );
            echo '<div class="notice notice-warning inline"><p>' . wp_kses_post( $message ) . '</p></div>';
        }

        echo '<div class="notice notice-info inline"><p><strong>'
            . esc_html__( 'IPN URL (set it in the VNPAY merchant portal):', 'payment-gateway-for-vnpay' )
            . '</strong> <code>' . esc_html( WC()->api_request_url( 'vnpay_gateway' ) ) . '</code></p>'
            . '<p><strong>' . esc_html__( 'The Return URL is generated automatically:', 'payment-gateway-for-vnpay' )
            . '</strong> <code>' . esc_html( WC()->api_request_url( 'vnpay_return' ) ) . '</code></p>'
            . '<p>' . esc_html__( 'The site must be reachable from the internet over HTTPS, otherwise VNPAY cannot deliver the IPN.', 'payment-gateway-for-vnpay' ) . '</p></div>';

        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
    }

    public function is_available() {
        if ( empty( $this->tmn_code ) || empty( $this->hash_secret ) ) {
            return false;
        }
        if ( ! $this->is_currency_supported() ) {
            return false;
        }
        return parent::is_available();
    }

    /* ---------------------------------------------------------------------
     * Payment
     * ------------------------------------------------------------------ */

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            wc_add_notice( __( 'Order not found.', 'payment-gateway-for-vnpay' ), 'error' );
            return array( 'result' => 'failure' );
        }

        if ( empty( $this->tmn_code ) || empty( $this->hash_secret ) ) {
            $this->log( 'error', 'TmnCode or HashSecret is empty for the current mode.' );
            wc_add_notice( __( 'VNPAY payment is temporarily unavailable. Please choose another method.', 'payment-gateway-for-vnpay' ), 'error' );
            return array( 'result' => 'failure' );
        }

        $tz  = new DateTimeZone( 'Asia/Ho_Chi_Minh' );
        $now = new DateTime( 'now', $tz );

        $create_date = $now->format( 'YmdHis' );
        $expire_date = $now->modify( '+' . $this->expire_minutes . ' minutes' )->format( 'YmdHis' );

        $txn_ref = $order_id . '_' . time();

        $ip = WC_Geolocation::get_ip_address();
        if ( empty( $ip ) || '::1' === $ip ) {
            $ip = '127.0.0.1';
        }

        $order_info = preg_replace(
            '/[^A-Za-z0-9 #:._-]/',
            '',
            remove_accents( $this->order_info_prefix . $order->get_order_number() )
        );

        $input = array(
            'vnp_Version'    => '2.1.0',
            'vnp_Command'    => 'pay',
            'vnp_TmnCode'    => $this->tmn_code,
            'vnp_Amount'     => $this->get_order_vnp_amount( $order, 'payment' ),
            'vnp_CreateDate' => $create_date,
            'vnp_ExpireDate' => $expire_date,
            'vnp_CurrCode'   => 'VND',
            'vnp_IpAddr'     => $ip,
            'vnp_Locale'     => $this->vnp_locale,
            'vnp_OrderInfo'  => $order_info,
            'vnp_OrderType'  => $this->vnp_order_type,
            'vnp_ReturnUrl'  => WC()->api_request_url( 'vnpay_return' ),
            'vnp_TxnRef'     => $txn_ref,
        );

        if ( ! empty( $this->bank_code ) ) {
            $input['vnp_BankCode'] = $this->bank_code;
        }

        // Add-ons can adjust the request parameters (the signature is built after this filter).
        $input = apply_filters( 'pgfv_payment_args', $input, $order, $this );

        $query = $this->build_query( $input );
        $hash  = hash_hmac( 'sha512', $query, $this->hash_secret );
        $base  = $this->testmode
            ? 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'
            : 'https://pay.vnpay.vn/vpcpay.html';
        $url   = $base . '?' . $query . '&vnp_SecureHash=' . $hash;

        $order->update_meta_data( '_vnpay_txn_ref', $txn_ref );

        // Remember every reference issued for this order, so a late IPN for an earlier attempt is still recognised.
        $refs = $order->get_meta( '_vnpay_txn_refs' );
        $refs = is_array( $refs ) ? $refs : array();
        $refs[] = $txn_ref;
        $order->update_meta_data( '_vnpay_txn_refs', array_slice( array_values( array_unique( $refs ) ), -20 ) );

        /* translators: %s: VNPAY transaction reference */
        $order->add_order_note( sprintf( __( 'Customer redirected to VNPAY. Reference: %s', 'payment-gateway-for-vnpay' ), $txn_ref ) );
        $order->save();

        $this->log( 'info', 'Payment created for order #' . $order_id . ', reference ' . $txn_ref . ', mode: ' . ( $this->testmode ? 'sandbox' : 'live' ) );
        if ( $this->debug ) {
            // The signature is intentionally not logged.
            $this->log( 'debug', 'Request parameters: ' . wp_json_encode( $input ) );
        }

        return array(
            'result'   => 'success',
            'redirect' => $url,
        );
    }

    /* ---------------------------------------------------------------------
     * Return URL: where VNPAY sends the customer back
     * ------------------------------------------------------------------ */

    public function handle_return() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Callback from the payment provider, authenticated by its HMAC signature. Raw values are required to verify that signature.
        $data = $this->extract_vnp_params( wp_unslash( $_GET ) );

        if ( empty( $data ) || ! $this->is_signature_valid( $data ) ) {
            // Logged only in debug mode: this public URL can be hit by anyone and must not flood the logs.
            $this->log( 'info', 'Return URL: invalid signature or empty request.' );
            wc_add_notice( __( 'Could not verify the payment data. If you were charged, please contact the store.', 'payment-gateway-for-vnpay' ), 'error' );
            wp_safe_redirect( wc_get_checkout_url() );
            exit;
        }

        $order = $this->get_order_by_txn_ref( $data['vnp_TxnRef'] ?? '' );
        if ( ! $order ) {
            wc_add_notice( __( 'Order not found.', 'payment-gateway-for-vnpay' ), 'error' );
            wp_safe_redirect( wc_get_checkout_url() );
            exit;
        }

        $response_code      = $data['vnp_ResponseCode'] ?? '';
        $transaction_status = $data['vnp_TransactionStatus'] ?? '';

        if ( '00' === $response_code && '00' === $transaction_status ) {
            // Only the IPN changes the order status: it comes from the VNPAY server and is signed.
            $target = $this->success_url ? $this->success_url : $this->get_return_url( $order );
            wp_safe_redirect( $target );
            exit;
        }

        $this->log( 'info', 'Return URL: payment for order #' . $order->get_id() . ' failed, code ' . $response_code );
        wc_add_notice( $this->get_error_message( $response_code ), 'error' );

        $target = $this->failure_url ? $this->failure_url : $order->get_checkout_payment_url();
        wp_safe_redirect( $target );
        exit;
    }

    /* ---------------------------------------------------------------------
     * IPN: server-to-server notification from VNPAY
     * ------------------------------------------------------------------ */

    public function handle_ipn_response() {
        $this->log( 'info', 'VNPAY IPN received.' );

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Callback from the payment provider, authenticated by its HMAC signature. Raw values are required to verify that signature.
        $data = $this->extract_vnp_params( wp_unslash( $_GET ) );

        if ( empty( $data ) || ! $this->is_signature_valid( $data ) ) {
            // Logged only in debug mode: this public URL can be hit by anyone and must not flood the logs.
            $this->log( 'info', 'IPN: invalid checksum.' );
            wp_send_json( array( 'RspCode' => '97', 'Message' => 'Invalid checksum' ) );
        }

        if ( ! isset( $data['vnp_TxnRef'], $data['vnp_Amount'], $data['vnp_ResponseCode'] ) ) {
            wp_send_json( array( 'RspCode' => '99', 'Message' => 'Invalid request' ) );
        }

        // The merchant code must be ours (the signature already binds it to our secret; this is an extra check).
        if ( isset( $data['vnp_TmnCode'] ) && $data['vnp_TmnCode'] !== $this->tmn_code ) {
            $this->log( 'warning', 'IPN: unexpected merchant code.' );
            wp_send_json( array( 'RspCode' => '99', 'Message' => 'Invalid request' ) );
        }

        $order = $this->get_order_by_txn_ref( $data['vnp_TxnRef'] );
        if ( ! $order || ! $this->is_known_txn_ref( $order, $data['vnp_TxnRef'] ) ) {
            $this->log( 'warning', 'IPN: order not found, reference ' . $data['vnp_TxnRef'] );
            wp_send_json( array( 'RspCode' => '01', 'Message' => 'Order not found' ) );
        }

        if ( $this->get_order_vnp_amount( $order, 'verify' ) !== (int) $data['vnp_Amount'] ) {
            $this->log( 'warning', 'IPN: amount mismatch for order #' . $order->get_id() );
            wp_send_json( array( 'RspCode' => '04', 'Message' => 'Invalid amount' ) );
        }

        // Two identical IPNs arriving at once must not both be processed.
        $order_id = $order->get_id();
        if ( ! $this->acquire_order_lock( $order_id ) ) {
            wp_send_json( array( 'RspCode' => '02', 'Message' => 'Order already confirmed' ) );
        }
        register_shutdown_function( array( $this, 'release_order_lock' ), $order_id );

        // Reload the order after taking the lock, so the status check sees the latest state.
        $order = wc_get_order( $order_id );
        if ( ! $order || ! $order->has_status( array( 'pending', 'on-hold', 'failed' ) ) ) {
            wp_send_json( array( 'RspCode' => '02', 'Message' => 'Order already confirmed' ) );
        }

        $transaction_status = $data['vnp_TransactionStatus'] ?? '';
        $transaction_no     = $data['vnp_TransactionNo'] ?? '';
        $is_success         = '00' === $data['vnp_ResponseCode'] && '00' === $transaction_status;

        // A late "declined" notice for an earlier attempt must not overwrite a newer attempt.
        if ( ! $is_success && $data['vnp_TxnRef'] !== (string) $order->get_meta( '_vnpay_txn_ref' ) ) {
            $this->log( 'info', 'IPN: ignored declined notice for an earlier attempt, order #' . $order_id );
            wp_send_json( array( 'RspCode' => '00', 'Message' => 'Confirm Success' ) );
        }

        if ( $is_success ) {
            $order->payment_complete( $transaction_no );
            $order->add_order_note(
                sprintf(
                    /* translators: 1: VNPAY transaction number, 2: bank code */
                    __( 'Payment completed via VNPAY (IPN). Transaction: %1$s, bank: %2$s.', 'payment-gateway-for-vnpay' ),
                    $transaction_no ? $transaction_no : '-',
                    $data['vnp_BankCode'] ?? '-'
                )
            );
            $this->log( 'info', 'IPN: order #' . $order->get_id() . ' paid.' );
        } else {
            $order->update_status(
                'failed',
                /* translators: %s: VNPAY response code */
                sprintf( __( 'Payment declined by VNPAY. Error code: %s', 'payment-gateway-for-vnpay' ), $data['vnp_ResponseCode'] )
            );
            $this->log( 'info', 'IPN: payment for order #' . $order->get_id() . ' declined, code ' . $data['vnp_ResponseCode'] );
        }

        /**
         * Fires after a verified IPN has been applied to the order.
         *
         * @param WC_Order $order      The order.
         * @param array    $data       Verified vnp_* parameters.
         * @param bool     $is_success Whether the payment succeeded.
         */
        do_action( 'pgfv_ipn_processed', $order, $data, $is_success );

        // VNPAY expects 00 whenever the notification was accepted, even for a declined payment.
        wp_send_json( array( 'RspCode' => '00', 'Message' => 'Confirm Success' ) );
    }

    /* ---------------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------------ */

    /**
     * Whether the store currency can be charged. Plain VND by default; add-ons may allow more (with conversion).
     */
    public function is_currency_supported() {
        $currency = get_woocommerce_currency();
        return (bool) apply_filters( 'pgfv_currency_supported', 'VND' === $currency, $currency );
    }

    /**
     * Order total in VND, in VNPAY units (x100).
     *
     * @param WC_Order $order   Order.
     * @param string   $context 'payment' when creating a request, 'verify' when checking an IPN.
     */
    private function get_order_vnp_amount( $order, $context ) {
        $total = apply_filters( 'pgfv_order_vnd_total', (float) $order->get_total(), $order, $context );
        return $this->to_vnp_amount( $total );
    }

    /**
     * Refunds are handled by add-ons through this filter. Without one, a clear error is returned.
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        $default = new WP_Error( 'pgfv_refund_unsupported', __( 'Automatic refunds are not available. Refund the customer in the VNPAY merchant portal.', 'payment-gateway-for-vnpay' ) );
        return apply_filters( 'pgfv_process_refund', $default, $order_id, $amount, $reason, $this );
    }

    private function to_vnp_amount( $total ) {
        return (int) round( (float) $total * 100 );
    }

    private function build_query( array $data ) {
        $data = array_filter(
            $data,
            function ( $value ) {
                return null !== $value && '' !== $value;
            }
        );
        ksort( $data );
        return http_build_query( $data, '', '&', PHP_QUERY_RFC1738 );
    }

    private function extract_vnp_params( array $params ) {
        $data = array();
        foreach ( $params as $key => $value ) {
            if ( 0 === strpos( $key, 'vnp_' ) && is_scalar( $value ) ) {
                $data[ $key ] = (string) $value;
            }
        }
        return $data;
    }

    private function is_signature_valid( array $data ) {
        $received = $data['vnp_SecureHash'] ?? '';
        unset( $data['vnp_SecureHash'], $data['vnp_SecureHashType'] );

        if ( '' === $received || empty( $this->hash_secret ) ) {
            return false;
        }

        $expected = hash_hmac( 'sha512', $this->build_query( $data ), $this->hash_secret );
        return hash_equals( $expected, $received );
    }

    private function get_order_by_txn_ref( $txn_ref ) {
        $parts    = explode( '_', (string) $txn_ref );
        $order_id = absint( $parts[0] );
        if ( ! $order_id ) {
            return false;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order || $order->get_payment_method() !== $this->id ) {
            return false;
        }
        return $order;
    }

    private function is_known_txn_ref( $order, $txn_ref ) {
        $refs = $order->get_meta( '_vnpay_txn_refs' );
        $refs = is_array( $refs ) ? $refs : array();
        $refs[] = (string) $order->get_meta( '_vnpay_txn_ref' );
        return in_array( (string) $txn_ref, $refs, true );
    }

    /**
     * Atomic per-order lock (add_option fails if the row already exists).
     * A lock older than 60 seconds is treated as stale and replaced.
     */
    private function acquire_order_lock( $order_id ) {
        $name = 'pgfv_lock_' . absint( $order_id );
        if ( add_option( $name, time(), '', 'no' ) ) {
            return true;
        }
        wp_cache_delete( $name, 'options' );
        wp_cache_delete( 'alloptions', 'options' );
        $started = (int) get_option( $name, 0 );
        if ( $started && ( time() - $started ) > 60 ) {
            delete_option( $name );
            return add_option( $name, time(), '', 'no' );
        }
        return false;
    }

    public function release_order_lock( $order_id ) {
        delete_option( 'pgfv_lock_' . absint( $order_id ) );
    }

    private function get_error_message( $code ) {
        $messages = array(
            '07' => __( 'The bank flagged the payment as suspicious. Please contact your bank or choose another method.', 'payment-gateway-for-vnpay' ),
            '09' => __( 'Internet Banking is not enabled for this card or account.', 'payment-gateway-for-vnpay' ),
            '10' => __( 'The card details were entered incorrectly more than three times.', 'payment-gateway-for-vnpay' ),
            '11' => __( 'The payment window has expired. Please start the payment again.', 'payment-gateway-for-vnpay' ),
            '12' => __( 'The card or account is locked.', 'payment-gateway-for-vnpay' ),
            '13' => __( 'Incorrect one-time password (OTP).', 'payment-gateway-for-vnpay' ),
            '24' => __( 'You cancelled the payment.', 'payment-gateway-for-vnpay' ),
            '51' => __( 'Insufficient funds.', 'payment-gateway-for-vnpay' ),
            '65' => __( 'The daily transaction limit has been exceeded.', 'payment-gateway-for-vnpay' ),
            '75' => __( 'The bank is under maintenance. Please try again later.', 'payment-gateway-for-vnpay' ),
            '79' => __( 'The payment password was entered incorrectly too many times.', 'payment-gateway-for-vnpay' ),
        );

        return isset( $messages[ $code ] )
            ? $messages[ $code ]
            : __( 'The payment was not completed. Please try again or choose another payment method.', 'payment-gateway-for-vnpay' );
    }

    public function log( $level, $message ) {
        // Errors and warnings are always logged, everything else only when debugging is on.
        if ( ! $this->debug && ! in_array( $level, array( 'error', 'warning' ), true ) ) {
            return;
        }
        wc_get_logger()->log( $level, $message, array( 'source' => self::LOG_SOURCE ) );
    }
}
