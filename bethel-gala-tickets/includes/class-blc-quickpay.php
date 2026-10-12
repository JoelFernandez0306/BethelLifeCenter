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

    /** Option names for the fee charged on QR code payments. */
    const OPTION_FEE_RATE  = 'blc_gala_quickpay_fee_rate';
    const OPTION_FEE_FIXED = 'blc_gala_quickpay_fee_fixed';

    /**
     * The fee added to QR code payments, kept separate from the gala ticket
     * and donation fee so the two can differ (a raffle ticket may be billed
     * at a different rate from a gala ticket).
     *
     * Until these have been saved they follow the gala fee settings, so a
     * site that has never touched them keeps charging exactly what it did.
     *
     * @return array {rate: percent e.g. 1.99, fixed: dollars e.g. 0.49}
     */
    public static function fee_settings() {
        $rate  = get_option( self::OPTION_FEE_RATE, false );
        $fixed = get_option( self::OPTION_FEE_FIXED, false );

        if ( false === $rate ) {
            $rate = get_option( 'blc_gala_paypal_fee_rate', 2.99 );
        }
        if ( false === $fixed ) {
            $fixed = get_option( 'blc_gala_paypal_fee_fixed', 0.49 );
        }

        return array(
            'rate'  => (float) $rate,
            'fixed' => (float) $fixed,
        );
    }

    /**
     * Check a percentage and fixed fee, returning them rounded to the cent,
     * or a WP_Error describing the typo. Refuses values that are clearly
     * mistakes, since a wrong rate is charged to real buyers.
     *
     * @param mixed $rate  Percentage, e.g. 1.99.
     * @param mixed $fixed Dollars per payment, e.g. 0.49.
     * @return array|WP_Error {rate, fixed}
     */
    public static function validate_fee( $rate, $fixed ) {
        if ( ! is_numeric( $rate ) || ! is_numeric( $fixed ) ) {
            return new WP_Error( 'invalid_fee', 'Please enter both the percentage and the fixed fee as numbers (use 0 for none).' );
        }

        $rate  = round( (float) $rate, 2 );
        $fixed = round( (float) $fixed, 2 );

        if ( $rate < 0 || $rate > 20 ) {
            return new WP_Error( 'invalid_fee', 'The percentage should be between 0 and 20. For 1.99%, type 1.99.' );
        }
        if ( $fixed < 0 || $fixed > 5 ) {
            return new WP_Error( 'invalid_fee', 'The fixed fee should be between $0 and $5. For 49 cents, type 0.49.' );
        }

        return array( 'rate' => $rate, 'fixed' => $fixed );
    }

    /**
     * Save the raffle QR code fee.
     *
     * @return true|WP_Error
     */
    public static function save_fee_settings( $rate, $fixed ) {
        $fee = self::validate_fee( $rate, $fixed );

        if ( is_wp_error( $fee ) ) {
            return $fee;
        }

        update_option( self::OPTION_FEE_RATE, $fee['rate'] );
        update_option( self::OPTION_FEE_FIXED, $fee['fixed'] );

        return true;
    }

    // ---------------------------------------------------------------------
    // Donation QR code
    //
    // One QR code, not a list: it opens the same Quick Pay page in "give any
    // amount" mode. It has its own fee so a donation can cover PayPal's
    // charge without affecting raffle or gala pricing.
    // ---------------------------------------------------------------------

    /** Reserved item id that puts the Quick Pay page into donation mode. Real items get random 12-character ids, so they can never collide. */
    const DONATE_ID = 'donate';

    const OPTION_DONATE_FEE_RATE  = 'blc_gala_donateqr_fee_rate';
    const OPTION_DONATE_FEE_FIXED = 'blc_gala_donateqr_fee_fixed';
    const OPTION_DONATE_NOTE      = 'blc_gala_donateqr_note';

    const DEFAULT_DONATE_NOTE = 'Always on Mission donation';

    /** Smallest and largest single donation, in cents. The ceiling is a guard against a mistyped amount, not a policy. */
    const MIN_DONATION_CENTS = 100;
    const MAX_DONATION_CENTS = 2500000;

    /**
     * Fee added to donation QR payments. Follows the gala fee until it has
     * been saved, so an update never changes a price on its own.
     *
     * @return array {rate: percent, fixed: dollars}
     */
    public static function donation_fee_settings() {
        $rate  = get_option( self::OPTION_DONATE_FEE_RATE, false );
        $fixed = get_option( self::OPTION_DONATE_FEE_FIXED, false );

        if ( false === $rate ) {
            $rate = get_option( 'blc_gala_paypal_fee_rate', 2.99 );
        }
        if ( false === $fixed ) {
            $fixed = get_option( 'blc_gala_paypal_fee_fixed', 0.49 );
        }

        return array(
            'rate'  => (float) $rate,
            'fixed' => (float) $fixed,
        );
    }

    /**
     * The text sent to PayPal as the payment's description, which is what
     * appears on the transaction in the church's PayPal account.
     */
    public static function donation_note() {
        $note = trim( (string) get_option( self::OPTION_DONATE_NOTE, '' ) );

        return '' === $note ? self::DEFAULT_DONATE_NOTE : $note;
    }

    /**
     * Save the donation fee and the note.
     *
     * @return true|WP_Error
     */
    public static function save_donation_settings( $rate, $fixed, $note ) {
        $fee = self::validate_fee( $rate, $fixed );

        if ( is_wp_error( $fee ) ) {
            return $fee;
        }

        // PayPal cuts a description off at 127 characters; stay well inside.
        $note = trim( sanitize_text_field( $note ) );
        $note = function_exists( 'mb_substr' ) ? mb_substr( $note, 0, 100 ) : substr( $note, 0, 100 );

        update_option( self::OPTION_DONATE_FEE_RATE, $fee['rate'] );
        update_option( self::OPTION_DONATE_FEE_FIXED, $fee['fixed'] );
        update_option( self::OPTION_DONATE_NOTE, '' === $note ? self::DEFAULT_DONATE_NOTE : $note );

        return true;
    }

    /**
     * Turn the donor's amount into whole cents, or refuse it.
     *
     * The browser sends cents as an integer, so there is no decimal to
     * misread. Anything else (text, decimals, out of range) is an error.
     *
     * @param mixed $raw
     * @return int|WP_Error
     */
    public static function sanitize_donation_cents( $raw ) {
        if ( is_string( $raw ) && preg_match( '/^\d+$/', $raw ) ) {
            $raw = (int) $raw;
        }

        if ( ! is_int( $raw ) ) {
            return new WP_Error( 'invalid_amount', 'Please enter a donation amount.' );
        }

        if ( $raw < self::MIN_DONATION_CENTS ) {
            return new WP_Error( 'invalid_amount', 'The minimum donation is $' . number_format( self::MIN_DONATION_CENTS / 100, 2 ) . '.' );
        }
        if ( $raw > self::MAX_DONATION_CENTS ) {
            return new WP_Error( 'invalid_amount', 'For a gift over $' . number_format( self::MAX_DONATION_CENTS / 100 ) . ', please contact the church directly.' );
        }

        return $raw;
    }

    /**
     * The donation fee as whole numbers: the fixed fee in cents and the
     * percentage in hundredths of a percent (1.99% is 199). The page is given
     * these same two integers, so both sides do identical arithmetic.
     *
     * @return array {fixed_cents, rate_hund}
     */
    public static function donation_fee_params() {
        $fee       = self::donation_fee_settings();
        $rate_hund = (int) round( $fee['rate'] * 100 );

        return array(
            'fixed_cents' => (int) round( $fee['fixed'] * 100 ),
            // A rate of 100% or more would divide by zero; treat it as none.
            'rate_hund'   => $rate_hund >= 10000 ? 0 : $rate_hund,
        );
    }

    /**
     * What a donor is charged so the church receives exactly their gift.
     *
     * Done in whole cents with integer arithmetic. The page shows the donor
     * a total as they type, so JavaScript and PHP both have to land on the
     * same figure; floating-point rounding can disagree by a cent, while
     * integer maths cannot. gala-quickpay.js mirrors this function exactly.
     *
     * total = (gift + fixed) / (1 - rate), rounded half up to the cent.
     *
     * @param int $gift_cents
     * @return array {gift_cents, fee_cents, total_cents, gift, fee, total}
     */
    public static function donation_total( $gift_cents ) {
        $params    = self::donation_fee_params();
        $gift      = (int) $gift_cents;
        $fixed     = $params['fixed_cents'];
        $rate_hund = $params['rate_hund'];

        if ( $rate_hund <= 0 && $fixed <= 0 ) {
            $total = $gift;
        } else {
            $num   = ( $gift + $fixed ) * 10000;
            $den   = 10000 - $rate_hund;
            $total = intdiv( 2 * $num + $den, 2 * $den );
        }

        return array(
            'gift_cents'  => $gift,
            'fee_cents'   => $total - $gift,
            'total_cents' => $total,
            // Always floats: integer division in PHP returns an int when it
            // divides evenly, which would make the type depend on the amount.
            'gift'        => $gift / 100.0,
            'fee'         => ( $total - $gift ) / 100.0,
            'total'       => $total / 100.0,
        );
    }

    /**
     * Link the donation QR code points at, or '' until the Quick Pay page exists.
     */
    public static function get_donation_url() {
        return self::get_payment_url( self::DONATE_ID );
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
     * Uses the QR code fee from fee_settings(), so changing those two numbers
     * updates every QR code at once without touching gala ticket pricing.
     */
    public static function calculate_total( $amount, $quantity = 1 ) {
        $amount = round( floatval( $amount ) * max( 1, (int) $quantity ), 2 );
        $fee    = self::fee_settings();
        $rate   = $fee['rate'] / 100;
        $fixed  = $fee['fixed'];

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
