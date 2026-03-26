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

        return array(
            'total_tickets' => $total_tickets,
            'checked_in'    => $checked_in,
            'remaining'     => $total_tickets - $checked_in,
        );
    }
}
