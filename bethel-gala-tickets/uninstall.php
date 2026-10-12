<?php
/**
 * Fired when the plugin is uninstalled.
 * Removes all database tables and options created by the plugin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop custom tables
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}blc_gala_tickets" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}blc_gala_orders" );

// Remove all plugin options
$options = array(
    'blc_gala_event_name',
    'blc_gala_event_tagline',
    'blc_gala_event_date',
    'blc_gala_total_tickets',
    'blc_gala_ticket_price',
    'blc_gala_max_per_order',
    'blc_gala_paypal_client_id',
    'blc_gala_paypal_secret',
    'blc_gala_paypal_sandbox',
    'blc_gala_donation_enabled',
    'blc_gala_donation_message',
    'blc_gala_confirmation_message',
    'blc_gala_accent_color',
    'blc_gala_secondary_color',
    'blc_gala_scanner_pin',
    'blc_gala_page_url',
    'blc_gala_db_version',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove uploaded QR files
$upload_dir = wp_upload_dir();
$gala_dir = $upload_dir['basedir'] . '/blc-gala/';
if ( is_dir( $gala_dir ) ) {
    array_map( 'unlink', glob( $gala_dir . '*' ) );
    rmdir( $gala_dir );
}
