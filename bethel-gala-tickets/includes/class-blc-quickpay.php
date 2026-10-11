<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Manages "Quick Pay" QR codes — fixed-amount payment links the church can
 * print and set out on a table (e.g. $12 for a fundraiser meal, $5 for a snack).
 *
 * Items live in a single option rather than their own table: there are only
 * ever a handful, and this keeps the plugin free of another migration.
 */
class BLC_Gala_QuickPay {

    const OPTION = 'blc_gala_quickpay_items';

    /**
     * All configured items, oldest first.
     */
    public static function get_items() {
        $items = get_option( self::OPTION, array() );
        return is_array( $items ) ? $items : array();
    }

    /**
     * Find one item by its public ID. Returns null when it no longer exists.
     */
    public static function get_item( $id ) {
        if ( empty( $id ) ) {
            return null;
        }
        foreach ( self::get_items() as $item ) {
            if ( isset( $item['id'] ) && $item['id'] === $id ) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Add an item. Returns the new item, or WP_Error when the amount is unusable.
     */
    public static function add_item( $label, $amount ) {
        $amount = round( floatval( $amount ), 2 );

        if ( $amount < 0.01 ) {
            return new WP_Error( 'invalid_amount', 'Amount must be at least $0.01.' );
        }

        $label = sanitize_text_field( $label );
        if ( $label === '' ) {
            $label = '$' . number_format( $amount, 2 ) . ' Payment';
        }

        $items   = self::get_items();
        $items[] = array(
            'id'     => strtolower( wp_generate_password( 12, false, false ) ),
            'label'  => $label,
            'amount' => $amount,
        );

        update_option( self::OPTION, $items );

        return end( $items );
    }

    /**
     * Remove an item. Past payments made with it are kept.
     */
    public static function delete_item( $id ) {
        $items = array_values( array_filter( self::get_items(), function ( $item ) use ( $id ) {
            return ! isset( $item['id'] ) || $item['id'] !== $id;
        } ) );

        update_option( self::OPTION, $items );
    }

    /**
     * Most tickets one person can buy in a single payment. A ceiling rather
     * than a business rule: it stops a mistyped quantity becoming a huge
     * charge, and keeps the price table the page ships to the browser small.
     */
    const MAX_QUANTITY = 99;

    /**
     * Turn whatever the browser sent into a usable quantity.
     *
     * A missing value means 1, so a page still running an older cached copy of
     * the script, which never sent a quantity, keeps working.
     *
     * @param mixed $raw
     * @return int|WP_Error
     */
    public static function sanitize_quantity( $raw ) {
        if ( null === $raw || '' === $raw ) {
            return 1;
        }

        if ( ! is_numeric( $raw ) || (float) $raw !== floor( (float) $raw ) ) {
            return new WP_Error( 'invalid_quantity', 'Please enter a whole number of tickets.' );
        }

        $quantity = (int) $raw;

        if ( $quantity < 1 || $quantity > self::MAX_QUANTITY ) {
            return new WP_Error(
                'invalid_quantity',
                sprintf( 'Please choose between 1 and %d tickets.', self::MAX_QUANTITY )
            );
        }

        return $quantity;
    }

    /**
     * What the buyer is charged for $quantity tickets at $amount each, so the
     * church nets the full subtotal.
     *
     * The fee is worked out once on the whole subtotal, not per ticket,
     * because PayPal's fixed fee applies once per payment. Three $5 tickets
     * therefore cost less in fees than three separate $5 payments would.
     *
     * Uses the same PayPal fee settings as tickets and donations, so changing
     * those numbers updates every QR code at once.
     */
    public static function calculate_total( $amount, $quantity = 1 ) {
        $amount = round( floatval( $amount ) * max( 1, (int) $quantity ), 2 );
        $rate   = (float) get_option( 'blc_gala_paypal_fee_rate', 2.99 ) / 100;
        $fixed  = (float) get_option( 'blc_gala_paypal_fee_fixed', 0.49 );

        if ( $rate <= 0 && $fixed <= 0 ) {
            return array(
                'subtotal' => $amount,
                'fee'      => 0.00,
                'total'    => $amount,
            );
        }

        // Guard against a nonsensical rate that would divide by zero or flip the sign.
        if ( $rate >= 1 ) {
            $rate = 0.0;
        }

        $total = round( ( $amount + $fixed ) / ( 1 - $rate ), 2 );

        return array(
            'subtotal' => $amount,
            'fee'      => round( $total - $amount, 2 ),
            'total'    => $total,
        );
    }

    /**
     * Every possible price for an item, keyed by quantity.
     *
     * The payment page is handed this table instead of working the fee out
     * again in JavaScript. That way the total a buyer sees is, to the cent,
     * the total the server will charge — two separate roundings of the same
     * sum can disagree by a cent.
     *
     * @return array [ quantity => {subtotal, fee, total} ]
     */
    public static function price_table( $amount ) {
        $table = array();

        for ( $q = 1; $q <= self::MAX_QUANTITY; $q++ ) {
            $table[ $q ] = self::calculate_total( $amount, $q );
        }

        return $table;
    }

    /**
     * URL of the page holding the [blc_gala_quickpay] shortcode, with the
     * item pre-selected. Empty until that page has been visited once.
     */
    public static function get_payment_url( $id ) {
        $page_url = get_option( 'blc_gala_quickpay_page_url', '' );

        if ( ! $page_url ) {
            return '';
        }

        return add_query_arg( 'blc_qp', $id, $page_url );
    }
}
