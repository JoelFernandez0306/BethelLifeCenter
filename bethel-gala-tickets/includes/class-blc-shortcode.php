<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BLC_Gala_Shortcode {

    public function __construct() {
        add_shortcode( 'blc_gala_tickets', array( $this, 'render' ) );
        add_shortcode( 'blc_gala_scanner', array( $this, 'render_scanner' ) );
        add_shortcode( 'blc_gala_quickpay', array( $this, 'render_quickpay' ) );
        add_shortcode( 'blc_gala_quickpay_purchases', array( $this, 'render_quickpay_purchases' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
        add_action( 'template_redirect', array( $this, 'detect_page_url' ) );
    }

    /**
     * Auto-detect and save the URL of the pages containing our shortcodes,
     * so the admin screen knows where the QR codes should point.
     */
    public function detect_page_url() {
        global $post;
        if ( ! $post ) {
            return;
        }

        if ( has_shortcode( $post->post_content, 'blc_gala_tickets' ) ) {
            $url = get_permalink( $post );
            if ( $url && $url !== get_option( 'blc_gala_page_url' ) ) {
                update_option( 'blc_gala_page_url', $url );
            }
        }

        if ( has_shortcode( $post->post_content, 'blc_gala_quickpay' ) ) {
            $url = get_permalink( $post );
            if ( $url && $url !== get_option( 'blc_gala_quickpay_page_url' ) ) {
                update_option( 'blc_gala_quickpay_page_url', $url );
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

        $has_tickets   = has_shortcode( $post->post_content, 'blc_gala_tickets' );
        $has_scanner   = has_shortcode( $post->post_content, 'blc_gala_scanner' );
        $has_quickpay  = has_shortcode( $post->post_content, 'blc_gala_quickpay' );
        $has_qp_report = has_shortcode( $post->post_content, 'blc_gala_quickpay_purchases' );

        if ( ! $has_tickets && ! $has_scanner && ! $has_quickpay && ! $has_qp_report ) {
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

        // Quick Pay payment page and purchase log
        if ( $has_quickpay || $has_qp_report ) {
            $qp_paypal = new BLC_Gala_PayPal();
            $qp_deps   = array();

            if ( $has_quickpay && $qp_paypal->is_configured() ) {
                wp_enqueue_script( 'paypal-sdk', $qp_paypal->get_sdk_url(), array(), null, true );
                $qp_deps[] = 'paypal-sdk';
            }

            wp_enqueue_script( 'blc-gala-quickpay', BLC_GALA_PLUGIN_URL . 'public/js/gala-quickpay.js', $qp_deps, BLC_GALA_VERSION, true );

            $item_id = isset( $_GET['blc_qp'] ) ? sanitize_text_field( wp_unslash( $_GET['blc_qp'] ) ) : '';
            $item    = BLC_Gala_QuickPay::get_item( $item_id );

            wp_add_inline_script( 'blc-gala-quickpay', 'window.blcQuickPay = ' . wp_json_encode( array(
                'restUrl' => rest_url( 'blc-gala/v1/' ),
                'itemId'  => $item ? $item['id'] : '',
                'label'   => $item ? $item['label'] : '',
            ) ) . ';', 'before' );
        }

        if ( ! $has_tickets ) {
            return;
        }

        // PayPal JS SDK (only load if configured)
        $paypal = new BLC_Gala_PayPal();
        $js_deps = array();
        if ( $paypal->is_configured() ) {
            wp_enqueue_script( 'paypal-sdk', $paypal->get_sdk_url(), array(), null, true );
            $js_deps[] = 'paypal-sdk';
        }

        wp_enqueue_script( 'blc-gala-frontend', BLC_GALA_PLUGIN_URL . 'public/js/gala-frontend.js', $js_deps, BLC_GALA_VERSION, true );

        $tickets_mgr = new BLC_Gala_Tickets();
        $config_data = array(
            'restUrl'         => rest_url( 'blc-gala/v1/' ),
            'nonce'           => wp_create_nonce( 'wp_rest' ),
            'remaining'       => (int) $tickets_mgr->get_remaining_count(),
            'total'           => (int) get_option( 'blc_gala_total_tickets', 100 ),
            'price'           => (float) get_option( 'blc_gala_ticket_price', 50.00 ),
            'maxPerOrder'     => (int) get_option( 'blc_gala_max_per_order', 1 ),
            'eventDate'       => get_option( 'blc_gala_event_date', '' ),
            'eventName'       => get_option( 'blc_gala_event_name', '' ),
            'soldOut'         => (bool) $tickets_mgr->is_sold_out(),
            'donationEnabled' => (bool) get_option( 'blc_gala_donation_enabled', 1 ),
            'paypalConfigured' => (bool) $paypal->is_configured(),
            'feeRate'         => (float) get_option( 'blc_gala_paypal_fee_rate', 2.99 ),
            'feeFixed'        => (float) get_option( 'blc_gala_paypal_fee_fixed', 0.49 ),
            'accentColor'     => get_option( 'blc_gala_accent_color', '#C9A84C' ),
            'secondaryColor'  => get_option( 'blc_gala_secondary_color', '#1B2A4A' ),
        );
        wp_add_inline_script( 'blc-gala-frontend', 'window.blcGala = ' . wp_json_encode( $config_data ) . ';', 'before' );
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
                <?php
                $address = get_option( 'blc_gala_event_address', '' );
                if ( $address ) : ?>
                    <p class="blc-gala-date"><?php echo nl2br( esc_html( $address ) ); ?></p>
                <?php endif; ?>
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
                <h2 class="blc-gala-section-title">Get Your Tickets</h2>
                <p class="blc-gala-price">$<?php echo esc_html( number_format( $price, 2 ) ); ?> per person</p>

                <form id="blc-gala-ticket-form" class="blc-gala-form">
                    <div class="blc-form-row">
                        <div class="blc-form-group blc-form-half">
                            <label for="blc-ticket-fname">First Name <span class="blc-required">*</span></label>
                            <input type="text" id="blc-ticket-fname" name="first_name" required placeholder="First name" />
                        </div>
                        <div class="blc-form-group blc-form-half">
                            <label for="blc-ticket-lname">Last Name <span class="blc-required">*</span></label>
                            <input type="text" id="blc-ticket-lname" name="last_name" required placeholder="Last name" />
                        </div>
                    </div>
                    <div class="blc-form-group">
                        <label for="blc-ticket-email">Email Address <span class="blc-required">*</span></label>
                        <input type="email" id="blc-ticket-email" name="buyer_email" required placeholder="Enter your email address" />
                        <p class="blc-form-hint">All ticket QR codes will be sent to this email.</p>
                    </div>
                    <div class="blc-form-group">
                        <label for="blc-ticket-quantity">Number of Tickets <span class="blc-required">*</span></label>
                        <select id="blc-ticket-quantity" name="quantity" required>
                            <?php
                            $max_per_order = (int) get_option( 'blc_gala_max_per_order', 10 );
                            for ( $i = 1; $i <= $max_per_order; $i++ ) :
                                $label = $i . ' ' . ( $i === 1 ? 'ticket' : 'tickets' ) . ' — $' . number_format( $price * $i, 2 );
                            ?>
                                <option value="<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endfor; ?>
                        </select>
                        <div class="blc-form-total" style="font-size: 1.2em; color: <?php echo esc_attr( $secondary ); ?>; margin: 10px 0 0;">
                            <div>Subtotal: <strong id="blc-ticket-total" style="color: <?php echo esc_attr( $accent ); ?>; font-size: 1.1em;">$<?php echo esc_html( number_format( $price, 2 ) ); ?></strong></div>
                            <div class="blc-fee-line" style="font-size: 0.9em; color: #666666; margin-top: 4px;">PayPal Transaction Fee: <strong id="blc-ticket-fee" style="color: #666666;">$0.00</strong></div>
                            <div class="blc-grand-total" style="font-size: 1.15em; margin-top: 6px; padding-top: 6px; border-top: 1px solid #e0d9c8;">Total: <strong id="blc-ticket-grand-total" style="color: <?php echo esc_attr( $accent ); ?>; font-size: 1.1em;">$<?php echo esc_html( number_format( $price, 2 ) ); ?></strong></div>
                        </div>
                    </div>

                    <div id="blc-paypal-button-container" class="blc-paypal-buttons"></div>

                    <?php if ( ! ( new BLC_Gala_PayPal() )->is_configured() ) : ?>
                        <div class="blc-message blc-error" style="display: block;">Payment is not yet configured. Please check back soon!</div>
                    <?php endif; ?>

                    <div id="blc-ticket-message" class="blc-message" style="display: none;"></div>
                </form>

                <div class="blc-cashcheck-wrapper" style="margin-top: 20px; text-align: center;">
                    <button type="button" id="blc-cashcheck-btn" class="blc-cashcheck-trigger">
                        Cash / Check
                        <span class="blc-cashcheck-sub">For Admin Use Only</span>
                    </button>
                </div>
            </div>

            <!-- Cash/Check Modal -->
            <div id="blc-cashcheck-overlay" class="blc-modal-overlay" style="display: none;">
                <div class="blc-modal">
                    <!-- Step 1: PIN -->
                    <div id="blc-cc-step-pin" class="blc-cc-step">
                        <h3>Admin Verification</h3>
                        <p>Enter the admin code to continue.</p>
                        <input type="password" id="blc-cc-pin" placeholder="Admin Code" maxlength="8" inputmode="numeric" autocomplete="off" />
                        <div class="blc-modal-actions">
                            <button type="button" class="blc-modal-btn blc-modal-btn-cancel" id="blc-cc-cancel-pin">Cancel</button>
                            <button type="button" class="blc-modal-btn blc-modal-btn-primary" id="blc-cc-verify-pin">Verify</button>
                        </div>
                        <div id="blc-cc-pin-msg" class="blc-message" style="display: none;"></div>
                    </div>
                    <!-- Step 2: Payment Method -->
                    <div id="blc-cc-step-method" class="blc-cc-step" style="display: none;">
                        <h3>Payment Method</h3>
                        <p style="font-size: 0.9em; color: #666; margin: 0 0 12px;">Select how the buyer is paying:</p>
                        <div class="blc-cc-method-btns" style="flex-direction: column;">
                            <button type="button" class="blc-modal-btn blc-modal-btn-primary blc-cc-method-btn" data-method="cash" style="width: 100%;">Cash</button>
                            <button type="button" class="blc-modal-btn blc-modal-btn-primary blc-cc-method-btn" data-method="check" style="width: 100%;">Check</button>
                            <button type="button" class="blc-modal-btn blc-modal-btn-primary blc-cc-method-btn" data-method="cash+check" style="width: 100%;">Cash + Check</button>
                        </div>
                        <div class="blc-modal-actions" style="margin-top: 15px;">
                            <button type="button" class="blc-modal-btn blc-modal-btn-cancel" id="blc-cc-cancel-method">Cancel</button>
                            <button type="button" class="blc-modal-btn blc-modal-btn-primary" id="blc-cc-next-info" style="display: none;">Next</button>
                        </div>
                    </div>
                    <!-- Step 3: Buyer Info -->
                    <div id="blc-cc-step-info" class="blc-cc-step" style="display: none;">
                        <h3>Buyer Information</h3>
                        <div class="blc-form-group">
                            <label>First Name <span class="blc-required">*</span></label>
                            <input type="text" id="blc-cc-fname" placeholder="First name" />
                        </div>
                        <div class="blc-form-group">
                            <label>Last Name <span class="blc-required">*</span></label>
                            <input type="text" id="blc-cc-lname" placeholder="Last name" />
                        </div>
                        <div class="blc-form-group">
                            <label>Email <span class="blc-required">*</span></label>
                            <input type="email" id="blc-cc-email" placeholder="Email address" />
                        </div>
                        <div class="blc-form-group">
                            <label>Number of Tickets <span class="blc-required">*</span></label>
                            <select id="blc-cc-quantity">
                                <?php for ( $i = 1; $i <= $max_per_order; $i++ ) : ?>
                                    <option value="<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i . ' ' . ( $i === 1 ? 'ticket' : 'tickets' ) . ' — $' . number_format( $price * $i, 2 ) ); ?></option>
                                <?php endfor; ?>
                            </select>
                            <div id="blc-cc-order-total" style="margin-top: 8px; font-weight: 600; color: #1B2A4A; font-size: 1.05em;">Total: $<?php echo esc_html( number_format( $price, 2 ) ); ?></div>
                        </div>
                        <!-- Check fields (visible for Check and Cash+Check) -->
                        <div id="blc-cc-check-field" style="display: none;">
                            <div class="blc-form-group">
                                <label>Check Number <span class="blc-required">*</span></label>
                                <input type="text" id="blc-cc-check-number" placeholder="Enter check number" />
                            </div>
                        </div>
                        <!-- Split amounts (visible only for Cash+Check) -->
                        <div id="blc-cc-split-fields" style="display: none;">
                            <p style="font-size: 0.9em; color: #666; margin: 0 0 10px; font-style: italic;">Enter how much is paid by each method:</p>
                            <div style="display: flex; gap: 10px;">
                                <div class="blc-form-group" style="flex: 1;">
                                    <label>Cash Amount <span class="blc-required">*</span></label>
                                    <input type="number" id="blc-cc-cash-amount" placeholder="0.00" min="0" step="0.01" style="width: 100%; padding: 10px 12px; border: 1px solid #e0d9c8; border-radius: 8px; font-size: 1em; box-sizing: border-box;" />
                                </div>
                                <div class="blc-form-group" style="flex: 1;">
                                    <label>Check Amount <span class="blc-required">*</span></label>
                                    <input type="number" id="blc-cc-check-amount" placeholder="0.00" min="0" step="0.01" style="width: 100%; padding: 10px 12px; border: 1px solid #e0d9c8; border-radius: 8px; font-size: 1em; box-sizing: border-box;" />
                                </div>
                            </div>
                            <div id="blc-cc-split-warning" style="display: none; color: #c0392b; font-size: 0.85em; margin-top: 4px;"></div>
                        </div>
                        <div class="blc-modal-actions">
                            <button type="button" class="blc-modal-btn blc-modal-btn-cancel" id="blc-cc-back-method">Back</button>
                            <button type="button" class="blc-modal-btn blc-modal-btn-primary" id="blc-cc-next-review">Review</button>
                        </div>
                        <div id="blc-cc-info-msg" class="blc-message" style="display: none;"></div>
                    </div>
                    <!-- Step 4: Review -->
                    <div id="blc-cc-step-review" class="blc-cc-step" style="display: none;">
                        <h3>Review Order</h3>
                        <table class="blc-cc-review-table">
                            <tr><td><strong>Name:</strong></td><td id="blc-cc-review-name"></td></tr>
                            <tr><td><strong>Email:</strong></td><td id="blc-cc-review-email"></td></tr>
                            <tr><td><strong>Tickets:</strong></td><td id="blc-cc-review-qty"></td></tr>
                            <tr><td><strong>Amount:</strong></td><td id="blc-cc-review-amount"></td></tr>
                            <tr><td><strong>Payment:</strong></td><td id="blc-cc-review-method"></td></tr>
                        </table>
                        <div class="blc-modal-actions">
                            <button type="button" class="blc-modal-btn blc-modal-btn-cancel" id="blc-cc-back-info">Back</button>
                            <button type="button" class="blc-modal-btn blc-modal-btn-primary" id="blc-cc-confirm">Complete Payment</button>
                        </div>
                        <div id="blc-cc-review-msg" class="blc-message" style="display: none;"></div>
                    </div>
                    <!-- Step 5: Done -->
                    <div id="blc-cc-step-done" class="blc-cc-step" style="display: none;">
                        <div id="blc-cc-done-msg" class="blc-message blc-success" style="display: block;"></div>
                        <div class="blc-modal-actions">
                            <button type="button" class="blc-modal-btn blc-modal-btn-primary" id="blc-cc-done-close">Close</button>
                        </div>
                    </div>
                </div>
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
                    <div class="blc-form-row">
                        <div class="blc-form-group blc-form-half">
                            <label for="blc-donation-fname">First Name <span class="blc-required">*</span></label>
                            <input type="text" id="blc-donation-fname" name="donor_fname" required placeholder="First name" />
                        </div>
                        <div class="blc-form-group blc-form-half">
                            <label for="blc-donation-lname">Last Name <span class="blc-required">*</span></label>
                            <input type="text" id="blc-donation-lname" name="donor_lname" required placeholder="Last name" />
                        </div>
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

                    <?php if ( ! ( new BLC_Gala_PayPal() )->is_configured() ) : ?>
                        <div class="blc-message blc-error" style="display: block;">Payment is not yet configured. Please check back soon!</div>
                    <?php endif; ?>

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
                    Tickets Scanned: <span id="blc-scanner-checked-in">0 / 0</span>
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

                <!-- Checked-In Guest List -->
                <div class="blc-gala-section" style="margin-top: 20px;">
                    <h2 class="blc-gala-section-title">Checked-In Guests</h2>
                    <div id="blc-guest-list-container">
                        <p style="text-align: center; color: #666;">Loading...</p>
                    </div>
                </div>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render [blc_gala_quickpay] — the page a Quick Pay QR code opens.
     * The amount comes from the saved item, never from the URL.
     */
    public function render_quickpay( $atts ) {
        $accent    = get_option( 'blc_gala_accent_color', '#C9A84C' );
        $secondary = get_option( 'blc_gala_secondary_color', '#1B2A4A' );
        $church    = get_option( 'blc_gala_event_tagline', 'Bethel Life Center' );

        $item_id = isset( $_GET['blc_qp'] ) ? sanitize_text_field( wp_unslash( $_GET['blc_qp'] ) ) : '';
        $item    = BLC_Gala_QuickPay::get_item( $item_id );
        $paypal  = new BLC_Gala_PayPal();

        ob_start();
        ?>
        <div class="blc-gala-wrapper blc-quickpay-wrapper" style="--blc-accent: <?php echo esc_attr( $accent ); ?>; --blc-secondary: <?php echo esc_attr( $secondary ); ?>;">

            <?php if ( ! $item ) : ?>

                <?php $available = BLC_Gala_QuickPay::get_items(); ?>
                <div class="blc-gala-hero">
                    <h1 class="blc-gala-title">Make a Payment</h1>
                    <p class="blc-gala-tagline"><?php echo esc_html( $church ); ?></p>
                </div>
                <div class="blc-gala-section">
                    <?php if ( empty( $available ) ) : ?>
                        <p class="blc-gala-description">No payment options are set up yet. Please check back soon.</p>
                    <?php else : ?>
                        <h2 class="blc-gala-section-title">Choose an Amount</h2>
                        <p class="blc-gala-description">Scan a QR code, or pick an option below.</p>
                        <div class="blc-quickpay-options">
                            <?php foreach ( $available as $option ) :
                                $totals = BLC_Gala_QuickPay::calculate_total( $option['amount'] );
                                ?>
                                <a class="blc-quickpay-option" href="<?php echo esc_url( add_query_arg( 'blc_qp', $option['id'] ) ); ?>">
                                    <span class="blc-quickpay-option-label"><?php echo esc_html( $option['label'] ); ?></span>
                                    <span class="blc-quickpay-option-amount">$<?php echo esc_html( number_format( $totals['total'], 2 ) ); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else :
                $totals = BLC_Gala_QuickPay::calculate_total( $item['amount'] );
                ?>

                <div class="blc-gala-hero">
                    <h1 class="blc-gala-title"><?php echo esc_html( $item['label'] ); ?></h1>
                    <p class="blc-gala-tagline"><?php echo esc_html( $church ); ?></p>
                </div>

                <div class="blc-gala-section">
                    <div class="blc-quickpay-amount">$<?php echo esc_html( number_format( $totals['total'], 2 ) ); ?></div>

                    <div class="blc-form-total" style="font-size: 1.05em; color: <?php echo esc_attr( $secondary ); ?>; margin: 0 0 20px; text-align: center;">
                        <div>Amount: <strong style="color: <?php echo esc_attr( $accent ); ?>;">$<?php echo esc_html( number_format( $totals['subtotal'], 2 ) ); ?></strong></div>
                        <?php if ( $totals['fee'] > 0 ) : ?>
                            <div style="font-size: 0.9em; color: #666666; margin-top: 4px;">Processing Fee: <strong style="color: #666666;">$<?php echo esc_html( number_format( $totals['fee'], 2 ) ); ?></strong></div>
                            <div style="font-size: 1.1em; margin-top: 6px; padding-top: 6px; border-top: 1px solid #e0d9c8;">Total: <strong style="color: <?php echo esc_attr( $accent ); ?>;">$<?php echo esc_html( number_format( $totals['total'], 2 ) ); ?></strong></div>
                        <?php endif; ?>
                    </div>

                    <div id="blc-quickpay-buttons" class="blc-paypal-buttons"></div>

                    <?php if ( ! $paypal->is_configured() ) : ?>
                        <div class="blc-message blc-error" style="display: block;">Payment is not yet configured. Please check back soon!</div>
                    <?php endif; ?>

                    <div id="blc-quickpay-message" class="blc-message" style="display: none;"></div>
                </div>

            <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render [blc_gala_quickpay_purchases] — the Quick Pay purchase log.
     * PIN-gated because it lists payer names and card digits.
     */
    public function render_quickpay_purchases( $atts ) {
        $accent    = get_option( 'blc_gala_accent_color', '#C9A84C' );
        $secondary = get_option( 'blc_gala_secondary_color', '#1B2A4A' );

        ob_start();
        ?>
        <div class="blc-gala-wrapper blc-quickpay-report" style="--blc-accent: <?php echo esc_attr( $accent ); ?>; --blc-secondary: <?php echo esc_attr( $secondary ); ?>;">

            <!-- PIN gate -->
            <div id="blc-qp-pin-section">
                <div class="blc-gala-hero">
                    <h1 class="blc-gala-title">QR Code Payments</h1>
                    <p class="blc-gala-tagline"><?php echo esc_html( get_option( 'blc_gala_event_tagline', 'Bethel Life Center' ) ); ?></p>
                </div>
                <div class="blc-gala-section" style="text-align: center;">
                    <h2 class="blc-gala-section-title">Enter Admin Code</h2>
                    <form id="blc-qp-pin-form" class="blc-gala-form" style="max-width: 300px; margin: 20px auto;">
                        <div class="blc-form-group">
                            <input type="password" id="blc-qp-pin-input" class="blc-scanner-pin-input" placeholder="Admin Code" maxlength="8" inputmode="numeric" autocomplete="off" required />
                        </div>
                        <button type="submit" class="blc-scanner-pin-btn">View Payments</button>
                    </form>
                    <div id="blc-qp-pin-message" class="blc-message" style="display: none;"></div>
                </div>
            </div>

            <!-- Purchase log -->
            <div id="blc-qp-report-section" style="display: none;">
                <div class="blc-gala-hero">
                    <h1 class="blc-gala-title">QR Code Payments</h1>
                    <p class="blc-gala-tagline"><?php echo esc_html( get_option( 'blc_gala_event_tagline', 'Bethel Life Center' ) ); ?></p>
                </div>

                <div class="blc-quickpay-totals" id="blc-qp-totals">
                    <div class="blc-quickpay-total-box">
                        <span class="blc-quickpay-total-number" id="blc-qp-count">0</span>
                        <span class="blc-quickpay-total-label">Payments</span>
                    </div>
                    <div class="blc-quickpay-total-box">
                        <span class="blc-quickpay-total-number" id="blc-qp-sum">$0.00</span>
                        <span class="blc-quickpay-total-label">Total Collected</span>
                    </div>
                </div>

                <div class="blc-gala-section" style="margin-top: 20px;">
                    <h2 class="blc-gala-section-title">All Payments</h2>
                    <div id="blc-qp-list"><p style="text-align:center;color:#666;">Loading&hellip;</p></div>
                    <p style="margin-top: 15px; text-align: center;">
                        <button type="button" id="blc-qp-refresh" class="blc-scanner-btn">Refresh</button>
                        <button type="button" id="blc-qp-print" class="blc-scanner-btn blc-scanner-btn-primary" onclick="window.print();">Print</button>
                    </p>
                </div>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }
}
