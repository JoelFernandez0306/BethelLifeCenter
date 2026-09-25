<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap blc-gala-orders">
    <h1>Gala Orders</h1>

    <?php
    $tickets_mgr = new BLC_Gala_Tickets();
    $filter_type = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : '';
    $filter_status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : 'completed';

    $orders = $tickets_mgr->get_orders( array(
        'order_type' => $filter_type,
        'status'     => $filter_status,
        'limit'      => 100,
    ) );
    ?>

    <?php
    $order_notice = isset( $_GET['blc_order_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['blc_order_notice'] ) ) : '';
    $manual_sold  = (int) get_option( 'blc_gala_manual_sold', 0 );
    $ticket_price = (float) get_option( 'blc_gala_ticket_price', 50.00 );
    ?>

    <?php if ( 'added' === $order_notice ) : ?>
        <div class="notice notice-success is-dismissible"><p>Sale recorded. Ticket codes were generated so this guest can be scanned at the door.</p></div>
    <?php elseif ( 'emailed' === $order_notice ) : ?>
        <div class="notice notice-success is-dismissible"><p>Sale recorded and the tickets were emailed to the buyer.</p></div>
    <?php elseif ( 'error' === $order_notice ) : ?>
        <?php $order_error = get_transient( 'blc_order_error' ); delete_transient( 'blc_order_error' ); ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $order_error ? $order_error : 'Could not record that sale.' ); ?></p></div>
    <?php endif; ?>

    <details class="blc-add-order" <?php echo $order_notice ? 'open' : ''; ?>>
        <summary>Record a Past Sale</summary>

        <p class="description" style="max-width: 720px;">
            For tickets sold on the old website, in person, or before this plugin was set up.
            The sale is saved as a normal order with its own ticket codes, so the guest can be
            scanned in at the door and the tickets remaining count goes down by the quantity.
        </p>

        <?php if ( $manual_sold > 0 ) : ?>
            <div class="notice notice-warning inline" style="margin: 12px 0; max-width: 720px;">
                <p>
                    <strong>Careful about counting twice.</strong> Settings still has
                    <strong>Previously Recorded Sales</strong> set to <strong><?php echo esc_html( $manual_sold ); ?></strong>.
                    Every sale you enter here is counted on its own, so lower that number by the same
                    amount as you go &mdash; or set it to 0 once everything has been entered here.
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=blc-gala-settings' ) ); ?>">Open Settings</a>
                </p>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <?php wp_nonce_field( 'blc_order_add' ); ?>
            <table class="form-table">
                <tr>
                    <th><label for="blc_order_first">Name <span style="color:#b32d2e;">*</span></label></th>
                    <td>
                        <input type="text" id="blc_order_first" name="blc_order_first" placeholder="First name" required />
                        <input type="text" id="blc_order_last" name="blc_order_last" placeholder="Last name" required />
                    </td>
                </tr>
                <tr>
                    <th><label for="blc_order_email">Email <span style="color:#b32d2e;">*</span></label></th>
                    <td>
                        <input type="email" id="blc_order_email" name="blc_order_email" class="regular-text" required />
                        <p class="description">Where the tickets go if you choose to email them below.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="blc_order_qty">Tickets <span style="color:#b32d2e;">*</span></label></th>
                    <td><input type="number" id="blc_order_qty" name="blc_order_qty" class="small-text" min="1" value="1" required /></td>
                </tr>
                <tr>
                    <th><label for="blc_order_amount">Amount Paid ($)</label></th>
                    <td>
                        <input type="number" id="blc_order_amount" name="blc_order_amount" class="small-text" min="0" step="0.01" placeholder="auto" />
                        <p class="description">Leave blank to use the current price of $<?php echo esc_html( number_format( $ticket_price, 2 ) ); ?> per ticket. Set it by hand if they paid a different price.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="blc_order_date">Date of Sale</label></th>
                    <td>
                        <input type="datetime-local" id="blc_order_date" name="blc_order_date" value="<?php echo esc_attr( date_i18n( 'Y-m-d\TH:i' ) ); ?>" />
                        <p class="description">Defaults to now. Change it to when the sale actually happened.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="blc_order_method">Paid With</label></th>
                    <td>
                        <select id="blc_order_method" name="blc_order_method">
                            <option value="Cash">Cash</option>
                            <option value="Check">Check</option>
                            <option value="PayPal">PayPal</option>
                            <option value="Card">Card</option>
                            <option value="Other">Other</option>
                        </select>
                        <input type="text" name="blc_order_ref" placeholder="Check or reference # (optional)" style="margin-left: 8px; width: 240px;" />
                    </td>
                </tr>
                <tr>
                    <th>Email the Tickets</th>
                    <td>
                        <label>
                            <input type="checkbox" name="blc_order_send" value="1" />
                            Send this buyer their QR code tickets now
                        </label>
                        <p class="description">
                            Useful when the old site's tickets no longer work. Leave unticked to record the
                            sale quietly &mdash; you can always use <strong>Resend Email</strong> on the row later.
                        </p>
                    </td>
                </tr>
            </table>
            <p><button type="submit" name="blc_order_add" value="1" class="button button-primary">Record Sale</button></p>
        </form>
    </details>

    <div style="margin: 15px 0;">
        <button type="button" id="blc-print-orders" class="button button-secondary" onclick="window.print();">Print Orders</button>
    </div>

    <div class="blc-gala-filters" style="margin: 15px 0;">
        <strong>Filter:</strong>
        <a href="?page=blc-gala-orders&status=completed" class="button <?php echo $filter_status === 'completed' ? 'button-primary' : ''; ?>">Completed</a>
        <a href="?page=blc-gala-orders&status=pending" class="button <?php echo $filter_status === 'pending' ? 'button-primary' : ''; ?>">Pending</a>
        <a href="?page=blc-gala-orders&status=" class="button <?php echo $filter_status === '' ? 'button-primary' : ''; ?>">All</a>
        &nbsp;|&nbsp;
        <a href="?page=blc-gala-orders&status=<?php echo esc_attr( $filter_status ); ?>&type=ticket" class="button <?php echo $filter_type === 'ticket' ? 'button-primary' : ''; ?>">Tickets</a>
        <a href="?page=blc-gala-orders&status=<?php echo esc_attr( $filter_status ); ?>&type=donation" class="button <?php echo $filter_type === 'donation' ? 'button-primary' : ''; ?>">Donations</a>
        <a href="?page=blc-gala-orders&status=<?php echo esc_attr( $filter_status ); ?>&type=" class="button <?php echo $filter_type === '' ? 'button-primary' : ''; ?>">All Types</a>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Date</th>
                <th>Name</th>
                <th>Email</th>
                <th>Type</th>
                <th>Qty</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Payment</th>
                <th>Ticket Codes</th>
                <th>Checked In</th>
                <th class="blc-col-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $orders ) ) : ?>
                <tr><td colspan="11">No orders found.</td></tr>
            <?php else : ?>
                <?php foreach ( $orders as $order ) : ?>
                    <tr>
                        <td><?php echo esc_html( date_i18n( 'M j, Y g:i A', strtotime( $order->created_at ) ) ); ?></td>
                        <td><?php echo esc_html( $order->buyer_name ); ?></td>
                        <td><?php echo esc_html( $order->buyer_email ); ?></td>
                        <td><span class="blc-badge blc-badge-<?php echo esc_attr( $order->order_type ); ?>"><?php echo esc_html( ucfirst( $order->order_type ) ); ?></span></td>
                        <td><?php echo esc_html( $order->quantity ); ?></td>
                        <td>$<?php echo esc_html( number_format( $order->amount_paid, 2 ) ); ?></td>
                        <td><span class="blc-status blc-status-<?php echo esc_attr( $order->status ); ?>"><?php echo esc_html( ucfirst( $order->status ) ); ?></span></td>
                        <td><?php
                            $ref = $order->paypal_order_id;
                            if ( empty( $ref ) ) {
                                echo '—';
                            } elseif ( strpos( $ref, 'MANUAL:' ) === 0 ) {
                                // A sale entered by hand rather than taken on this site.
                                echo esc_html( substr( $ref, 7 ) );
                                echo ' <span class="blc-recorded-tag">recorded</span>';
                            } elseif ( strpos( $ref, 'CASH' ) !== false || strpos( $ref, 'CHECK' ) !== false ) {
                                echo esc_html( $ref );
                            } else {
                                echo 'PayPal';
                            }
                        ?></td>
                        <td><?php echo $order->ticket_codes ? esc_html( $order->ticket_codes ) : '—'; ?></td>
                        <td>
                            <?php
                            if ( $order->check_in_statuses ) {
                                $statuses = explode( ',', $order->check_in_statuses );
                                $checked = array_sum( $statuses );
                                echo esc_html( $checked . '/' . count( $statuses ) );
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                        <td class="blc-col-actions">
                            <?php if ( $order->status === 'completed' ) : ?>
                                <button type="button" class="button blc-resend-btn" data-order-id="<?php echo esc_attr( $order->id ); ?>" data-email="<?php echo esc_attr( $order->buyer_email ); ?>">Resend Email</button>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
@media print {
    /* Hide WordPress admin sidebar, toolbar, and non-essential elements */
    #adminmenumain, #wpadminbar, #wpfooter, #screen-meta, #screen-meta-links,
    .blc-gala-filters, .notice, .updated, .update-nag, #blc-print-orders,
    .blc-resend-btn, .blc-col-actions, .blc-add-order { display: none !important; }
    #wpcontent, #wpbody-content { margin-left: 0 !important; padding: 0 !important; }
    .wrap { max-width: 100%; }
    .wp-list-table { font-size: 11px; }
    .wp-list-table th, .wp-list-table td { padding: 4px 6px !important; }
    h1 { font-size: 18px; margin-bottom: 10px; }
}
</style>

<script>
(function($) {
    $('.blc-resend-btn').on('click', function() {
        var btn = $(this);
        var orderId = btn.data('order-id');
        var email = btn.data('email');

        if (!confirm('Resend ticket email to ' + email + '?')) return;

        btn.prop('disabled', true).text('Sending...');

        $.ajax({
            url: blcGalaAdmin.restUrl + 'resend-email/' + orderId,
            method: 'POST',
            headers: { 'X-WP-Nonce': blcGalaAdmin.nonce },
            contentType: 'application/json',
            data: '{}',
            success: function(data) {
                if (data.success) {
                    btn.text('Sent!').css('color', '#0a7c00');
                    setTimeout(function() {
                        btn.prop('disabled', false).text('Resend Email').css('color', '');
                    }, 3000);
                } else {
                    alert(data.message || 'Failed to resend.');
                    btn.prop('disabled', false).text('Resend Email');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to resend.';
                alert(msg);
                btn.prop('disabled', false).text('Resend Email');
            }
        });
    });
})(jQuery);
</script>
