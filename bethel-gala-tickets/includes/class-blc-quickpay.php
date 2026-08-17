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
     * What the buyer is charged so the church nets the item's full amount.
     * Uses the same PayPal fee settings as tickets and donations, so changing
     * those numbers updates every QR code at once.
     */
    public static function calculate_total( $amount ) {
        $amount = round( floatval( $amount ), 2 );
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
