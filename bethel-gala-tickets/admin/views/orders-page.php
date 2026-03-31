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
                <th>Ticket Codes</th>
                <th>Checked In</th>
                <th class="blc-col-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $orders ) ) : ?>
                <tr><td colspan="10">No orders found.</td></tr>
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
    .blc-resend-btn, .blc-col-actions { display: none !important; }
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
