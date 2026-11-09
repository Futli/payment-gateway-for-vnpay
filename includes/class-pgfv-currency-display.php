<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Appends an approximate USD amount next to prices shown in VND.
 * Display only: the customer is always charged in VND.
 */
class PGFV_Currency_Display {

    public static function init() {
        add_filter( 'wc_price', array( __CLASS__, 'append_usd' ), 20, 4 );
    }

    /**
     * @param string     $formatted         Price HTML produced by wc_price().
     * @param string     $price             The amount already formatted by wc_price(), for example "100 000". Not usable as a number.
     * @param array      $args              wc_price() arguments.
     * @param float|null $unformatted_price The raw amount passed to wc_price().
     * @return string
     */
    public static function append_usd( $formatted, $price, $args = array(), $unformatted_price = null ) {
        // Skip the admin area, but keep AJAX requests (cart and checkout fragments).
        if ( is_admin() && ! wp_doing_ajax() ) {
            return $formatted;
        }

        $settings = (array) get_option( 'woocommerce_vnpay_gateway_settings', array() );

        if ( 'yes' !== ( $settings['usd_display'] ?? 'no' ) ) {
            return $formatted;
        }

        $rate = (float) ( $settings['usd_rate'] ?? 0 );
        if ( $rate <= 0 ) {
            return $formatted;
        }

        $currency = ! empty( $args['currency'] ) ? $args['currency'] : get_woocommerce_currency();
        if ( 'VND' !== $currency ) {
            return $formatted;
        }

        // Use the raw amount. The second filter argument is already formatted ("100 000") and would be misread.
        if ( null !== $unformatted_price && '' !== $unformatted_price ) {
            $amount = (float) $unformatted_price;
        } else {
            // VND has no decimals, so keeping only digits and the minus sign is safe here.
            $amount = (float) preg_replace( '/[^0-9\-]/', '', wp_strip_all_tags( (string) $price ) );
        }
        if ( 0.0 === $amount ) {
            return $formatted;
        }

        $sign = $amount < 0 ? '-' : '';
        $usd  = number_format( abs( $amount ) / $rate, 2, '.', ',' );

        return $formatted
            . ' <span class="pgfv-usd" style="font-size:.85em;opacity:.75;white-space:nowrap;">(≈ '
            . esc_html( $sign . '$' . $usd )
            . ')</span>';
    }
}
