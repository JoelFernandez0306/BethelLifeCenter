<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BLC_Gala_Shortcode {

    public function __construct() {
        add_shortcode( 'blc_gala_tickets', array( $this, 'render' ) );
        add_shortcode( 'blc_gala_scanner', array( $this, 'render_scanner' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
        add_action( 'template_redirect', array( $this, 'detect_page_url' ) );
    }

    /**
     * Auto-detect and save the URL of the page containing our shortcode.
     */
    public function detect_page_url() {
        global $post;
        if ( $post && has_shortcode( $post->post_content, 'blc_gala_tickets' ) ) {
            $url = get_permalink( $post );
            if ( $url && $url !== get_option( 'blc_gala_page_url' ) ) {
                update_option( 'blc_gala_page_url', $url );
            }
        }
    }

    /**
     * Enqueue assets only on pages that use the shortcode.
     */
    public function maybe_enqueue_assets() {
        global $post;
        if ( ! $post ) {
            return;
        }

        $has_tickets = has_shortcode( $post->post_content, 'blc_gala_tickets' );
        $has_scanner = has_shortcode( $post->post_content, 'blc_gala_scanner' );

        if ( ! $has_tickets && ! $has_scanner ) {
            return;
        }

        wp_enqueue_style( 'blc-gala-frontend', BLC_GALA_PLUGIN_URL . 'public/css/gala-frontend.css', array(), BLC_GALA_VERSION );

        // Scanner shortcode assets
        if ( $has_scanner ) {
            wp_enqueue_script( 'html5-qrcode', 'https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js', array(), '2.3.8', true );
            wp_enqueue_script( 'blc-gala-scanner', BLC_GALA_PLUGIN_URL . 'public/js/gala-scanner.js', array( 'html5-qrcode' ), BLC_GALA_VERSION, true );
            wp_localize_script( 'blc-gala-scanner', 'blcScanner', array(
                'restUrl' => rest_url( 'blc-gala/v1/' ),
            ) );
        }

        if ( ! $has_tickets ) {
            return;
        }

        // PayPal JS SDK
        $paypal = new BLC_Gala_PayPal();
        if ( $paypal->is_configured() ) {
            wp_enqueue_script( 'paypal-sdk', $paypal->get_sdk_url(), array(), null, true );
        }

        wp_enqueue_script( 'blc-gala-frontend', BLC_GALA_PLUGIN_URL . 'public/js/gala-frontend.js', array( 'paypal-sdk' ), BLC_GALA_VERSION, true );

        $tickets_mgr = new BLC_Gala_Tickets();
        wp_localize_script( 'blc-gala-frontend', 'blcGala', array(
            'restUrl'       => rest_url( 'blc-gala/v1/' ),
            'nonce'         => wp_create_nonce( 'wp_rest' ),
            'remaining'     => $tickets_mgr->get_remaining_count(),
            'total'         => (int) get_option( 'blc_gala_total_tickets', 100 ),
            'price'         => (float) get_option( 'blc_gala_ticket_price', 50.00 ),
            'maxPerOrder'   => (int) get_option( 'blc_gala_max_per_order', 1 ),
            'eventDate'     => get_option( 'blc_gala_event_date', '' ),
            'eventName'     => get_option( 'blc_gala_event_name', '' ),
            'soldOut'       => $tickets_mgr->is_sold_out(),
            'donationEnabled' => (bool) get_option( 'blc_gala_donation_enabled', 1 ),
            'accentColor'   => get_option( 'blc_gala_accent_color', '#C9A84C' ),
            'secondaryColor' => get_option( 'blc_gala_secondary_color', '#1B2A4A' ),
        ) );
    }

    /**
     * Render the shortcode output.
     */
    public function render( $atts ) {
        $tickets_mgr = new BLC_Gala_Tickets();
        $remaining   = $tickets_mgr->get_remaining_count();
        $total       = (int) get_option( 'blc_gala_total_tickets', 100 );
        $sold_out    = $remaining <= 0;
        $price       = (float) get_option( 'blc_gala_ticket_price', 50.00 );
        $event_name  = get_option( 'blc_gala_event_name', 'Always on Mission Gala 2026' );
        $tagline     = get_option( 'blc_gala_event_tagline', 'Hosted by Bethel Life Center' );
        $event_date  = get_option( 'blc_gala_event_date', '' );
        $accent      = get_option( 'blc_gala_accent_color', '#C9A84C' );
        $secondary   = get_option( 'blc_gala_secondary_color', '#1B2A4A' );
        $donation_msg = get_option( 'blc_gala_donation_message', '' );
        $donation_on  = get_option( 'blc_gala_donation_enabled', 1 );

        $formatted_date = '';
        if ( $event_date ) {
            $formatted_date = date_i18n( 'l, F j, Y — g:i A', strtotime( $event_date ) );
        }

        ob_start();
        ?>
        <div id="blc-gala-wrapper" class="blc-gala-wrapper" style="--blc-accent: <?php echo esc_attr( $accent ); ?>; --blc-secondary: <?php echo esc_attr( $secondary ); ?>;">

            <!-- Hero Section -->
            <div class="blc-gala-hero">
                <h1 class="blc-gala-title"><?php echo esc_html( $event_name ); ?></h1>
                <p class="blc-gala-tagline"><?php echo esc_html( $tagline ); ?></p>
                <?php if ( $formatted_date ) : ?>
                    <p class="blc-gala-date"><?php echo esc_html( $formatted_date ); ?></p>
                <?php endif; ?>
            </div>

            <!-- Countdown Timer -->
            <?php if ( $event_date ) : ?>
            <div class="blc-gala-countdown" id="blc-gala-countdown">
                <div class="blc-countdown-item">
                    <span class="blc-countdown-number" id="blc-countdown-days">--</span>
                    <span class="blc-countdown-label">Days</span>
                </div>
                <div class="blc-countdown-item">
                    <span class="blc-countdown-number" id="blc-countdown-hours">--</span>
                    <span class="blc-countdown-label">Hours</span>
                </div>
                <div class="blc-countdown-item">
                    <span class="blc-countdown-number" id="blc-countdown-minutes">--</span>
                    <span class="blc-countdown-label">Minutes</span>
                </div>
                <div class="blc-countdown-item">
                    <span class="blc-countdown-number" id="blc-countdown-seconds">--</span>
                    <span class="blc-countdown-label">Seconds</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Ticket Counter -->
            <div class="blc-gala-counter" id="blc-gala-counter">
                <div class="blc-counter-bar-wrapper">
                    <div class="blc-counter-bar" style="width: <?php echo esc_attr( round( ( ( $total - $remaining ) / max( $total, 1 ) ) * 100 ) ); ?>%"></div>
                </div>
                <p class="blc-counter-text">
                    <span id="blc-remaining-count"><?php echo esc_html( $remaining ); ?></span>
                    of <?php echo esc_html( $total ); ?> tickets remaining
                </p>
            </div>

            <!-- Ticket Purchase Section -->
            <div id="blc-gala-ticket-section" class="blc-gala-section" <?php echo $sold_out ? 'style="display:none;"' : ''; ?>>
                <h2 class="blc-gala-section-title">Get Your Ticket</h2>
                <p class="blc-gala-price">$<?php echo esc_html( number_format( $price, 2 ) ); ?> per person</p>

                <form id="blc-gala-ticket-form" class="blc-gala-form">
                    <div class="blc-form-group">
                        <label for="blc-ticket-name">Full Name <span class="blc-required">*</span></label>
                        <input type="text" id="blc-ticket-name" name="buyer_name" required placeholder="Enter your full name" />
                    </div>
                    <div class="blc-form-group">
                        <label for="blc-ticket-email">Email Address <span class="blc-required">*</span></label>
                        <input type="email" id="blc-ticket-email" name="buyer_email" required placeholder="Enter your email address" />
                    </div>

                    <div id="blc-paypal-button-container" class="blc-paypal-buttons"></div>

                    <div id="blc-ticket-message" class="blc-message" style="display: none;"></div>
                </form>
            </div>

            <!-- Sold Out Section -->
            <div id="blc-gala-soldout-section" class="blc-gala-section blc-gala-soldout" <?php echo ! $sold_out ? 'style="display:none;"' : ''; ?>>
                <div class="blc-soldout-badge">SOLD OUT</div>
                <p class="blc-soldout-text"><?php echo wp_kses_post( $donation_msg ); ?></p>
            </div>

            <!-- Donation Section -->
            <?php if ( $donation_on ) : ?>
            <div id="blc-gala-donation-section" class="blc-gala-section">
                <h2 class="blc-gala-section-title">Support Our Mission</h2>
                <p class="blc-gala-description">Your generous donation helps support our missions work around the world. Every dollar makes a difference.</p>

                <form id="blc-gala-donation-form" class="blc-gala-form">
                    <div class="blc-form-group">
                        <label for="blc-donation-name">Full Name <span class="blc-required">*</span></label>
                        <input type="text" id="blc-donation-name" name="donor_name" required placeholder="Enter your full name" />
                    </div>
                    <div class="blc-form-group">
                        <label for="blc-donation-email">Email Address <span class="blc-required">*</span></label>
                        <input type="email" id="blc-donation-email" name="donor_email" required placeholder="Enter your email address" />
                    </div>
                    <div class="blc-form-group">
                        <label for="blc-donation-amount">Donation Amount ($) <span class="blc-required">*</span></label>
                        <div class="blc-amount-presets">
                            <button type="button" class="blc-preset-btn" data-amount="25">$25</button>
                            <button type="button" class="blc-preset-btn" data-amount="50">$50</button>
                            <button type="button" class="blc-preset-btn" data-amount="100">$100</button>
                            <button type="button" class="blc-preset-btn" data-amount="250">$250</button>
                        </div>
                        <input type="number" id="blc-donation-amount" name="donation_amount" required min="1" step="0.01" placeholder="Enter amount" />
                    </div>

                    <div id="blc-paypal-donation-container" class="blc-paypal-buttons"></div>

                    <div id="blc-donation-message" class="blc-message" style="display: none;"></div>
                </form>
            </div>
            <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the [blc_gala_scanner] shortcode — PIN-protected public scanner.
     */
    public function render_scanner( $atts ) {
        $accent    = get_option( 'blc_gala_accent_color', '#C9A84C' );
        $secondary = get_option( 'blc_gala_secondary_color', '#1B2A4A' );

        ob_start();
        ?>
        <div id="blc-scanner-wrapper" class="blc-gala-wrapper blc-scanner-wrapper" style="--blc-accent: <?php echo esc_attr( $accent ); ?>; --blc-secondary: <?php echo esc_attr( $secondary ); ?>;">

            <!-- PIN Entry -->
            <div id="blc-scanner-pin-section" class="blc-scanner-pin-section">
                <div class="blc-gala-hero">
                    <h1 class="blc-gala-title">Door Scanner</h1>
                    <p class="blc-gala-tagline"><?php echo esc_html( get_option( 'blc_gala_event_name', 'Always on Mission Gala 2026' ) ); ?></p>
                </div>
                <div class="blc-gala-section" style="text-align: center; margin-top: 20px;">
                    <h2 class="blc-gala-section-title">Enter Scanner PIN</h2>
                    <form id="blc-scanner-pin-form" class="blc-gala-form" style="max-width: 300px; margin: 20px auto;">
                        <div class="blc-form-group">
                            <input type="password" id="blc-scanner-pin-input" class="blc-scanner-pin-input" placeholder="Enter PIN" maxlength="8" inputmode="numeric" autocomplete="off" required />
                        </div>
                        <button type="submit" class="blc-scanner-pin-btn">Access Scanner</button>
                    </form>
                    <div id="blc-pin-message" class="blc-message" style="display: none;"></div>
                </div>
            </div>

            <!-- Scanner UI (hidden until authenticated) -->
            <div id="blc-scanner-ui-section" class="blc-scanner-ui-section" style="display: none;">
                <div class="blc-gala-hero">
                    <h1 class="blc-gala-title">Door Scanner</h1>
                    <p class="blc-gala-tagline"><?php echo esc_html( get_option( 'blc_gala_event_name', 'Always on Mission Gala 2026' ) ); ?></p>
                </div>

                <div class="blc-scanner-stats-bar" id="blc-scanner-stats-bar" style="margin-top: 20px;">
                    <span id="blc-scanner-checked-in">0</span> checked in
                </div>

                <div class="blc-gala-section" style="margin-top: 20px;">
                    <div class="blc-scanner-controls">
                        <button id="blc-scan-start-camera" class="blc-scanner-btn blc-scanner-btn-primary">Start Camera</button>
                        <button id="blc-scan-stop-camera" class="blc-scanner-btn" style="display: none;">Stop Camera</button>
                    </div>

                    <div id="blc-scan-qr-reader" style="width: 100%; max-width: 400px; margin: 15px auto;"></div>

                    <div class="blc-scanner-manual" style="margin-top: 20px;">
                        <label for="blc-scan-manual-code" style="font-weight: 600; display: block; margin-bottom: 6px;">Or enter ticket code:</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" id="blc-scan-manual-code" placeholder="Ticket code" style="flex: 1; text-transform: uppercase;" />
                            <button id="blc-scan-manual-btn" class="blc-scanner-btn blc-scanner-btn-primary">Check In</button>
                        </div>
                    </div>
                </div>

                <!-- Scan Result -->
                <div id="blc-scan-result" class="blc-scan-result" style="display: none;">
                    <div id="blc-scan-result-icon" class="blc-scan-result-icon"></div>
                    <div id="blc-scan-result-name" class="blc-scan-result-name"></div>
                    <div id="blc-scan-result-status" class="blc-scan-result-status"></div>
                </div>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }
}
