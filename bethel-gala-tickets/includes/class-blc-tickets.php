<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BLC_Gala_Tickets {

    private $orders_table;
    private $tickets_table;

    public function __construct() {
        global $wpdb;
        $this->orders_table  = $wpdb->prefix . 'blc_gala_orders';
        $this->tickets_table = $wpdb->prefix . 'blc_gala_tickets';
    }

    /**
     * Get the number of remaining tickets.
     */
    public function get_remaining_count() {
        global $wpdb;
        $total       = (int) get_option( 'blc_gala_total_tickets', 100 );
        $manual_sold = (int) get_option( 'blc_gala_manual_sold', 0 );
        $db_sold     = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(quantity), 0) FROM {$this->orders_table} WHERE order_type = %s AND status = %s",
            'ticket',
            'completed'
        ) );
        return max( 0, $total - $db_sold - $manual_sold );
    }

    /**
     * Check if tickets are sold out.
     */
    public function is_sold_out() {
        return $this->get_remaining_count() <= 0;
    }

    /**
     * Reserve tickets by creating a pending order. Uses a transaction to prevent overselling.
     *
     * @return array|WP_Error Order data on success, WP_Error on failure.
     */
    public function reserve_ticket( $buyer_name, $buyer_email, $quantity = 1 ) {
        global $wpdb;

        $total       = (int) get_option( 'blc_gala_total_tickets', 100 );
        $manual_sold = (int) get_option( 'blc_gala_manual_sold', 0 );
        $max         = (int) get_option( 'blc_gala_max_per_order', 1 );

        if ( $quantity > $max ) {
            return new WP_Error( 'quantity_exceeded', sprintf( 'Maximum %d ticket(s) per order.', $max ), array( 'status' => 400 ) );
        }

        $wpdb->query( 'START TRANSACTION' );

        $db_sold = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(quantity), 0) FROM {$this->orders_table} WHERE order_type = %s AND status = %s FOR UPDATE",
            'ticket',
            'completed'
        ) );

        $sold = $db_sold + $manual_sold;

        if ( $sold + $quantity > $total ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'sold_out', 'Sorry, there are not enough tickets remaining.', array( 'status' => 409 ) );
        }

        $order_uuid = wp_generate_uuid4();
        $price      = (float) get_option( 'blc_gala_ticket_price', 50.00 );
        $amount     = $price * $quantity;

        $inserted = $wpdb->insert( $this->orders_table, array(
            'order_uuid'  => $order_uuid,
            'order_type'  => 'ticket',
            'buyer_name'  => sanitize_text_field( $buyer_name ),
            'buyer_email' => sanitize_email( $buyer_email ),
            'quantity'    => $quantity,
            'amount_paid' => $amount,
            'status'      => 'pending',
            'created_at'  => current_time( 'mysql' ),
        ) );

        if ( ! $inserted ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'db_error', 'Failed to create order.', array( 'status' => 500 ) );
        }

        $order_id = $wpdb->insert_id;
        $wpdb->query( 'COMMIT' );

        return array(
            'order_id'   => $order_id,
            'order_uuid' => $order_uuid,
            'amount'     => $amount,
            'quantity'   => $quantity,
        );
    }

    /**
     * Create a donation order (no ticket inventory impact).
     */
    public function create_donation_order( $buyer_name, $buyer_email, $amount ) {
        global $wpdb;

        $order_uuid = wp_generate_uuid4();

        $inserted = $wpdb->insert( $this->orders_table, array(
            'order_uuid'  => $order_uuid,
            'order_type'  => 'donation',
            'buyer_name'  => sanitize_text_field( $buyer_name ),
            'buyer_email' => sanitize_email( $buyer_email ),
            'quantity'    => 0,
            'amount_paid' => floatval( $amount ),
            'status'      => 'pending',
            'created_at'  => current_time( 'mysql' ),
        ) );

        if ( ! $inserted ) {
            return new WP_Error( 'db_error', 'Failed to create donation order.', array( 'status' => 500 ) );
        }

        return array(
            'order_id'   => $wpdb->insert_id,
            'order_uuid' => $order_uuid,
            'amount'     => floatval( $amount ),
        );
    }

    /**
     * Complete an order after successful PayPal payment. Generate ticket codes.
     */
    public function complete_order( $order_uuid, $paypal_order_id, $paypal_capture_id = '' ) {
        global $wpdb;

        $order = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->orders_table} WHERE order_uuid = %s",
            $order_uuid
        ) );

        if ( ! $order ) {
            return new WP_Error( 'not_found', 'Order not found.', array( 'status' => 404 ) );
        }

        if ( $order->status === 'completed' ) {
            return new WP_Error( 'already_completed', 'Order already completed.', array( 'status' => 400 ) );
        }

        $wpdb->update(
            $this->orders_table,
            array(
                'status'            => 'completed',
                'paypal_order_id'   => sanitize_text_field( $paypal_order_id ),
                'paypal_capture_id' => sanitize_text_field( $paypal_capture_id ),
            ),
            array( 'order_uuid' => $order_uuid )
        );

        // Generate ticket records for ticket orders
        $tickets = array();
        if ( $order->order_type === 'ticket' ) {
            for ( $i = 0; $i < $order->quantity; $i++ ) {
                $ticket_code = strtoupper( substr( md5( $order_uuid . $i . wp_generate_password( 12, false ) ), 0, 12 ) );
                $wpdb->insert( $this->tickets_table, array(
                    'order_id'      => $order->id,
                    'ticket_code'   => $ticket_code,
                    'attendee_name' => $order->buyer_name,
                    'created_at'    => current_time( 'mysql' ),
                ) );
                $tickets[] = array(
                    'ticket_id'   => $wpdb->insert_id,
                    'ticket_code' => $ticket_code,
                );
            }
        }

        return array(
            'order'   => $order,
            'tickets' => $tickets,
        );
    }

    /**
     * Validate and check in a ticket at the door.
     */
    public function validate_ticket( $ticket_code ) {
        global $wpdb;

        $ticket = $wpdb->get_row( $wpdb->prepare(
            "SELECT t.*, o.buyer_name, o.buyer_email, o.status AS order_status
             FROM {$this->tickets_table} t
             JOIN {$this->orders_table} o ON t.order_id = o.id
             WHERE t.ticket_code = %s",
            strtoupper( sanitize_text_field( $ticket_code ) )
        ) );

        if ( ! $ticket ) {
            return new WP_Error( 'invalid_ticket', 'This ticket code is not valid.', array( 'status' => 404 ) );
        }

        if ( $ticket->order_status !== 'completed' ) {
            return new WP_Error( 'unpaid_ticket', 'This ticket has not been paid for.', array( 'status' => 400 ) );
        }

        if ( $ticket->is_checked_in ) {
            return array(
                'status'        => 'already_checked_in',
                'attendee_name' => $ticket->attendee_name,
                'checked_in_at' => $ticket->checked_in_at,
            );
        }

        $wpdb->update(
            $this->tickets_table,
            array(
                'is_checked_in' => 1,
                'checked_in_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $ticket->id )
        );

        return array(
            'status'        => 'checked_in',
            'attendee_name' => $ticket->attendee_name,
            'buyer_email'   => $ticket->buyer_email,
        );
    }

    /**
     * Create a pending quick-pay order for a fixed-amount QR code.
     *
     * @param string $label  Which item was scanned, for the purchase log.
     * @param float  $amount The total the buyer will be charged, fee included.
     */
    public function create_quickpay_order( $label, $amount ) {
        global $wpdb;

        $order_uuid = wp_generate_uuid4();

        $inserted = $wpdb->insert( $this->orders_table, array(
            'order_uuid'    => $order_uuid,
            'order_type'    => 'quickpay',
            'buyer_name'    => '',
            'buyer_email'   => '',
            'quantity'      => 1,
            'amount_paid'   => floatval( $amount ),
            'payment_label' => sanitize_text_field( $label ),
            'status'        => 'pending',
            'created_at'    => current_time( 'mysql' ),
        ) );

        if ( ! $inserted ) {
            return new WP_Error( 'db_error', 'Failed to start the payment.', array( 'status' => 500 ) );
        }

        return array(
            'order_id'   => $wpdb->insert_id,
            'order_uuid' => $order_uuid,
            'amount'     => floatval( $amount ),
        );
    }

    /**
     * Mark a quick-pay order complete and record who paid.
     *
     * @param array $payer Payer details from BLC_Gala_PayPal::extract_payer_details().
     */
    public function complete_quickpay_order( $order_uuid, $paypal_order_id, $capture_id, $payer ) {
        global $wpdb;

        $order = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->orders_table} WHERE order_uuid = %s",
            $order_uuid
        ) );

        if ( ! $order ) {
            return new WP_Error( 'not_found', 'Payment not found.', array( 'status' => 404 ) );
        }

        if ( $order->status === 'completed' ) {
            return new WP_Error( 'already_completed', 'This payment was already recorded.', array( 'status' => 400 ) );
        }

        $payer_name = isset( $payer['payer_name'] ) ? sanitize_text_field( $payer['payer_name'] ) : '';
        $payer_mail = isset( $payer['payer_email'] ) ? sanitize_email( $payer['payer_email'] ) : '';
        $last4      = isset( $payer['card_last4'] ) ? preg_replace( '/\D/', '', $payer['card_last4'] ) : '';
        $brand      = isset( $payer['card_brand'] ) ? sanitize_text_field( $payer['card_brand'] ) : '';

        $wpdb->update(
            $this->orders_table,
            array(
                'status'            => 'completed',
                'paypal_order_id'   => sanitize_text_field( $paypal_order_id ),
                'paypal_capture_id' => sanitize_text_field( $capture_id ),
                'buyer_name'        => $payer_name,
                'buyer_email'       => $payer_mail,
                'payer_name'        => $payer_name,
                'card_last4'        => substr( $last4, -4 ),
                'card_brand'        => $brand,
            ),
            array( 'order_uuid' => $order_uuid )
        );

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->orders_table} WHERE order_uuid = %s",
            $order_uuid
        ) );
    }

    /**
     * Completed quick-pay purchases, newest first.
     */
    public function get_quickpay_purchases( $limit = 500 ) {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT payer_name, card_last4, card_brand, amount_paid, payment_label, created_at
             FROM {$this->orders_table}
             WHERE order_type = %s AND status = %s
             ORDER BY created_at DESC
             LIMIT %d",
            'quickpay',
            'completed',
            $limit
        ) );
    }

    /**
     * Get all orders with optional filtering.
     */
    public function get_orders( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'order_type' => '',
            'status'     => '',
            'limit'      => 50,
            'offset'     => 0,
        );
        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $values = array();

        if ( $args['order_type'] ) {
            $where[]  = 'o.order_type = %s';
            $values[] = $args['order_type'];
        }
        if ( $args['status'] ) {
            $where[]  = 'o.status = %s';
            $values[] = $args['status'];
        }

        $where_sql = implode( ' AND ', $where );

        $query = "SELECT o.*, GROUP_CONCAT(t.ticket_code) AS ticket_codes, GROUP_CONCAT(t.is_checked_in) AS check_in_statuses
                  FROM {$this->orders_table} o
                  LEFT JOIN {$this->tickets_table} t ON o.id = t.order_id
                  WHERE {$where_sql}
                  GROUP BY o.id
                  ORDER BY o.created_at DESC
                  LIMIT %d OFFSET %d";

        $values[] = $args['limit'];
        $values[] = $args['offset'];

        if ( count( $values ) > 0 ) {
            $query = $wpdb->prepare( $query, ...$values );
        }

        return $wpdb->get_results( $query );
    }

    /**
     * Get an order by ID, including its tickets.
     */
    public function get_order_with_tickets( $order_id ) {
        global $wpdb;

        $order = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->orders_table} WHERE id = %d",
            $order_id
        ) );

        if ( ! $order ) {
            return new WP_Error( 'not_found', 'Order not found.', array( 'status' => 404 ) );
        }

        $tickets = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->tickets_table} WHERE order_id = %d",
            $order_id
        ) );

        $ticket_data = array();
        foreach ( $tickets as $t ) {
            $ticket_data[] = array(
                'ticket_id'   => $t->id,
                'ticket_code' => $t->ticket_code,
            );
        }

        return array(
            'order'   => $order,
            'tickets' => $ticket_data,
        );
    }
}
