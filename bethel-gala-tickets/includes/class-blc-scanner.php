<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Scanner helper class — primarily used for the admin scanner page functionality.
 * The actual check-in logic lives in BLC_Gala_Tickets::validate_ticket().
 */
class BLC_Gala_Scanner {

    /**
     * Get check-in statistics.
     */
    public static function get_stats() {
        global $wpdb;
        $tickets_table = $wpdb->prefix . 'blc_gala_tickets';
        $orders_table  = $wpdb->prefix . 'blc_gala_orders';

        $total_tickets = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tickets_table} t
             JOIN {$orders_table} o ON t.order_id = o.id
             WHERE o.status = 'completed'"
        );

        $checked_in = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tickets_table} t
             JOIN {$orders_table} o ON t.order_id = o.id
             WHERE o.status = 'completed' AND t.is_checked_in = 1"
        );

        // Get list of checked-in guests
        $guests = $wpdb->get_results(
            "SELECT t.attendee_name, t.checked_in_at
             FROM {$tickets_table} t
             JOIN {$orders_table} o ON t.order_id = o.id
             WHERE o.status = 'completed' AND t.is_checked_in = 1
             ORDER BY t.checked_in_at DESC"
        );

        $guest_list = array();
        foreach ( $guests as $guest ) {
            $guest_list[] = array(
                'name'          => $guest->attendee_name,
                'checked_in_at' => $guest->checked_in_at,
            );
        }

        return array(
            'total_tickets' => $total_tickets,
            'checked_in'    => $checked_in,
            'remaining'     => $total_tickets - $checked_in,
            'guests'        => $guest_list,
        );
    }
}
