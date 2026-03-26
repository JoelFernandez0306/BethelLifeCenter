/**
 * Always on Mission Gala — Frontend JavaScript
 * Handles: countdown timer, live ticket counter, PayPal buttons, donation form.
 */
(function () {
    'use strict';

    var config = window.blcGala || {};

    // ========================================================================
    // Countdown Timer
    // ========================================================================
    function initCountdown() {
        if (!config.eventDate) return;

        // Normalize the date string — handle both "2026-10-17 18:00:00" and "2026-10-17T18:00:00"
        var dateStr = config.eventDate.replace(' ', 'T');
        // Ensure it parses correctly by appending timezone if missing
        if (dateStr.indexOf('Z') === -1 && dateStr.indexOf('+') === -1 && dateStr.indexOf('-', 10) === -1) {
            // No timezone info — treat as local time
        }
        var target = new Date(dateStr).getTime();
        if (isNaN(target)) return;

        function update() {
            var now = Date.now();
            var diff = target - now;

            if (diff <= 0) {
                setCountdown(0, 0, 0, 0);
                return;
            }

            var days = Math.floor(diff / 86400000);
            var hours = Math.floor((diff % 86400000) / 3600000);
            var minutes = Math.floor((diff % 3600000) / 60000);
            var seconds = Math.floor((diff % 60000) / 1000);
            setCountdown(days, hours, minutes, seconds);
        }

        function setCountdown(d, h, m, s) {
            var el;
            el = document.getElementById('blc-countdown-days');
            if (el) el.textContent = d;
            el = document.getElementById('blc-countdown-hours');
            if (el) el.textContent = h;
            el = document.getElementById('blc-countdown-minutes');
            if (el) el.textContent = m;
            el = document.getElementById('blc-countdown-seconds');
            if (el) el.textContent = s;
        }

        update();
        setInterval(update, 1000);
    }

    // ========================================================================
    // Live Ticket Counter (polls every 30 seconds)
    // ========================================================================
    var currentRemaining = config.remaining || 0;

    function pollTicketCount() {
        fetch(config.restUrl + 'remaining', { method: 'GET' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (typeof data.remaining !== 'undefined') {
                    currentRemaining = data.remaining;
                    updateCounterDisplay(data.remaining, data.total);

                    if (data.sold_out) {
                        showSoldOut();
                    }
                }
            })
            .catch(function () { /* silent fail */ });
    }

    function updateCounterDisplay(remaining, total) {
        var el = document.getElementById('blc-remaining-count');
        if (el) el.textContent = remaining;

        var bar = document.querySelector('.blc-counter-bar');
        if (bar && total > 0) {
            var pct = ((total - remaining) / total) * 100;
            bar.style.width = Math.min(pct, 100) + '%';
        }
    }

    function showSoldOut() {
        var ticketSection = document.getElementById('blc-gala-ticket-section');
        var soldOutSection = document.getElementById('blc-gala-soldout-section');
        if (ticketSection) ticketSection.style.display = 'none';
        if (soldOutSection) soldOutSection.style.display = 'block';
    }

    // ========================================================================
    // Quantity Selector + Dynamic Total
    // ========================================================================
    function initQuantitySelector() {
        var select = document.getElementById('blc-ticket-quantity');
        var totalEl = document.getElementById('blc-ticket-total');
        if (!select || !totalEl) return;

        select.addEventListener('change', function () {
            var qty = parseInt(select.value) || 1;
            var total = (qty * config.price).toFixed(2);
            totalEl.textContent = '$' + total;
        });
    }

    function getSelectedQuantity() {
        var select = document.getElementById('blc-ticket-quantity');
        return select ? (parseInt(select.value) || 1) : 1;
    }

    // ========================================================================
    // PayPal Ticket Purchase Buttons
    // ========================================================================
    function initTicketPayPal() {
        if (typeof paypal === 'undefined') return;
        if (config.soldOut) return;

        var container = document.getElementById('blc-paypal-button-container');
        if (!container) return;

        paypal.Buttons({
            style: {
                layout: 'vertical',
                color: 'gold',
                shape: 'rect',
                label: 'pay'
            },

            // Validate form before allowing PayPal to open
            onClick: function (data, actions) {
                var fname = document.getElementById('blc-ticket-fname');
                var lname = document.getElementById('blc-ticket-lname');
                var email = document.getElementById('blc-ticket-email');

                if (!fname || !fname.value.trim()) {
                    showMessage('blc-ticket-message', 'Please enter your first name.', 'error');
                    return actions.reject();
                }
                if (!lname || !lname.value.trim()) {
                    showMessage('blc-ticket-message', 'Please enter your last name.', 'error');
                    return actions.reject();
                }
                if (!email || !email.value.trim() || !isValidEmail(email.value)) {
                    showMessage('blc-ticket-message', 'Please enter a valid email address.', 'error');
                    return actions.reject();
                }

                hideMessage('blc-ticket-message');
                return actions.resolve();
            },

            createOrder: function () {
                var fname = document.getElementById('blc-ticket-fname').value.trim();
                var lname = document.getElementById('blc-ticket-lname').value.trim();
                var name = fname + ' ' + lname;
                var email = document.getElementById('blc-ticket-email').value.trim();
                var quantity = getSelectedQuantity();

                showMessage('blc-ticket-message', '<span class="blc-spinner"></span> Processing your order...', 'loading');

                return fetch(config.restUrl + 'create-order', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': config.nonce
                    },
                    body: JSON.stringify({
                        buyer_name: name,
                        buyer_email: email,
                        quantity: quantity
                    })
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.paypal_order_id) {
                        // Store order UUID for capture step
                        container.dataset.orderUuid = data.order_uuid;
                        return data.paypal_order_id;
                    }
                    var msg = data.message || 'Failed to create order. Please try again.';
                    showMessage('blc-ticket-message', msg, 'error');
                    throw new Error(msg);
                });
            },

            onApprove: function (data) {
                showMessage('blc-ticket-message', '<span class="blc-spinner"></span> Confirming your payment...', 'loading');

                return fetch(config.restUrl + 'capture-order', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': config.nonce
                    },
                    body: JSON.stringify({
                        paypal_order_id: data.orderID,
                        order_uuid: container.dataset.orderUuid
                    })
                })
                .then(function (r) { return r.json(); })
                .then(function (result) {
                    if (result.success) {
                        showMessage('blc-ticket-message', result.message, 'success');
                        // Refresh the counter
                        pollTicketCount();
                        // Hide the form
                        var form = document.getElementById('blc-gala-ticket-form');
                        if (form) {
                            var inputs = form.querySelectorAll('input');
                            inputs.forEach(function (i) { i.disabled = true; });
                        }
                        container.innerHTML = '';
                    } else {
                        var msg = result.message || 'Payment could not be confirmed. Please contact us.';
                        showMessage('blc-ticket-message', msg, 'error');
                    }
                })
                .catch(function () {
                    showMessage('blc-ticket-message', 'Something went wrong. Please contact us if you were charged.', 'error');
                });
            },

            onError: function () {
                showMessage('blc-ticket-message', 'PayPal encountered an error. Please try again.', 'error');
            },

            onCancel: function () {
                hideMessage('blc-ticket-message');
            }
        }).render('#blc-paypal-button-container');
    }

    // ========================================================================
    // PayPal Donation Buttons
    // ========================================================================
    function initDonationPayPal() {
        if (typeof paypal === 'undefined') return;
        if (!config.donationEnabled) return;

        var container = document.getElementById('blc-paypal-donation-container');
        if (!container) return;

        paypal.Buttons({
            style: {
                layout: 'vertical',
                color: 'blue',
                shape: 'rect',
                label: 'donate'
            },

            onClick: function (data, actions) {
                var fname = document.getElementById('blc-donation-fname');
                var lname = document.getElementById('blc-donation-lname');
                var email = document.getElementById('blc-donation-email');
                var amount = document.getElementById('blc-donation-amount');

                if (!fname || !fname.value.trim()) {
                    showMessage('blc-donation-message', 'Please enter your first name.', 'error');
                    return actions.reject();
                }
                if (!lname || !lname.value.trim()) {
                    showMessage('blc-donation-message', 'Please enter your last name.', 'error');
                    return actions.reject();
                }
                if (!email || !email.value.trim() || !isValidEmail(email.value)) {
                    showMessage('blc-donation-message', 'Please enter a valid email address.', 'error');
                    return actions.reject();
                }
                if (!amount || !amount.value || parseFloat(amount.value) < 1) {
                    showMessage('blc-donation-message', 'Please enter a donation amount of at least $1.00.', 'error');
                    return actions.reject();
                }

                hideMessage('blc-donation-message');
                return actions.resolve();
            },

            createOrder: function () {
                var fname = document.getElementById('blc-donation-fname').value.trim();
                var lname = document.getElementById('blc-donation-lname').value.trim();
                var name = fname + ' ' + lname;
                var email = document.getElementById('blc-donation-email').value.trim();
                var amount = parseFloat(document.getElementById('blc-donation-amount').value);

                showMessage('blc-donation-message', '<span class="blc-spinner"></span> Processing your donation...', 'loading');

                return fetch(config.restUrl + 'create-donation', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': config.nonce
                    },
                    body: JSON.stringify({
                        buyer_name: name,
                        buyer_email: email,
                        amount: amount
                    })
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.paypal_order_id) {
                        container.dataset.orderUuid = data.order_uuid;
                        return data.paypal_order_id;
                    }
                    var msg = data.message || 'Failed to create donation. Please try again.';
                    showMessage('blc-donation-message', msg, 'error');
                    throw new Error(msg);
                });
            },

            onApprove: function (data) {
                showMessage('blc-donation-message', '<span class="blc-spinner"></span> Confirming your donation...', 'loading');

                return fetch(config.restUrl + 'capture-donation', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': config.nonce
                    },
                    body: JSON.stringify({
                        paypal_order_id: data.orderID,
                        order_uuid: container.dataset.orderUuid
                    })
                })
                .then(function (r) { return r.json(); })
                .then(function (result) {
                    if (result.success) {
                        showMessage('blc-donation-message', result.message, 'success');
                        var form = document.getElementById('blc-gala-donation-form');
                        if (form) {
                            var inputs = form.querySelectorAll('input');
                            inputs.forEach(function (i) { i.disabled = true; });
                        }
                        container.innerHTML = '';
                    } else {
                        var msg = result.message || 'Donation could not be confirmed. Please contact us.';
                        showMessage('blc-donation-message', msg, 'error');
                    }
                })
                .catch(function () {
                    showMessage('blc-donation-message', 'Something went wrong. Please contact us if you were charged.', 'error');
                });
            },

            onError: function () {
                showMessage('blc-donation-message', 'PayPal encountered an error. Please try again.', 'error');
            },

            onCancel: function () {
                hideMessage('blc-donation-message');
            }
        }).render('#blc-paypal-donation-container');
    }

    // ========================================================================
    // Donation Amount Presets
    // ========================================================================
    function initDonationPresets() {
        var buttons = document.querySelectorAll('.blc-preset-btn');
        var input = document.getElementById('blc-donation-amount');
        if (!buttons.length || !input) return;

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                buttons.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                input.value = btn.dataset.amount;
            });
        });

        input.addEventListener('input', function () {
            buttons.forEach(function (b) { b.classList.remove('active'); });
        });
    }

    // ========================================================================
    // Helpers
    // ========================================================================
    function showMessage(id, html, type) {
        var el = document.getElementById(id);
        if (!el) return;
        el.innerHTML = html;
        el.className = 'blc-message blc-' + type;
        el.style.display = 'block';
    }

    function hideMessage(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.style.display = 'none';
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // ========================================================================
    // Initialize
    // ========================================================================
    function init() {
        initCountdown();
        initQuantitySelector();
        initTicketPayPal();
        initDonationPayPal();
        initDonationPresets();

        // Poll ticket count every 30 seconds
        setInterval(pollTicketCount, 30000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
