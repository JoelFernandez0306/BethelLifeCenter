<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap blc-gala-settings">
    <h1>Always on Mission Gala — Settings</h1>

    <?php
    $tickets_mgr = new BLC_Gala_Tickets();
    $breakdown   = $tickets_mgr->get_ticket_breakdown();

    $remaining = $breakdown['remaining'];
    $total     = $breakdown['total'];
    $sold      = $breakdown['taken'];
    ?>
    <div class="blc-gala-dashboard">
        <div class="blc-gala-stat">
            <span class="blc-gala-stat-number"><?php echo esc_html( $breakdown['online'] ); ?></span>
            <span class="blc-gala-stat-label">Sold On This Site</span>
        </div>
        <div class="blc-gala-stat">
            <span class="blc-gala-stat-number"><?php echo esc_html( $breakdown['recorded'] ); ?></span>
            <span class="blc-gala-stat-label">Previously Recorded</span>
        </div>
        <div class="blc-gala-stat">
            <span class="blc-gala-stat-number"><?php echo esc_html( $breakdown['volunteers'] ); ?></span>
            <span class="blc-gala-stat-label">Volunteers (Free)</span>
        </div>
        <div class="blc-gala-stat">
            <span class="blc-gala-stat-number"><?php echo esc_html( $remaining ); ?></span>
            <span class="blc-gala-stat-label">Tickets Remaining</span>
        </div>
    </div>

    <p class="description" style="margin: 10px 0 0; font-size: 13px;">
        <?php
        printf(
            '%d sold on this site + %d previously recorded + %d volunteer%s = <strong>%d of %d seats taken</strong>, %d remaining.',
            (int) $breakdown['online'],
            (int) $breakdown['recorded'],
            (int) $breakdown['volunteers'],
            1 === (int) $breakdown['volunteers'] ? '' : 's',
            (int) $sold,
            (int) $total,
            (int) $remaining
        );
        ?>
    </p>

    <?php if ( $sold > $total ) : ?>
        <div class="notice notice-warning inline" style="margin: 15px 0;">
            <p>
                <strong>Heads up:</strong> those entries add up to <?php echo esc_html( $sold ); ?> seats,
                which is more than the <?php echo esc_html( $total ); ?> total tickets available.
                The page will show as sold out. Raise "Total Tickets Available" or remove some entries.
            </p>
        </div>
    <?php endif; ?>

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
                <th><label for="blc_gala_manual_sold">Previously Recorded Sales</label></th>
                <td><input type="number" id="blc_gala_manual_sold" name="blc_gala_manual_sold" value="<?php echo esc_attr( get_option( 'blc_gala_manual_sold', 0 ) ); ?>" min="0" class="small-text" />
                <p class="description">
                    Tickets sold outside this site &mdash; on the old website, in person, or before this
                    plugin was installed. Each one subtracts from the tickets remaining.<br />
                    This site has recorded <strong><?php echo esc_html( $breakdown['online'] ); ?></strong>
                    order<?php echo 1 === (int) $breakdown['online'] ? '' : 's'; ?> of its own, which
                    <em>are not</em> included in this number &mdash; do not count them twice.
                </p></td>
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

    <hr style="margin: 40px 0 25px;" />

    <?php
    // ---------------------------------------------------------------------
    // Plugin updates (served from GitHub releases)
    // ---------------------------------------------------------------------
    $upd_checked = isset( $_GET['blc_upd_notice'] ) && 'checked' === $_GET['blc_upd_notice'];
    $updater     = new BLC_Gala_Updater( BLC_GALA_PLUGIN_DIR . 'bethel-gala-tickets.php' );
    $release     = $updater->get_latest_release();

    $latest        = $release['version'];
    $update_ready  = $latest && version_compare( $latest, BLC_GALA_VERSION, '>' );
    $check_url     = wp_nonce_url( admin_url( 'admin.php?page=blc-gala-settings&blc_upd_check=1#blc-updates' ), 'blc_upd_check' );
    ?>

    <h2 id="blc-updates">Plugin Updates</h2>
    <p class="description" style="max-width: 700px;">
        This plugin updates itself from GitHub. When a new version is released it shows up
        under <strong>Dashboard &rarr; Updates</strong> and on the Plugins screen, just like
        any other plugin &mdash; no files to upload.
    </p>

    <?php if ( $upd_checked ) : ?>
        <div class="notice notice-success is-dismissible"><p>Checked for updates.</p></div>
    <?php endif; ?>

    <table class="form-table">
        <tr>
            <th>Installed Version</th>
            <td><code><?php echo esc_html( BLC_GALA_VERSION ); ?></code></td>
        </tr>
        <tr>
            <th>Latest Released</th>
            <td>
                <?php if ( $latest ) : ?>
                    <code><?php echo esc_html( $latest ); ?></code>
                    <?php if ( $update_ready ) : ?>
                        <span style="color:#b32d2e;font-weight:600;margin-left:8px;">Update available</span>
                        <p class="description">
                            <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">Go to the Plugins screen</a>
                            to install it.
                        </p>
                    <?php else : ?>
                        <span style="color:#0a7c00;font-weight:600;margin-left:8px;">Up to date</span>
                    <?php endif; ?>
                <?php else : ?>
                    <em>No release published yet.</em>
                    <p class="description">
                        Once the first release is tagged on GitHub it will appear here.
                    </p>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th>Check Now</th>
            <td>
                <a href="<?php echo esc_url( $check_url ); ?>" class="button">Check for Updates</a>
                <p class="description">
                    Updates are checked automatically a few times a day. This forces an immediate check.
                    &nbsp;&middot;&nbsp;
                    <a href="<?php echo esc_url( BLC_Gala_Updater::releases_url() ); ?>" target="_blank">View all releases</a>
                </p>
            </td>
        </tr>
        <tr>
            <th>Automatic Updates</th>
            <td>
                <p class="description" style="margin-top: 4px;">
                    To have new versions install themselves with no clicking at all, go to the
                    <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">Plugins screen</a>
                    and click <strong>Enable auto-updates</strong> next to this plugin.
                </p>
            </td>
        </tr>
    </table>

    <hr style="margin: 40px 0 25px;" />

    <?php
    // ---------------------------------------------------------------------
    // Volunteers (free admission)
    // ---------------------------------------------------------------------
    $volunteers  = BLC_Gala_Volunteers::get_volunteers();
    $vol_notice  = isset( $_GET['blc_vol_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['blc_vol_notice'] ) ) : '';
    ?>

    <h2 id="blc-volunteers">Volunteers (Free Admission)</h2>
    <p class="description" style="max-width: 700px;">
        Volunteers attend at no charge but still take a seat, so each name added here
        subtracts one from the tickets remaining. Removing a name puts the seat back.
    </p>

    <?php if ( $vol_notice === 'added' ) : ?>
        <div class="notice notice-success is-dismissible"><p>Volunteer added. One seat was subtracted from the tickets remaining.</p></div>
    <?php elseif ( $vol_notice === 'deleted' ) : ?>
        <div class="notice notice-success is-dismissible"><p>Volunteer removed. Their seat is available again.</p></div>
    <?php elseif ( $vol_notice === 'invalid' ) : ?>
        <div class="notice notice-error is-dismissible"><p>Please enter both a first and last name.</p></div>
    <?php endif; ?>

    <form method="post" action="" style="margin: 20px 0; padding: 15px 20px; background: #fff; border: 1px solid #c3c4c7; max-width: 700px;">
        <?php wp_nonce_field( 'blc_vol_add' ); ?>
        <h3 style="margin-top: 0;">Add a Volunteer</h3>
        <table class="form-table">
            <tr>
                <th><label for="blc_vol_first">First Name</label></th>
                <td><input type="text" id="blc_vol_first" name="blc_vol_first" class="regular-text" required /></td>
            </tr>
            <tr>
                <th><label for="blc_vol_last">Last Name</label></th>
                <td><input type="text" id="blc_vol_last" name="blc_vol_last" class="regular-text" required /></td>
            </tr>
        </table>
        <p><button type="submit" name="blc_vol_add" value="1" class="button button-primary">Add Volunteer</button></p>
    </form>

    <?php if ( empty( $volunteers ) ) : ?>
        <p><em>No volunteers added yet.</em></p>
    <?php else : ?>
        <p><strong><?php echo count( $volunteers ); ?></strong> volunteer<?php echo 1 === count( $volunteers ) ? '' : 's'; ?> &mdash;
           taking up <?php echo count( $volunteers ); ?> of the <?php echo esc_html( $total ); ?> seats.</p>
        <table class="wp-list-table widefat fixed striped" style="max-width: 700px;">
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th style="width: 100px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $volunteers as $index => $volunteer ) :
                    $vol_delete = wp_nonce_url(
                        admin_url( 'admin.php?page=blc-gala-settings&blc_vol_delete=' . rawurlencode( $volunteer['id'] ) ),
                        'blc_vol_delete'
                    );
                    ?>
                    <tr>
                        <td><?php echo esc_html( $index + 1 ); ?></td>
                        <td><?php echo esc_html( $volunteer['first'] ); ?></td>
                        <td><?php echo esc_html( $volunteer['last'] ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( $vol_delete ); ?>" class="button blc-qp-delete"
                               onclick="return confirm('Remove <?php echo esc_js( BLC_Gala_Volunteers::full_name( $volunteer ) ); ?> from the volunteer list? This frees up one seat.');">Remove</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <hr style="margin: 40px 0 25px;" />

    <?php
    // ---------------------------------------------------------------------
    // Quick Pay QR Codes
    // ---------------------------------------------------------------------
    $qp_items    = BLC_Gala_QuickPay::get_items();
    $qp_page_url = get_option( 'blc_gala_quickpay_page_url', '' );
    $qp_notice   = isset( $_GET['blc_qp_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['blc_qp_notice'] ) ) : '';
    ?>

    <h2 id="blc-quickpay">Quick Pay QR Codes</h2>
    <p class="description" style="max-width: 700px;">
        Create a QR code for any dollar amount. Print it, set it out, and people scan it to pay that
        exact amount &mdash; useful for fundraiser meals, snacks, books, or anything sold in person.
        The PayPal fee settings above are added automatically so the church receives the full amount.
    </p>

    <?php if ( $qp_notice === 'added' ) : ?>
        <div class="notice notice-success is-dismissible"><p>QR code created.</p></div>
    <?php elseif ( $qp_notice === 'deleted' ) : ?>
        <div class="notice notice-success is-dismissible"><p>QR code deleted. Past payments were kept.</p></div>
    <?php elseif ( $qp_notice === 'invalid' ) : ?>
        <div class="notice notice-error is-dismissible"><p>Please enter an amount of at least $0.01.</p></div>
    <?php endif; ?>

    <?php if ( ! $qp_page_url ) : ?>
        <div class="notice notice-warning inline" style="margin: 15px 0;">
            <p>
                <strong>One setup step first:</strong> create a page containing the shortcode
                <code>[blc_gala_quickpay]</code>, publish it, then visit it once. The QR codes will
                appear here automatically pointing at that page.
            </p>
        </div>
    <?php endif; ?>

    <form method="post" action="" style="margin: 20px 0; padding: 15px 20px; background: #fff; border: 1px solid #c3c4c7; max-width: 700px;">
        <?php wp_nonce_field( 'blc_qp_add' ); ?>
        <h3 style="margin-top: 0;">Create a New QR Code</h3>
        <table class="form-table">
            <tr>
                <th><label for="blc_qp_label">What is it for?</label></th>
                <td>
                    <input type="text" id="blc_qp_label" name="blc_qp_label" class="regular-text" placeholder="e.g. Fundraiser Dinner" />
                    <p class="description">Shown on the payment page and in the payment log.</p>
                </td>
            </tr>
            <tr>
                <th><label for="blc_qp_amount">Amount ($)</label></th>
                <td>
                    <input type="number" id="blc_qp_amount" name="blc_qp_amount" class="small-text" min="0.01" step="0.01" placeholder="12.00" required />
                    <p class="description">What the church should receive. The PayPal fee is added on top.</p>
                </td>
            </tr>
        </table>
        <p><button type="submit" name="blc_qp_add" value="1" class="button button-primary">Create QR Code</button></p>
    </form>

    <?php if ( empty( $qp_items ) ) : ?>
        <p><em>No QR codes yet. Create one above.</em></p>
    <?php else : ?>
        <div class="blc-qp-grid">
            <?php
            $qr_gen = new BLC_Gala_QR_Generator();
            foreach ( $qp_items as $qp_item ) :
                $qp_totals  = BLC_Gala_QuickPay::calculate_total( $qp_item['amount'] );
                $qp_pay_url = BLC_Gala_QuickPay::get_payment_url( $qp_item['id'] );
                $qp_delete  = wp_nonce_url(
                    admin_url( 'admin.php?page=blc-gala-settings&blc_qp_delete=' . rawurlencode( $qp_item['id'] ) ),
                    'blc_qp_delete'
                );
                ?>
                <div class="blc-qp-card" data-label="<?php echo esc_attr( $qp_item['label'] ); ?>" data-amount="$<?php echo esc_attr( number_format( $qp_totals['total'], 2 ) ); ?>">
                    <h4 class="blc-qp-card-label"><?php echo esc_html( $qp_item['label'] ); ?></h4>
                    <div class="blc-qp-card-amount">$<?php echo esc_html( number_format( $qp_totals['total'], 2 ) ); ?></div>

                    <div class="blc-qp-card-qr">
                        <?php
                        if ( $qp_pay_url ) {
                            echo $qr_gen->generate_svg( $qp_pay_url, 200 );
                        } else {
                            echo '<p class="description">Publish the Quick Pay page to generate this QR code.</p>';
                        }
                        ?>
                    </div>

                    <p class="description blc-qp-card-breakdown">
                        Church receives $<?php echo esc_html( number_format( $qp_totals['subtotal'], 2 ) ); ?><?php
                        if ( $qp_totals['fee'] > 0 ) {
                            echo ' &middot; fee $' . esc_html( number_format( $qp_totals['fee'], 2 ) );
                        }
                        ?>
                    </p>

                    <?php if ( $qp_pay_url ) : ?>
                        <p class="blc-qp-card-actions">
                            <button type="button" class="button blc-qp-print">Print</button>
                            <button type="button" class="button blc-qp-download">Download</button>
                            <a href="<?php echo esc_url( $qp_delete ); ?>" class="button blc-qp-delete" onclick="return confirm('Delete this QR code? Any printed copies will stop working. Past payments are kept.');">Delete</a>
                        </p>
                        <p class="description blc-qp-card-url"><a href="<?php echo esc_url( $qp_pay_url ); ?>" target="_blank">Open payment page</a></p>
                    <?php else : ?>
                        <p class="blc-qp-card-actions">
                            <a href="<?php echo esc_url( $qp_delete ); ?>" class="button blc-qp-delete" onclick="return confirm('Delete this QR code?');">Delete</a>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h3 style="margin-top: 30px;">Payment Log Page</h3>
    <table class="form-table">
        <tr>
            <th>Purchases Shortcode</th>
            <td>
                <code>[blc_gala_quickpay_purchases]</code>
                <p class="description">
                    Put this on a page to see every QR code payment &mdash; payer name, card last 4, and amount.
                    It asks for the <strong>Cash/Check Admin PIN</strong> above before showing anything,
                    since it displays payment details.
                </p>
            </td>
        </tr>
        <tr>
            <th>Payment Page Shortcode</th>
            <td>
                <code>[blc_gala_quickpay]</code>
                <p class="description">The page the QR codes open. Create it once and publish it; every QR code uses it.</p>
            </td>
        </tr>
    </table>
</div>
