<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BLC_Gala_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_init', array( $this, 'handle_quickpay_actions' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Add or delete a Quick Pay QR code.
     *
     * These live outside the Settings API because they are a repeatable list
     * rather than a fixed set of fields, so they get their own form and nonce.
     */
    public function handle_quickpay_actions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_POST['blc_qp_add'] ) ) {
            check_admin_referer( 'blc_qp_add' );

            $result = BLC_Gala_QuickPay::add_item(
                isset( $_POST['blc_qp_label'] ) ? wp_unslash( $_POST['blc_qp_label'] ) : '',
                isset( $_POST['blc_qp_amount'] ) ? wp_unslash( $_POST['blc_qp_amount'] ) : 0
            );

            $notice = is_wp_error( $result ) ? 'invalid' : 'added';
            wp_safe_redirect( admin_url( 'admin.php?page=blc-gala-settings&blc_qp_notice=' . $notice . '#blc-quickpay' ) );
            exit;
        }

        if ( isset( $_GET['blc_qp_delete'] ) ) {
            check_admin_referer( 'blc_qp_delete' );

            BLC_Gala_QuickPay::delete_item( sanitize_text_field( wp_unslash( $_GET['blc_qp_delete'] ) ) );
            wp_safe_redirect( admin_url( 'admin.php?page=blc-gala-settings&blc_qp_notice=deleted#blc-quickpay' ) );
            exit;
        }
    }

    public function add_menu_pages() {
        add_menu_page(
            'Gala Tickets',
            'Gala Tickets',
            'manage_options',
            'blc-gala-settings',
            array( $this, 'render_settings_page' ),
            'dashicons-tickets-alt',
            30
        );

        add_submenu_page(
            'blc-gala-settings',
            'Settings',
            'Settings',
            'manage_options',
            'blc-gala-settings',
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            'blc-gala-settings',
            'Orders',
            'Orders',
            'manage_options',
            'blc-gala-orders',
            array( $this, 'render_orders_page' )
        );

        add_submenu_page(
            'blc-gala-settings',
            'Door Scanner',
            'Door Scanner',
            'manage_options',
            'blc-gala-scanner',
            array( $this, 'render_scanner_page' )
        );
    }

    public function register_settings() {
        // Event settings
        register_setting( 'blc_gala_settings', 'blc_gala_event_name', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'blc_gala_settings', 'blc_gala_event_tagline', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'blc_gala_settings', 'blc_gala_event_address', array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
        register_setting( 'blc_gala_settings', 'blc_gala_event_date', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'blc_gala_settings', 'blc_gala_total_tickets', array( 'sanitize_callback' => 'absint' ) );
        register_setting( 'blc_gala_settings', 'blc_gala_ticket_price', array( 'sanitize_callback' => 'floatval' ) );
        register_setting( 'blc_gala_settings', 'blc_gala_max_per_order', array( 'sanitize_callback' => 'absint' ) );
        register_setting( 'blc_gala_settings', 'blc_gala_manual_sold', array( 'sanitize_callback' => 'absint' ) );

        // PayPal settings
        register_setting( 'blc_gala_settings', 'blc_gala_paypal_client_id', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'blc_gala_settings', 'blc_gala_paypal_secret', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'blc_gala_settings', 'blc_gala_paypal_sandbox', array( 'sanitize_callback' => 'absint' ) );

        // Scanner settings
        register_setting( 'blc_gala_settings', 'blc_gala_scanner_pin', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'blc_gala_settings', 'blc_gala_admin_pin', array( 'sanitize_callback' => 'sanitize_text_field' ) );

        // Fee settings
        register_setting( 'blc_gala_settings', 'blc_gala_paypal_fee_rate', array( 'sanitize_callback' => 'floatval' ) );
        register_setting( 'blc_gala_settings', 'blc_gala_paypal_fee_fixed', array( 'sanitize_callback' => 'floatval' ) );

        // Display settings
        register_setting( 'blc_gala_settings', 'blc_gala_donation_enabled', array( 'sanitize_callback' => 'absint' ) );
        register_setting( 'blc_gala_settings', 'blc_gala_donation_message', array( 'sanitize_callback' => 'wp_kses_post' ) );
        register_setting( 'blc_gala_settings', 'blc_gala_confirmation_message', array( 'sanitize_callback' => 'wp_kses_post' ) );
        register_setting( 'blc_gala_settings', 'blc_gala_accent_color', array( 'sanitize_callback' => 'sanitize_hex_color' ) );
        register_setting( 'blc_gala_settings', 'blc_gala_secondary_color', array( 'sanitize_callback' => 'sanitize_hex_color' ) );
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'blc-gala' ) === false ) {
            return;
        }
        wp_enqueue_style( 'blc-gala-admin', BLC_GALA_PLUGIN_URL . 'admin/css/admin.css', array(), BLC_GALA_VERSION );
        wp_enqueue_script( 'blc-gala-admin', BLC_GALA_PLUGIN_URL . 'admin/js/admin.js', array( 'jquery' ), BLC_GALA_VERSION, true );

        if ( $hook === 'gala-tickets_page_blc-gala-scanner' ) {
            wp_enqueue_script( 'html5-qrcode', 'https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js', array(), '2.3.8', true );
        }

        wp_localize_script( 'blc-gala-admin', 'blcGalaAdmin', array(
            'restUrl' => rest_url( 'blc-gala/v1/' ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
        ) );
    }

    public function render_settings_page() {
        require_once BLC_GALA_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    public function render_orders_page() {
        require_once BLC_GALA_PLUGIN_DIR . 'admin/views/orders-page.php';
    }

    public function render_scanner_page() {
        require_once BLC_GALA_PLUGIN_DIR . 'admin/views/scanner-page.php';
    }
}
