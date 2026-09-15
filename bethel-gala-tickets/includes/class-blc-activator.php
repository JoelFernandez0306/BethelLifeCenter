<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BLC_Gala_Activator {

    public static function activate() {
        self::create_tables();
        self::set_default_options();
    }

    /**
     * Run the schema/defaults pass when the plugin files have been updated
     * without the plugin being deactivated and reactivated. dbDelta only
     * applies the differences, so this is a no-op once the version matches.
     */
    public static function maybe_upgrade() {
        if ( get_option( 'blc_gala_db_version' ) === BLC_GALA_VERSION ) {
            return;
        }

        self::create_tables();
        self::set_default_options();
    }

    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $orders_table = $wpdb->prefix . 'blc_gala_orders';
        $tickets_table = $wpdb->prefix . 'blc_gala_tickets';

        $sql = "CREATE TABLE {$orders_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_uuid VARCHAR(36) NOT NULL,
            order_type ENUM('ticket','donation','quickpay') NOT NULL DEFAULT 'ticket',
            buyer_name VARCHAR(255) NOT NULL,
            buyer_email VARCHAR(255) NOT NULL,
            quantity INT UNSIGNED NOT NULL DEFAULT 1,
            amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            paypal_order_id VARCHAR(255) DEFAULT NULL,
            paypal_capture_id VARCHAR(255) DEFAULT NULL,
            payer_name VARCHAR(255) DEFAULT NULL,
            card_last4 VARCHAR(4) DEFAULT NULL,
            card_brand VARCHAR(32) DEFAULT NULL,
            payment_label VARCHAR(255) DEFAULT NULL,
            status ENUM('pending','completed','refunded','failed') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY order_uuid (order_uuid),
            KEY status (status),
            KEY order_type (order_type)
        ) {$charset_collate};

        CREATE TABLE {$tickets_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            ticket_code VARCHAR(64) NOT NULL,
            attendee_name VARCHAR(255) NOT NULL,
            is_checked_in TINYINT(1) NOT NULL DEFAULT 0,
            checked_in_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY ticket_code (ticket_code),
            KEY order_id (order_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'blc_gala_db_version', BLC_GALA_VERSION );
    }

    private static function set_default_options() {
        $defaults = array(
            'blc_gala_event_name'      => 'Always on Mission Gala 2026',
            'blc_gala_event_tagline'   => 'Hosted by Bethel Life Center',
            'blc_gala_event_address'   => "109 Club House Court\nEast Stroudsburg, PA 18335",
            'blc_gala_event_date'      => '2026-10-17 18:00:00',
            'blc_gala_total_tickets'   => 100,
            'blc_gala_ticket_price'    => 50.00,
            'blc_gala_max_per_order'   => 10,
            'blc_gala_paypal_client_id'    => '',
            'blc_gala_paypal_secret'       => '',
            'blc_gala_paypal_sandbox'      => 1,
            'blc_gala_donation_enabled'    => 1,
            'blc_gala_donation_message'    => 'All gala tickets have been claimed! You can still support our missions with a generous donation.',
            'blc_gala_confirmation_message' => 'Thank you for your purchase! Your ticket with a QR code has been sent to your email. Please present it at the door.',
            'blc_gala_scanner_pin'         => '1234',
            'blc_gala_admin_pin'           => '0000',
            'blc_gala_paypal_fee_rate'     => 2.99,
            'blc_gala_paypal_fee_fixed'    => 0.49,
            'blc_gala_accent_color'        => '#C9A84C',
            'blc_gala_secondary_color'     => '#1B2A4A',
        );

        foreach ( $defaults as $key => $value ) {
            if ( get_option( $key ) === false ) {
                add_option( $key, $value );
            }
        }

        // Upgrade: if max_per_order was set to 1 from an older version, bump to 10
        if ( (int) get_option( 'blc_gala_max_per_order' ) === 1 ) {
            update_option( 'blc_gala_max_per_order', 10 );
        }
    }
}
