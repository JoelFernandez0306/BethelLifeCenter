<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap blc-gala-settings">
    <h1>Always on Mission Gala — Settings</h1>

    <?php
    $tickets_mgr = new BLC_Gala_Tickets();
    $remaining   = $tickets_mgr->get_remaining_count();
    $total       = (int) get_option( 'blc_gala_total_tickets', 100 );
    $sold        = $total - $remaining;
    ?>
    <div class="blc-gala-dashboard">
        <div class="blc-gala-stat">
            <span class="blc-gala-stat-number"><?php echo esc_html( $sold ); ?></span>
            <span class="blc-gala-stat-label">Tickets Sold</span>
        </div>
        <div class="blc-gala-stat">
            <span class="blc-gala-stat-number"><?php echo esc_html( $remaining ); ?></span>
            <span class="blc-gala-stat-label">Tickets Remaining</span>
        </div>
        <div class="blc-gala-stat">
            <span class="blc-gala-stat-number"><?php echo esc_html( $total ); ?></span>
            <span class="blc-gala-stat-label">Total Tickets</span>
        </div>
    </div>

    <form method="post" action="options.php">
        <?php settings_fields( 'blc_gala_settings' ); ?>

        <h2>Event Details</h2>
        <table class="form-table">
            <tr>
                <th><label for="blc_gala_event_name">Event Name</label></th>
                <td><input type="text" id="blc_gala_event_name" name="blc_gala_event_name" value="<?php echo esc_attr( get_option( 'blc_gala_event_name' ) ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="blc_gala_event_tagline">Event Tagline</label></th>
                <td><input type="text" id="blc_gala_event_tagline" name="blc_gala_event_tagline" value="<?php echo esc_attr( get_option( 'blc_gala_event_tagline' ) ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="blc_gala_event_address">Event Address</label></th>
                <td><textarea id="blc_gala_event_address" name="blc_gala_event_address" rows="2" class="regular-text"><?php echo esc_textarea( get_option( 'blc_gala_event_address', '' ) ); ?></textarea>
                <p class="description">Displayed below the tagline in the hero section.</p></td>
            </tr>
            <tr>
                <th><label for="blc_gala_event_date">Event Date &amp; Time</label></th>
                <td><input type="datetime-local" id="blc_gala_event_date" name="blc_gala_event_date" value="<?php echo esc_attr( str_replace( ' ', 'T', get_option( 'blc_gala_event_date' ) ) ); ?>" /></td>
            </tr>
            <tr>
                <th><label for="blc_gala_total_tickets">Total Tickets Available</label></th>
                <td><input type="number" id="blc_gala_total_tickets" name="blc_gala_total_tickets" value="<?php echo esc_attr( get_option( 'blc_gala_total_tickets' ) ); ?>" min="1" class="small-text" /></td>
            </tr>
            <tr>
                <th><label for="blc_gala_manual_sold">Tickets Sold (Manual Adjustment)</label></th>
                <td><input type="number" id="blc_gala_manual_sold" name="blc_gala_manual_sold" value="<?php echo esc_attr( get_option( 'blc_gala_manual_sold', 0 ) ); ?>" min="0" class="small-text" />
                <p class="description">Add tickets sold outside this system (e.g., sold before plugin was set up). This is added on top of the <?php echo esc_html( $sold ); ?> ticket(s) already tracked by orders in the system.</p></td>
            </tr>
            <tr>
                <th><label for="blc_gala_ticket_price">Ticket Price ($)</label></th>
                <td><input type="number" id="blc_gala_ticket_price" name="blc_gala_ticket_price" value="<?php echo esc_attr( get_option( 'blc_gala_ticket_price' ) ); ?>" min="0" step="0.01" class="small-text" /></td>
            </tr>
            <tr>
                <th><label for="blc_gala_max_per_order">Max Tickets Per Order</label></th>
                <td><input type="number" id="blc_gala_max_per_order" name="blc_gala_max_per_order" value="<?php echo esc_attr( get_option( 'blc_gala_max_per_order', 1 ) ); ?>" min="1" class="small-text" />
                <p class="description">Maximum number of tickets a buyer can purchase in a single order.</p></td>
            </tr>
        </table>

        <h2>PayPal Settings</h2>
        <table class="form-table">
            <tr>
                <th><label for="blc_gala_paypal_client_id">PayPal Client ID</label></th>
                <td><input type="text" id="blc_gala_paypal_client_id" name="blc_gala_paypal_client_id" value="<?php echo esc_attr( get_option( 'blc_gala_paypal_client_id' ) ); ?>" class="large-text" />
                <p class="description">Get this from <a href="https://developer.paypal.com/dashboard/applications/live" target="_blank">PayPal Developer Dashboard</a> (Live) or <a href="https://developer.paypal.com/dashboard/applications/sandbox" target="_blank">Sandbox</a>.</p></td>
            </tr>
            <tr>
                <th><label for="blc_gala_paypal_secret">PayPal Secret</label></th>
                <td><input type="password" id="blc_gala_paypal_secret" name="blc_gala_paypal_secret" value="<?php echo esc_attr( get_option( 'blc_gala_paypal_secret' ) ); ?>" class="large-text" /></td>
            </tr>
            <tr>
                <th><label for="blc_gala_paypal_sandbox">Sandbox Mode</label></th>
                <td>
                    <label><input type="checkbox" id="blc_gala_paypal_sandbox" name="blc_gala_paypal_sandbox" value="1" <?php checked( get_option( 'blc_gala_paypal_sandbox' ), 1 ); ?> /> Enable sandbox/test mode</label>
                    <p class="description"><strong>Important:</strong> Sandbox mode requires sandbox credentials. Live mode requires live credentials. Make sure they match!</p>
                </td>
            </tr>
            <tr>
                <th>Test Connection</th>
                <td>
                    <button type="button" class="button" id="blc-test-paypal">Test PayPal Connection</button>
                    <span id="blc-test-paypal-result" style="margin-left: 10px;"></span>
                    <p class="description">Save settings first, then click to verify your PayPal credentials are working.</p>
                </td>
            </tr>
            <tr>
                <th><label for="blc_gala_paypal_fee_rate">PayPal Fee Rate (%)</label></th>
                <td><input type="number" id="blc_gala_paypal_fee_rate" name="blc_gala_paypal_fee_rate" value="<?php echo esc_attr( get_option( 'blc_gala_paypal_fee_rate', 2.99 ) ); ?>" min="0" step="0.01" class="small-text" />
                <p class="description">PayPal's percentage fee per transaction. Standard: 2.99%, Nonprofit: 1.99%. This fee is passed to the buyer.</p></td>
            </tr>
            <tr>
                <th><label for="blc_gala_paypal_fee_fixed">PayPal Fixed Fee ($)</label></th>
                <td><input type="number" id="blc_gala_paypal_fee_fixed" name="blc_gala_paypal_fee_fixed" value="<?php echo esc_attr( get_option( 'blc_gala_paypal_fee_fixed', 0.49 ) ); ?>" min="0" step="0.01" class="small-text" />
                <p class="description">PayPal's fixed fee per transaction (usually $0.49).</p></td>
            </tr>
        </table>

        <h2>Donation Settings</h2>
        <table class="form-table">
            <tr>
                <th><label for="blc_gala_donation_enabled">Enable Donations</label></th>
                <td><label><input type="checkbox" id="blc_gala_donation_enabled" name="blc_gala_donation_enabled" value="1" <?php checked( get_option( 'blc_gala_donation_enabled' ), 1 ); ?> /> Allow donations (shown when sold out, and optionally alongside tickets)</label></td>
            </tr>
            <tr>
                <th><label for="blc_gala_donation_message">Sold-Out Message</label></th>
                <td><textarea id="blc_gala_donation_message" name="blc_gala_donation_message" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'blc_gala_donation_message' ) ); ?></textarea></td>
            </tr>
        </table>

        <h2>Appearance</h2>
        <table class="form-table">
            <tr>
                <th><label for="blc_gala_accent_color">Accent Color</label></th>
                <td><input type="color" id="blc_gala_accent_color" name="blc_gala_accent_color" value="<?php echo esc_attr( get_option( 'blc_gala_accent_color', '#C9A84C' ) ); ?>" /></td>
            </tr>
            <tr>
                <th><label for="blc_gala_secondary_color">Secondary Color</label></th>
                <td><input type="color" id="blc_gala_secondary_color" name="blc_gala_secondary_color" value="<?php echo esc_attr( get_option( 'blc_gala_secondary_color', '#1B2A4A' ) ); ?>" /></td>
            </tr>
        </table>

        <h2>Door Scanner</h2>
        <table class="form-table">
            <tr>
                <th><label for="blc_gala_scanner_pin">Scanner PIN</label></th>
                <td>
                    <input type="text" id="blc_gala_scanner_pin" name="blc_gala_scanner_pin" value="<?php echo esc_attr( get_option( 'blc_gala_scanner_pin', '1234' ) ); ?>" class="small-text" maxlength="8" />
                    <p class="description">Volunteers enter this PIN to access the door scanner. Share this with your door staff.</p>
                </td>
            </tr>
            <tr>
                <th><label for="blc_gala_admin_pin">Cash/Check Admin PIN</label></th>
                <td>
                    <input type="text" id="blc_gala_admin_pin" name="blc_gala_admin_pin" value="<?php echo esc_attr( get_option( 'blc_gala_admin_pin', '0000' ) ); ?>" class="small-text" maxlength="8" />
                    <p class="description">Admin PIN for processing cash/check payments on the ticket page. Only share with authorized staff.</p>
                </td>
            </tr>
            <tr>
                <th>Volunteer Scanner Shortcode</th>
                <td>
                    <code>[blc_gala_scanner]</code>
                    <p class="description">Create a page with this shortcode for door volunteers. They only need the PIN &mdash; no WordPress login required.</p>
                </td>
            </tr>
            <tr>
                <th>Admin Scanner</th>
                <td>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=blc-gala-scanner' ) ); ?>" class="button">Open Admin Scanner</a>
                    <p class="description">This scanner requires WordPress admin login. Use the volunteer shortcode above for door staff.</p>
                </td>
            </tr>
        </table>

        <h2>Shortcodes</h2>
        <table class="form-table">
            <tr>
                <th>Ticket Sales Page</th>
                <td>
                    <code>[blc_gala_tickets]</code>
                    <p class="description">Paste this shortcode into any Divi Code Module or Text Module to display the ticket sales page.</p>
                </td>
            </tr>
            <tr>
                <th>Volunteer Scanner Page</th>
                <td>
                    <code>[blc_gala_scanner]</code>
                    <p class="description">Paste this into a separate page for door volunteers. PIN-protected, no login needed.</p>
                </td>
            </tr>
        </table>

        <h2>Promotional QR Code</h2>
        <table class="form-table">
            <tr>
                <th>QR Code</th>
                <td>
                    <div id="blc-promo-qr">
                        <?php
                        $page_url = get_option( 'blc_gala_page_url', '' );
                        if ( $page_url ) {
                            $qr = new BLC_Gala_QR_Generator();
                            echo $qr->generate_svg( $page_url, 200 );
                            echo '<p class="description">This QR code links to: <a href="' . esc_url( $page_url ) . '" target="_blank">' . esc_html( $page_url ) . '</a></p>';
                            echo '<p><button type="button" class="button" id="blc-download-qr">Download QR Code (PNG)</button></p>';
                        } else {
                            echo '<p class="description">Publish a page with the <code>[blc_gala_tickets]</code> shortcode first. The QR code will appear here automatically linking to that page.</p>';
                        }
                        ?>
                    </div>
                </td>
            </tr>
        </table>

        <?php submit_button( 'Save Settings' ); ?>
    </form>
</div>
