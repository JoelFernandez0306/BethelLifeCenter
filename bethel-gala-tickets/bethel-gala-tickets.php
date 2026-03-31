<?php
/**
 * Plugin Name: Always on Mission Gala Tickets
 * Plugin URI:  https://bethellifecenter.org
 * Description: Ticket sales, QR code tickets, live availability counter, and donations for the Always on Mission Gala 2026 hosted by Bethel Life Center.
 * Version:     1.1.0
 * Author:      Bethel Life Center
 * Author URI:  https://bethellifecenter.org
 * License:     GPL-2.0+
 * Text Domain: blc-gala
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BLC_GALA_VERSION', '1.1.0' );
define( 'BLC_GALA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLC_GALA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BLC_GALA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Activation hook — create database tables and default options.
 */
function blc_gala_activate() {
    require_once BLC_GALA_PLUGIN_DIR . 'includes/class-blc-activator.php';
    BLC_Gala_Activator::activate();
}
register_activation_hook( __FILE__, 'blc_gala_activate' );

/**
 * Uninstall is handled by uninstall.php (auto-loaded by WordPress).
 */

/**
 * Load plugin classes after all plugins are loaded.
 */
function blc_gala_init() {
    // Core classes
    require_once BLC_GALA_PLUGIN_DIR . 'includes/class-blc-tickets.php';
    require_once BLC_GALA_PLUGIN_DIR . 'includes/class-blc-qr-generator.php';
    require_once BLC_GALA_PLUGIN_DIR . 'includes/class-blc-paypal.php';
    require_once BLC_GALA_PLUGIN_DIR . 'includes/class-blc-email.php';
    require_once BLC_GALA_PLUGIN_DIR . 'includes/class-blc-rest-api.php';
    require_once BLC_GALA_PLUGIN_DIR . 'includes/class-blc-shortcode.php';
    require_once BLC_GALA_PLUGIN_DIR . 'includes/class-blc-scanner.php';

    // Admin classes (only in admin context)
    if ( is_admin() ) {
        require_once BLC_GALA_PLUGIN_DIR . 'includes/class-blc-admin.php';
        new BLC_Gala_Admin();
    }

    // Initialize public-facing components
    new BLC_Gala_Shortcode();
    new BLC_Gala_REST_API();
}
add_action( 'plugins_loaded', 'blc_gala_init' );
