<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BLC_Gala_REST_API {

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        $namespace = 'blc-gala/v1';

        // Public: get remaining ticket count
        register_rest_route( $namespace, '/remaining', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_remaining' ),
            'permission_callback' => '__return_true',
        ) );

        // Public: create a PayPal order for ticket purchase
        register_rest_route( $namespace, '/create-order', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'create_order' ),
            'permission_callback' => '__return_true',
        ) );

        // Public: capture PayPal payment after buyer approval
        register_rest_route( $namespace, '/capture-order', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'capture_order' ),
            'permission_callback' => '__return_true',
        ) );

        // Public: create a PayPal order for donation
        register_rest_route( $namespace, '/create-donation', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'create_donation' ),
            'permission_callback' => '__return_true',
        ) );

        // Public: capture donation payment
        register_rest_route( $namespace, '/capture-donation', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'capture_donation' ),
            'permission_callback' => '__return_true',
        ) );

        // Admin: validate/check-in a ticket
        register_rest_route( $namespace, '/validate/(?P<code>[A-Za-z0-9]+)', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'validate_ticket' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        // Admin: get ticket info without checking in
        register_rest_route( $namespace, '/ticket-info/(?P<code>[A-Za-z0-9]+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_ticket_info' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        // Public scanner: authenticate with PIN
        register_rest_route( $namespace, '/scanner-auth', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'scanner_auth' ),
            'permission_callback' => '__return_true',
        ) );

        // Public scanner: validate/check-in a ticket with scanner token
        register_rest_route( $namespace, '/scan-validate/(?P<code>[A-Za-z0-9]+)', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'validate_ticket' ),
            'permission_callback' => array( $this, 'check_scanner_token' ),
        ) );

        // Public scanner: get check-in stats with scanner token
        register_rest_route( $namespace, '/checkin-stats', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_checkin_stats' ),
            'permission_callback' => array( $this, 'check_scanner_token' ),
        ) );

        // Admin: test PayPal connection
        register_rest_route( $namespace, '/test-paypal', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'test_paypal' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        // Public: verify admin PIN for cash/check
        register_rest_route( $namespace, '/admin-auth', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'admin_auth' ),
            'permission_callback' => '__return_true',
        ) );

        // Public: process cash/check order (requires admin token)
        register_rest_route( $namespace, '/cash-check-order', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'cash_check_order' ),
            'permission_callback' => array( $this, 'check_admin_token' ),
        ) );
    }

    /**
     * GET /remaining — returns ticket availability.
     */
    public function get_remaining( $request ) {
        $tickets   = new BLC_Gala_Tickets();
        $remaining = $tickets->get_remaining_count();
        $total     = (int) get_option( 'blc_gala_total_tickets', 100 );

        return rest_ensure_response( array(
            'remaining' => $remaining,
            'total'     => $total,
            'sold_out'  => $remaining <= 0,
        ) );
    }

    /**
     * POST /create-order — reserve ticket and create PayPal order.
     */
    public function create_order( $request ) {
        $params = $request->get_json_params();

        $buyer_name  = isset( $params['buyer_name'] ) ? sanitize_text_field( $params['buyer_name'] ) : '';
        $buyer_email = isset( $params['buyer_email'] ) ? sanitize_email( $params['buyer_email'] ) : '';
        $quantity    = isset( $params['quantity'] ) ? absint( $params['quantity'] ) : 1;

        if ( empty( $buyer_name ) || empty( $buyer_email ) ) {
            return new WP_Error( 'missing_fields', 'Name and email are required.', array( 'status' => 400 ) );
        }

        if ( ! is_email( $buyer_email ) ) {
            return new WP_Error( 'invalid_email', 'Please provide a valid email address.', array( 'status' => 400 ) );
        }

        // Reserve the ticket (with transaction lock)
        $tickets_mgr = new BLC_Gala_Tickets();
        $order_data  = $tickets_mgr->reserve_ticket( $buyer_name, $buyer_email, $quantity );

        if ( is_wp_error( $order_data ) ) {
            return $order_data;
        }

        // Calculate total with PayPal fee
        $fee_rate     = (float) get_option( 'blc_gala_paypal_fee_rate', 2.99 ) / 100;
        $fee_fixed    = (float) get_option( 'blc_gala_paypal_fee_fixed', 0.49 );
        $subtotal     = $order_data['amount'];
        $charge_total = ( $fee_rate > 0 || $fee_fixed > 0 )
            ? round( ( $subtotal + $fee_fixed ) / ( 1 - $fee_rate ), 2 )
            : $subtotal;

        // Create PayPal order with fee-inclusive amount
        $paypal       = new BLC_Gala_PayPal();
        $event_name   = get_option( 'blc_gala_event_name', 'Always on Mission Gala 2026' );
        $description  = $event_name . ' — Ticket';
        $paypal_order = $paypal->create_order( $order_data['order_uuid'], $charge_total, $description );

        if ( is_wp_error( $paypal_order ) ) {
            // Clean up the pending order
            global $wpdb;
            $wpdb->delete( $wpdb->prefix . 'blc_gala_orders', array( 'order_uuid' => $order_data['order_uuid'] ) );
            return $paypal_order;
        }

        // Store PayPal order ID on our order
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'blc_gala_orders',
            array( 'paypal_order_id' => $paypal_order['id'] ),
            array( 'order_uuid' => $order_data['order_uuid'] )
        );

        return rest_ensure_response( array(
            'paypal_order_id' => $paypal_order['id'],
            'order_uuid'      => $order_data['order_uuid'],
        ) );
    }

    /**
     * POST /capture-order — capture payment and complete the ticket order.
     */
    public function capture_order( $request ) {
        $params = $request->get_json_params();

        $paypal_order_id = isset( $params['paypal_order_id'] ) ? sanitize_text_field( $params['paypal_order_id'] ) : '';
        $order_uuid      = isset( $params['order_uuid'] ) ? sanitize_text_field( $params['order_uuid'] ) : '';

        if ( empty( $paypal_order_id ) || empty( $order_uuid ) ) {
            return new WP_Error( 'missing_fields', 'PayPal order ID and order UUID are required.', array( 'status' => 400 ) );
        }

        $paypal = new BLC_Gala_PayPal();
        $result = $paypal->capture_order( $paypal_order_id );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( $result['status'] !== 'COMPLETED' ) {
            return new WP_Error( 'payment_not_completed', 'Payment was not completed.', array( 'status' => 400 ) );
        }

        // Complete the order internally
        $tickets_mgr = new BLC_Gala_Tickets();
        $completed   = $tickets_mgr->complete_order( $order_uuid, $paypal_order_id, $result['capture_id'] );

        if ( is_wp_error( $completed ) ) {
            return $completed;
        }

        // Send confirmation email with QR code
        $email = new BLC_Gala_Email();
        $email->send_ticket_email( $completed['order'], $completed['tickets'] );

        $confirmation = get_option( 'blc_gala_confirmation_message', 'Thank you for your purchase! Your ticket has been sent to your email.' );

        return rest_ensure_response( array(
            'success' => true,
            'message' => $confirmation,
            'tickets' => count( $completed['tickets'] ),
        ) );
    }

    /**
     * POST /create-donation — create a PayPal order for a donation.
     */
    public function create_donation( $request ) {
        $params = $request->get_json_params();

        $buyer_name  = isset( $params['buyer_name'] ) ? sanitize_text_field( $params['buyer_name'] ) : '';
        $buyer_email = isset( $params['buyer_email'] ) ? sanitize_email( $params['buyer_email'] ) : '';
        $amount      = isset( $params['amount'] ) ? floatval( $params['amount'] ) : 0;

        if ( empty( $buyer_name ) || empty( $buyer_email ) ) {
            return new WP_Error( 'missing_fields', 'Name and email are required.', array( 'status' => 400 ) );
        }

        if ( ! is_email( $buyer_email ) ) {
            return new WP_Error( 'invalid_email', 'Please provide a valid email address.', array( 'status' => 400 ) );
        }

        if ( $amount < 1 ) {
            return new WP_Error( 'invalid_amount', 'Minimum donation is $1.00.', array( 'status' => 400 ) );
        }

        $tickets_mgr = new BLC_Gala_Tickets();
        $order_data  = $tickets_mgr->create_donation_order( $buyer_name, $buyer_email, $amount );

        if ( is_wp_error( $order_data ) ) {
            return $order_data;
        }

        // Calculate total with PayPal fee for donation
        $fee_rate     = (float) get_option( 'blc_gala_paypal_fee_rate', 2.99 ) / 100;
        $fee_fixed    = (float) get_option( 'blc_gala_paypal_fee_fixed', 0.49 );
        $don_subtotal = $order_data['amount'];
        $don_charge   = ( $fee_rate > 0 || $fee_fixed > 0 )
            ? round( ( $don_subtotal + $fee_fixed ) / ( 1 - $fee_rate ), 2 )
            : $don_subtotal;

        $paypal      = new BLC_Gala_PayPal();
        $event_name  = get_option( 'blc_gala_event_name', 'Always on Mission Gala 2026' );
        $description = 'Donation — ' . $event_name;
        $paypal_order = $paypal->create_order( $order_data['order_uuid'], $don_charge, $description );

        if ( is_wp_error( $paypal_order ) ) {
            global $wpdb;
            $wpdb->delete( $wpdb->prefix . 'blc_gala_orders', array( 'order_uuid' => $order_data['order_uuid'] ) );
            return $paypal_order;
        }

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'blc_gala_orders',
            array( 'paypal_order_id' => $paypal_order['id'] ),
            array( 'order_uuid' => $order_data['order_uuid'] )
        );

        return rest_ensure_response( array(
            'paypal_order_id' => $paypal_order['id'],
            'order_uuid'      => $order_data['order_uuid'],
        ) );
    }

    /**
     * POST /capture-donation — capture donation payment.
     */
    public function capture_donation( $request ) {
        $params = $request->get_json_params();

        $paypal_order_id = isset( $params['paypal_order_id'] ) ? sanitize_text_field( $params['paypal_order_id'] ) : '';
        $order_uuid      = isset( $params['order_uuid'] ) ? sanitize_text_field( $params['order_uuid'] ) : '';

        if ( empty( $paypal_order_id ) || empty( $order_uuid ) ) {
            return new WP_Error( 'missing_fields', 'PayPal order ID and order UUID are required.', array( 'status' => 400 ) );
        }

        $paypal = new BLC_Gala_PayPal();
        $result = $paypal->capture_order( $paypal_order_id );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( $result['status'] !== 'COMPLETED' ) {
            return new WP_Error( 'payment_not_completed', 'Payment was not completed.', array( 'status' => 400 ) );
        }

        $tickets_mgr = new BLC_Gala_Tickets();
        $completed   = $tickets_mgr->complete_order( $order_uuid, $paypal_order_id, $result['capture_id'] );

        if ( is_wp_error( $completed ) ) {
            return $completed;
        }

        // Send donation thank you email
        $email = new BLC_Gala_Email();
        $email->send_donation_email( $completed['order'] );

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Thank you for your generous donation! A confirmation has been sent to your email.',
        ) );
    }

    /**
     * POST /validate/{code} — check in a ticket at the door.
     */
    public function validate_ticket( $request ) {
        $code        = $request->get_param( 'code' );
        $tickets_mgr = new BLC_Gala_Tickets();
        $result      = $tickets_mgr->validate_ticket( $code );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $result );
    }

    /**
     * GET /ticket-info/{code} — get ticket info without checking in.
     */
    public function get_ticket_info( $request ) {
        global $wpdb;
        $code = strtoupper( sanitize_text_field( $request->get_param( 'code' ) ) );

        $tickets_table = $wpdb->prefix . 'blc_gala_tickets';
        $orders_table  = $wpdb->prefix . 'blc_gala_orders';

        $ticket = $wpdb->get_row( $wpdb->prepare(
            "SELECT t.*, o.buyer_name, o.buyer_email, o.status AS order_status
             FROM {$tickets_table} t
             JOIN {$orders_table} o ON t.order_id = o.id
             WHERE t.ticket_code = %s",
            $code
        ) );

        if ( ! $ticket ) {
            return new WP_Error( 'not_found', 'Ticket not found.', array( 'status' => 404 ) );
        }

        return rest_ensure_response( array(
            'ticket_code'   => $ticket->ticket_code,
            'attendee_name' => $ticket->attendee_name,
            'buyer_email'   => $ticket->buyer_email,
            'is_checked_in' => (bool) $ticket->is_checked_in,
            'checked_in_at' => $ticket->checked_in_at,
            'order_status'  => $ticket->order_status,
        ) );
    }

    /**
     * POST /admin-auth — verify admin PIN for cash/check payments.
     */
    public function admin_auth( $request ) {
        $params = $request->get_json_params();
        $pin    = isset( $params['pin'] ) ? sanitize_text_field( $params['pin'] ) : '';

        $stored_pin = get_option( 'blc_gala_admin_pin', '' );

        if ( empty( $stored_pin ) || $pin !== $stored_pin ) {
            return new WP_Error( 'invalid_pin', 'Incorrect admin code.', array( 'status' => 401 ) );
        }

        $token = wp_hash( $stored_pin . '|admin|' . date( 'Y-m-d' ) );

        return rest_ensure_response( array(
            'token' => $token,
        ) );
    }

    /**
     * Permission callback for admin token (cash/check).
     */
    public function check_admin_token( $request ) {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        $token = $request->get_header( 'X-BLC-Admin-Token' );
        if ( empty( $token ) ) {
            return new WP_Error( 'missing_token', 'Admin authentication required.', array( 'status' => 401 ) );
        }

        $stored_pin     = get_option( 'blc_gala_admin_pin', '' );
        $expected_token = wp_hash( $stored_pin . '|admin|' . date( 'Y-m-d' ) );

        if ( ! hash_equals( $expected_token, $token ) ) {
            return new WP_Error( 'invalid_token', 'Admin token expired or invalid.', array( 'status' => 401 ) );
        }

        return true;
    }

    /**
     * POST /cash-check-order — process a cash or check ticket order.
     */
    public function cash_check_order( $request ) {
        $params = $request->get_json_params();

        $buyer_name    = isset( $params['buyer_name'] ) ? sanitize_text_field( $params['buyer_name'] ) : '';
        $buyer_email   = isset( $params['buyer_email'] ) ? sanitize_email( $params['buyer_email'] ) : '';
        $quantity      = isset( $params['quantity'] ) ? absint( $params['quantity'] ) : 1;
        $payment_method = isset( $params['payment_method'] ) ? sanitize_text_field( $params['payment_method'] ) : 'cash';
        $check_number  = isset( $params['check_number'] ) ? sanitize_text_field( $params['check_number'] ) : '';

        if ( empty( $buyer_name ) || empty( $buyer_email ) ) {
            return new WP_Error( 'missing_fields', 'Name and email are required.', array( 'status' => 400 ) );
        }

        if ( ! is_email( $buyer_email ) ) {
            return new WP_Error( 'invalid_email', 'Please provide a valid email address.', array( 'status' => 400 ) );
        }

        // Reserve tickets
        $tickets_mgr = new BLC_Gala_Tickets();
        $order_data  = $tickets_mgr->reserve_ticket( $buyer_name, $buyer_email, $quantity );

        if ( is_wp_error( $order_data ) ) {
            return $order_data;
        }

        // Immediately complete the order (no PayPal needed)
        $payment_ref = strtoupper( $payment_method );
        if ( $payment_method === 'check' && $check_number ) {
            $payment_ref = 'CHECK-' . $check_number;
        }

        $completed = $tickets_mgr->complete_order( $order_data['order_uuid'], $payment_ref, $payment_ref );

        if ( is_wp_error( $completed ) ) {
            return $completed;
        }

        // Send confirmation email with QR codes
        $email = new BLC_Gala_Email();
        $email->send_ticket_email( $completed['order'], $completed['tickets'] );

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Cash/check payment recorded. ' . count( $completed['tickets'] ) . ' ticket(s) sent to ' . $buyer_email,
            'tickets' => count( $completed['tickets'] ),
        ) );
    }

    /**
     * POST /test-paypal — test PayPal API credentials.
     */
    public function test_paypal( $request ) {
        $paypal    = new BLC_Gala_PayPal();
        $client_id = trim( get_option( 'blc_gala_paypal_client_id', '' ) );
        $secret    = trim( get_option( 'blc_gala_paypal_secret', '' ) );
        $sandbox   = get_option( 'blc_gala_paypal_sandbox', 1 );

        if ( empty( $client_id ) || empty( $secret ) ) {
            return rest_ensure_response( array(
                'success' => false,
                'message' => 'Client ID and Secret are both required. Please fill them in and save settings first.',
            ) );
        }

        $base_url = $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        $response = wp_remote_post( $base_url . '/v1/oauth2/token', array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $secret ),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body'    => 'grant_type=client_credentials',
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return rest_ensure_response( array(
                'success' => false,
                'message' => 'Could not connect to PayPal: ' . $response->get_error_message(),
            ) );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 && ! empty( $body['access_token'] ) ) {
            $mode = $sandbox ? 'Sandbox' : 'Live';
            return rest_ensure_response( array(
                'success' => true,
                'message' => 'PayPal connection successful! (' . $mode . ' mode)',
            ) );
        }

        $error = isset( $body['error_description'] ) ? $body['error_description'] : ( isset( $body['message'] ) ? $body['message'] : 'Unknown error' );
        $hint  = '';
        if ( $code === 401 ) {
            $hint = $sandbox
                ? ' Make sure you are using Sandbox credentials (not Live).'
                : ' Make sure you are using Live credentials (not Sandbox). Or enable Sandbox Mode if testing.';
        }

        return rest_ensure_response( array(
            'success' => false,
            'message' => 'PayPal returned error: ' . $error . $hint,
        ) );
    }

    public function check_admin_permission() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Generate a scanner token from the PIN (valid for the current day).
     */
    private function generate_scanner_token( $pin ) {
        return wp_hash( $pin . '|' . date( 'Y-m-d' ) );
    }

    /**
     * POST /scanner-auth — validate PIN and return a scanner token.
     */
    public function scanner_auth( $request ) {
        $params = $request->get_json_params();
        $pin    = isset( $params['pin'] ) ? sanitize_text_field( $params['pin'] ) : '';

        $stored_pin = get_option( 'blc_gala_scanner_pin', '' );

        if ( empty( $stored_pin ) || $pin !== $stored_pin ) {
            return new WP_Error( 'invalid_pin', 'Incorrect PIN. Please try again.', array( 'status' => 401 ) );
        }

        $token = $this->generate_scanner_token( $stored_pin );

        return rest_ensure_response( array(
            'token'   => $token,
            'expires' => date( 'Y-m-d', strtotime( '+1 day' ) ),
        ) );
    }

    /**
     * Permission callback: verify the X-BLC-Scanner-Token header.
     */
    public function check_scanner_token( $request ) {
        // Also allow WordPress admins through
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        $token = $request->get_header( 'X-BLC-Scanner-Token' );
        if ( empty( $token ) ) {
            return new WP_Error( 'missing_token', 'Scanner authentication required.', array( 'status' => 401 ) );
        }

        $stored_pin    = get_option( 'blc_gala_scanner_pin', '' );
        $expected_token = $this->generate_scanner_token( $stored_pin );

        if ( ! hash_equals( $expected_token, $token ) ) {
            return new WP_Error( 'invalid_token', 'Scanner token expired or invalid. Please re-enter the PIN.', array( 'status' => 401 ) );
        }

        return true;
    }

    /**
     * GET /checkin-stats — return check-in statistics for the scanner UI.
     */
    public function get_checkin_stats( $request ) {
        $stats = BLC_Gala_Scanner::get_stats();
        return rest_ensure_response( $stats );
    }
}
