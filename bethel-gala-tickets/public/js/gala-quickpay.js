/**
 * Always on Mission Gala — Quick Pay
 * Powers the fixed-amount QR payment page and the PIN-gated purchase log.
 */
(function () {
    'use strict';

    var config = window.blcQuickPay || {};
    var adminToken = null;

    // ========================================================================
    // Payment page
    // ========================================================================
    function initPayment() {
        var container = document.getElementById('blc-quickpay-buttons');
        if (!container || !config.itemId) return;

        if (typeof paypal === 'undefined') {
            container.innerHTML = '<div class="blc-message blc-error" style="display:block;">PayPal failed to load. Please refresh the page and try again.</div>';
            return;
        }

        paypal.Buttons({
            style: { layout: 'vertical', color: 'gold', shape: 'rect', label: 'pay' },

            createOrder: function () {
                showMessage('blc-quickpay-message', '<span class="blc-spinner"></span> Starting your payment...', 'loading');

                return fetch(config.restUrl + 'quickpay-create-order', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ item_id: config.itemId })
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.paypal_order_id) {
                        container.dataset.orderUuid = data.order_uuid;
                        return data.paypal_order_id;
                    }
                    var msg = data.message || 'Could not start the payment. Please try again.';
                    showMessage('blc-quickpay-message', msg, 'error');
                    throw new Error(msg);
                });
            },

            onApprove: function (data) {
                showMessage('blc-quickpay-message', '<span class="blc-spinner"></span> Confirming your payment...', 'loading');

                return fetch(config.restUrl + 'quickpay-capture-order', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        paypal_order_id: data.orderID,
                        order_uuid: container.dataset.orderUuid
                    })
                })
                .then(function (r) { return r.json(); })
                .then(function (result) {
                    if (result.success) {
                        showMessage('blc-quickpay-message', result.message, 'success');
                        container.innerHTML = '';
                    } else {
                        showMessage('blc-quickpay-message', result.message || 'Payment could not be confirmed. Please contact us.', 'error');
                    }
                })
                .catch(function () {
                    showMessage('blc-quickpay-message', 'Something went wrong. Please contact us if you were charged.', 'error');
                });
            },

            onError: function () {
                showMessage('blc-quickpay-message', 'PayPal encountered an error. Please try again.', 'error');
            },

            onCancel: function () {
                hideMessage('blc-quickpay-message');
            }
        }).render('#blc-quickpay-buttons');
    }

    // ========================================================================
    // Purchase log (PIN gated)
    // ========================================================================
    function initReport() {
        var form = document.getElementById('blc-qp-pin-form');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var pin = document.getElementById('blc-qp-pin-input').value.trim();
            if (!pin) return;

            showMessage('blc-qp-pin-message', 'Verifying...', 'loading');

            fetch(config.restUrl + 'admin-auth', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pin: pin })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.token) {
                    adminToken = data.token;
                    hideMessage('blc-qp-pin-message');
                    document.getElementById('blc-qp-pin-section').style.display = 'none';
                    document.getElementById('blc-qp-report-section').style.display = 'block';
                    loadPurchases();
                } else {
                    showMessage('blc-qp-pin-message', data.message || 'Incorrect code.', 'error');
                }
            })
            .catch(function () {
                showMessage('blc-qp-pin-message', 'Connection error. Please try again.', 'error');
            });
        });

        var refresh = document.getElementById('blc-qp-refresh');
        if (refresh) refresh.addEventListener('click', loadPurchases);
    }

    function loadPurchases() {
        var list = document.getElementById('blc-qp-list');
        if (!list || !adminToken) return;

        fetch(config.restUrl + 'quickpay-purchases', {
            method: 'GET',
            headers: { 'X-BLC-Admin-Token': adminToken }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.purchases) {
                list.innerHTML = '<p style="text-align:center;color:#c0392b;">Could not load payments.</p>';
                return;
            }
            document.getElementById('blc-qp-count').textContent = data.count;
            document.getElementById('blc-qp-sum').textContent = '$' + Number(data.total).toFixed(2);
            renderPurchases(data.purchases);
        })
        .catch(function () {
            list.innerHTML = '<p style="text-align:center;color:#c0392b;">Connection error.</p>';
        });
    }

    function renderPurchases(purchases) {
        var list = document.getElementById('blc-qp-list');

        if (!purchases.length) {
            list.innerHTML = '<p style="text-align:center;color:#666;">No payments yet.</p>';
            return;
        }

        var html = '<div style="overflow-x:auto;"><table class="blc-quickpay-table">';
        html += '<thead><tr>' +
                '<th>Date</th><th>Name</th><th>Card</th><th>For</th><th style="text-align:right;">Amount</th>' +
                '</tr></thead><tbody>';

        for (var i = 0; i < purchases.length; i++) {
            var p = purchases[i];
            html += '<tr>' +
                '<td>' + escapeHtml(formatDate(p.created_at)) + '</td>' +
                '<td style="font-weight:600;">' + escapeHtml(p.name) + '</td>' +
                '<td>' + escapeHtml(formatCard(p)) + '</td>' +
                '<td>' + escapeHtml(p.label || '—') + '</td>' +
                '<td style="text-align:right;font-weight:600;">$' + Number(p.amount).toFixed(2) + '</td>' +
                '</tr>';
        }

        html += '</tbody></table></div>';
        list.innerHTML = html;
    }

    /**
     * Card digits only exist for guest card payments. Paying from a PayPal
     * balance involves no card, so say so rather than showing a blank cell.
     */
    function formatCard(p) {
        if (!p.card_last4) return 'PayPal account';
        var brand = p.card_brand ? titleCase(p.card_brand) + ' ' : '';
        return brand + '•••• ' + p.card_last4;
    }

    function titleCase(s) {
        return s.charAt(0).toUpperCase() + s.slice(1).toLowerCase();
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr.replace(' ', 'T'));
        if (isNaN(d.getTime())) return dateStr;

        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        var hours = d.getHours();
        var mins = d.getMinutes();
        var ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;
        var minStr = mins < 10 ? '0' + mins : '' + mins;

        return months[d.getMonth()] + ' ' + d.getDate() + ', ' + hours + ':' + minStr + ' ' + ampm;
    }

    // ========================================================================
    // Helpers
    // ========================================================================
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text == null ? '' : String(text)));
        return div.innerHTML;
    }

    function showMessage(id, html, type) {
        var el = document.getElementById(id);
        if (!el) return;
        el.innerHTML = html;
        el.className = 'blc-message blc-' + type;
        el.style.display = 'block';
    }

    function hideMessage(id) {
        var el = document.getElementById(id);
        if (el) el.style.display = 'none';
    }

    function init() {
        initPayment();
        initReport();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
