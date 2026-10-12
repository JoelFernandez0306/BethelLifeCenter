/**
 * Always on Mission Gala — Quick Pay
 * Powers the fixed-amount QR payment page and the PIN-gated purchase log.
 */
(function () {
    'use strict';

    var config = window.blcQuickPay || {};
    var adminToken = null;

    // ========================================================================
    // Quantity
    // ========================================================================
    function money(n) {
        return '$' + Number(n).toFixed(2);
    }

    /**
     * The quantity currently in the box, clamped to something payable.
     * Anything unusable (empty, text, decimals, out of range) falls back
     * to the nearest valid whole number so a price is always shown.
     */
    function getQuantity() {
        var input = document.getElementById('blc-quickpay-qty-input');
        var max = Number(config.maxQuantity) || 99;
        var q = input ? parseInt(input.value, 10) : 1;
        if (isNaN(q) || q < 1) q = 1;
        if (q > max) q = max;
        return q;
    }

    function setText(id, text) {
        var el = document.getElementById(id);
        if (el) el.textContent = text;
    }

    function showRow(id, visible) {
        var el = document.getElementById(id);
        if (el) el.style.display = visible ? '' : 'none';
    }

    /**
     * Refresh every figure on the page for the chosen quantity. Prices come
     * from the server-built table, so nothing is calculated here.
     */
    function updateTotals() {
        var q = getQuantity();
        var row = config.prices && config.prices[q];
        if (!row) return;

        var each = row.subtotal / q;

        setText('blc-quickpay-total-big', money(row.total));
        setText('blc-quickpay-subtotal-label', q + ' × ' + money(each));
        setText('blc-quickpay-subtotal', money(row.subtotal));
        setText('blc-quickpay-fee', money(row.fee));
        setText('blc-quickpay-total', money(row.total));

        var hasFee = row.fee > 0;
        showRow('blc-quickpay-fee-row', hasFee);
        showRow('blc-quickpay-total-row', hasFee);
    }

    function initQuantity() {
        var input = document.getElementById('blc-quickpay-qty-input');
        if (!input) return;

        var max = Number(config.maxQuantity) || 99;

        function set(q) {
            input.value = Math.min(max, Math.max(1, q));
            updateTotals();
        }

        document.getElementById('blc-quickpay-minus').addEventListener('click', function () {
            set(getQuantity() - 1);
        });
        document.getElementById('blc-quickpay-plus').addEventListener('click', function () {
            set(getQuantity() + 1);
        });

        // Typing is allowed to pass through an empty box on its way to a new
        // number, so only live-update here and tidy the value once they leave.
        input.addEventListener('input', updateTotals);
        input.addEventListener('change', function () { set(getQuantity()); });
        input.addEventListener('blur', function () { set(getQuantity()); });

        updateTotals();
    }

    function lockQuantity() {
        ['blc-quickpay-qty-input', 'blc-quickpay-minus', 'blc-quickpay-plus'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.disabled = true;
        });
    }

    // ========================================================================
    // Donation (open amount)
    // ========================================================================

    /**
     * Read what the donor typed as whole cents, or null if it is not a
     * clean money amount. Done with string handling rather than parseFloat so
     * "19.99" is exactly 1999 cents and never 1998.9999999.
     */
    function parseCents(text) {
        var t = String(text == null ? '' : text).replace(/[\s,$]/g, '');
        // "25." and ".50" are allowed so nothing looks wrong mid-typing.
        var m = /^(\d*)(?:\.(\d{0,2}))?$/.exec(t);
        if (!m) return null;

        var dollars = m[1], frac = m[2] || '';
        if (dollars === '' && frac === '') return null;   // nothing but a dot
        if (dollars.length > 7) return null;

        var cents = frac ? parseInt((frac + '0').slice(0, 2), 10) : 0;
        return (dollars === '' ? 0 : parseInt(dollars, 10)) * 100 + cents;
    }

    /**
     * What the donor is charged for a gift. This mirrors
     * BLC_Gala_QuickPay::donation_total() step for step, in whole numbers,
     * so the total on screen is always the total the server charges.
     *
     * total = (gift + fixed) / (1 - rate), rounded half up to the cent.
     */
    function donationTotalCents(gift) {
        var d = config.donation;
        var fixed = d.fixedCents, rate = d.rateHund;
        if (rate <= 0 && fixed <= 0) return gift;
        var num = (gift + fixed) * 10000;
        var den = 10000 - rate;
        var n = 2 * num + den, m = 2 * den;
        return (n - (n % m)) / m;
    }

    function donationAmountError(cents, hadText) {
        var d = config.donation;
        if (cents === null) return hadText ? 'Please enter an amount like 25 or 25.50.' : '';
        if (cents < d.minCents) return 'The minimum donation is ' + money(d.minCents / 100) + '.';
        if (cents > d.maxCents) return 'For a gift over ' + money(d.maxCents / 100).replace('.00', '') + ', please contact the church directly.';
        return '';
    }

    function currentDonationCents() {
        var input = document.getElementById('blc-don-amount');
        return input ? parseCents(input.value) : null;
    }

    function updateDonation() {
        var input = document.getElementById('blc-don-amount');
        if (!input) return;

        var cents = parseCents(input.value);
        var err = donationAmountError(cents, input.value.trim() !== '');
        var errEl = document.getElementById('blc-don-error');
        if (errEl) {
            errEl.textContent = err;
            errEl.style.display = err ? 'block' : 'none';
        }

        var ok = cents !== null && !err;
        var total = ok ? donationTotalCents(cents) : 0;
        var gift = ok ? cents : 0;
        var fee = ok ? total - cents : 0;

        setText('blc-don-gift', money(gift / 100));
        setText('blc-don-fee', money(fee / 100));
        setText('blc-don-total', money(total / 100));
        setText('blc-quickpay-total-big', money(total / 100));

        // The fee lines appear whenever a fee is set up, even before an
        // amount is typed, so the donor knows up front it will be added.
        showRow('blc-don-fee-row', config.donation.hasFee);
        showRow('blc-don-total-row', config.donation.hasFee);

        // Keep the preset highlight honest: lit only when it matches the box.
        document.querySelectorAll('.blc-don-preset').forEach(function (b) {
            b.classList.toggle('active', ok && parseCents(b.getAttribute('data-amount')) === cents);
        });
    }

    function initDonation() {
        var input = document.getElementById('blc-don-amount');
        if (!input) return;

        document.querySelectorAll('.blc-don-preset').forEach(function (btn) {
            btn.addEventListener('click', function () {
                input.value = btn.getAttribute('data-amount');
                updateDonation();
            });
        });

        input.addEventListener('input', updateDonation);
        updateDonation();
    }

    function lockDonation() {
        var input = document.getElementById('blc-don-amount');
        if (input) input.disabled = true;
        document.querySelectorAll('.blc-don-preset').forEach(function (b) { b.disabled = true; });
    }

    // ========================================================================
    // Payment page
    // ========================================================================
    function initPayment() {
        var container = document.getElementById('blc-quickpay-buttons');
        if (!container || !config.itemId) return;

        var donate = !!config.donation;
        if (donate) { initDonation(); } else { initQuantity(); }

        if (typeof paypal === 'undefined') {
            container.innerHTML = '<div class="blc-message blc-error" style="display:block;">PayPal failed to load. Please refresh the page and try again.</div>';
            return;
        }

        paypal.Buttons({
            style: { layout: 'vertical', color: 'gold', shape: 'rect', label: donate ? 'donate' : 'pay' },

            // A donation has no fixed price, so check the amount before PayPal
            // opens. The server checks it again; this just saves a wasted trip.
            onClick: function (data, actions) {
                if (!donate) return actions.resolve();

                var cents = currentDonationCents();
                var err = donationAmountError(cents, true) || (cents === null ? 'Please enter the amount you would like to give.' : '');
                if (err) {
                    showMessage('blc-quickpay-message', err, 'error');
                    return actions.reject();
                }
                hideMessage('blc-quickpay-message');
                return actions.resolve();
            },

            createOrder: function () {
                showMessage('blc-quickpay-message', '<span class="blc-spinner"></span> Starting your payment...', 'loading');

                return fetch(config.restUrl + 'quickpay-create-order', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    // Read at the moment they pay, so a change made after the
                    // PayPal buttons appeared is still honoured. The server
                    // works out the price; this is only how many.
                    body: JSON.stringify(donate
                        ? { item_id: config.itemId, amount_cents: currentDonationCents() }
                        : { item_id: config.itemId, quantity: getQuantity() })
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
                        if (donate) { lockDonation(); } else { lockQuantity(); }
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
                '<th>Date</th><th>Name</th><th>Card</th><th>For</th><th style="text-align:right;">Tickets</th><th style="text-align:right;">Amount</th>' +
                '</tr></thead><tbody>';

        for (var i = 0; i < purchases.length; i++) {
            var p = purchases[i];
            html += '<tr>' +
                '<td>' + escapeHtml(formatDate(p.created_at)) + '</td>' +
                '<td style="font-weight:600;">' + escapeHtml(p.name) + '</td>' +
                '<td>' + escapeHtml(formatCard(p)) + '</td>' +
                '<td>' + escapeHtml(p.label || '—') + '</td>' +
                '<td style="text-align:right;">' + (p.quantity === 0 ? '\u2014' : escapeHtml(p.quantity || 1)) + '</td>' +
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
