/**
 * Always on Mission Gala — Frontend JavaScript
 * Handles: countdown timer, live ticket counter, PayPal buttons, donation form, cash/check modal.
 */
(function () {
    'use strict';

    var config = window.blcGala || {};

    // ========================================================================
    // Fee Calculation
    // ========================================================================
    function calculateFee(subtotal) {
        var rate = Number(config.feeRate) || 0;   // e.g. 2.99 (percent)
        var fixed = Number(config.feeFixed) || 0;  // e.g. 0.49 (dollars)
        if (rate <= 0 && fixed <= 0) return 0;
        // Calculate what to charge so that after PayPal takes its cut, church gets full subtotal
        var rateDecimal = rate / 100;
        var chargeTotal = (subtotal + fixed) / (1 - rateDecimal);
        var fee = chargeTotal - subtotal;
        return Math.round(fee * 100) / 100;
    }

    // ========================================================================
    // Countdown Timer
    // ========================================================================
    function initCountdown() {
        if (!config.eventDate) return;

        var dateStr = config.eventDate.replace(' ', 'T');
        if (dateStr.indexOf('Z') === -1 && dateStr.indexOf('+') === -1 && dateStr.indexOf('-', 10) === -1) {
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
    // Quantity Selector + Dynamic Total with Fee
    // ========================================================================
    function initQuantitySelector() {
        var select = document.getElementById('blc-ticket-quantity');
        var totalEl = document.getElementById('blc-ticket-total');
        var feeEl = document.getElementById('blc-ticket-fee');
        var grandEl = document.getElementById('blc-ticket-grand-total');
        if (!select || !totalEl) return;

        function updateTotal() {
            var qty = parseInt(select.value) || 1;
            var subtotal = qty * config.price;
            var fee = calculateFee(subtotal);
            var grand = subtotal + fee;

            totalEl.textContent = '$' + subtotal.toFixed(2);
            if (feeEl) feeEl.textContent = '$' + fee.toFixed(2);
            if (grandEl) grandEl.textContent = '$' + grand.toFixed(2);
        }

        select.addEventListener('change', updateTotal);
        updateTotal();
    }

    function getSelectedQuantity() {
        var select = document.getElementById('blc-ticket-quantity');
        return select ? (parseInt(select.value) || 1) : 1;
    }

    // ========================================================================
    // PayPal Ticket Purchase Buttons
    // ========================================================================
    function initTicketPayPal() {
        if (config.soldOut) return;

        var container = document.getElementById('blc-paypal-button-container');
        if (!container) return;

        if (typeof paypal === 'undefined') {
            container.innerHTML = '<div class="blc-message blc-error" style="display:block;">PayPal failed to load. Please check that the Client ID is correct and refresh the page.</div>';
            return;
        }

        paypal.Buttons({
            style: {
                layout: 'vertical',
                color: 'gold',
                shape: 'rect',
                label: 'pay'
            },

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
                        pollTicketCount();
                        var form = document.getElementById('blc-gala-ticket-form');
                        if (form) {
                            var inputs = form.querySelectorAll('input, select');
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
        if (!config.donationEnabled) return;

        var container = document.getElementById('blc-paypal-donation-container');
        if (!container) return;

        if (typeof paypal === 'undefined') {
            container.innerHTML = '<div class="blc-message blc-error" style="display:block;">PayPal failed to load. Please check that the Client ID is correct and refresh the page.</div>';
            return;
        }

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
    // Cash/Check Modal
    // ========================================================================
    var ccAdminToken = null;
    var ccPaymentMethod = '';

    function initCashCheck() {
        var triggerBtn = document.getElementById('blc-cashcheck-btn');
        var overlay = document.getElementById('blc-cashcheck-overlay');
        if (!triggerBtn || !overlay) return;

        function showStep(stepId) {
            overlay.querySelectorAll('.blc-cc-step').forEach(function (s) { s.style.display = 'none'; });
            document.getElementById(stepId).style.display = 'block';
        }

        function closeModal() {
            overlay.style.display = 'none';
            ccPaymentMethod = '';
            // Reset fields
            var pin = document.getElementById('blc-cc-pin');
            if (pin) pin.value = '';
            document.getElementById('blc-cc-fname').value = '';
            document.getElementById('blc-cc-lname').value = '';
            document.getElementById('blc-cc-email').value = '';
            var chk = document.getElementById('blc-cc-check-number');
            if (chk) chk.value = '';
            var cashAmt = document.getElementById('blc-cc-cash-amount');
            if (cashAmt) cashAmt.value = '';
            var checkAmt = document.getElementById('blc-cc-check-amount');
            if (checkAmt) checkAmt.value = '';
            var qty = document.getElementById('blc-cc-quantity');
            if (qty) qty.selectedIndex = 0;
            overlay.querySelectorAll('.blc-message').forEach(function (m) { m.style.display = 'none'; });
            overlay.querySelectorAll('.blc-cc-method-btn').forEach(function (b) { b.classList.remove('active'); });
            document.getElementById('blc-cc-check-field').style.display = 'none';
            document.getElementById('blc-cc-split-fields').style.display = 'none';
            var splitWarn = document.getElementById('blc-cc-split-warning');
            if (splitWarn) { splitWarn.style.display = 'none'; splitWarn.textContent = ''; }
            document.getElementById('blc-cc-next-info').style.display = 'none';
        }

        // Open modal
        triggerBtn.addEventListener('click', function () {
            overlay.style.display = 'flex';
            if (ccAdminToken) {
                showStep('blc-cc-step-method');
            } else {
                showStep('blc-cc-step-pin');
                document.getElementById('blc-cc-pin').focus();
            }
        });

        // Cancel buttons
        document.getElementById('blc-cc-cancel-pin').addEventListener('click', closeModal);
        document.getElementById('blc-cc-cancel-method').addEventListener('click', closeModal);

        // Step 1: Verify PIN
        document.getElementById('blc-cc-verify-pin').addEventListener('click', function () {
            var pin = document.getElementById('blc-cc-pin').value.trim();
            if (!pin) return;

            showMessage('blc-cc-pin-msg', 'Verifying...', 'loading');

            fetch(config.restUrl + 'admin-auth', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pin: pin })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.token) {
                    ccAdminToken = data.token;
                    hideMessage('blc-cc-pin-msg');
                    showStep('blc-cc-step-method');
                } else {
                    showMessage('blc-cc-pin-msg', data.message || 'Incorrect code.', 'error');
                }
            })
            .catch(function () {
                showMessage('blc-cc-pin-msg', 'Connection error.', 'error');
            });
        });

        // Allow Enter key on PIN
        document.getElementById('blc-cc-pin').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') document.getElementById('blc-cc-verify-pin').click();
        });

        // Step 2: Select method (exclusive — one choice at a time)
        overlay.querySelectorAll('.blc-cc-method-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                overlay.querySelectorAll('.blc-cc-method-btn').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                ccPaymentMethod = btn.dataset.method;
                document.getElementById('blc-cc-next-info').style.display = 'inline-block';
            });
        });

        // Update order total display when quantity changes
        var ccQtySelect = document.getElementById('blc-cc-quantity');
        if (ccQtySelect) {
            ccQtySelect.addEventListener('change', function () {
                var qty = parseInt(ccQtySelect.value) || 1;
                var total = qty * config.price;
                var totalEl = document.getElementById('blc-cc-order-total');
                if (totalEl) totalEl.textContent = 'Total: $' + total.toFixed(2);
            });
        }

        // Step 2 -> 3: show/hide check & split fields based on method
        document.getElementById('blc-cc-next-info').addEventListener('click', function () {
            var checkField = document.getElementById('blc-cc-check-field');
            var splitFields = document.getElementById('blc-cc-split-fields');
            var needsCheck = ccPaymentMethod.indexOf('check') !== -1;
            var isSplit = ccPaymentMethod === 'cash+check';

            checkField.style.display = needsCheck ? 'block' : 'none';
            splitFields.style.display = isSplit ? 'block' : 'none';

            showStep('blc-cc-step-info');
        });

        // Step 3 -> 2 (back)
        document.getElementById('blc-cc-back-method').addEventListener('click', function () {
            showStep('blc-cc-step-method');
        });

        // Step 3 -> 4 (review)
        document.getElementById('blc-cc-next-review').addEventListener('click', function () {
            var fname = document.getElementById('blc-cc-fname').value.trim();
            var lname = document.getElementById('blc-cc-lname').value.trim();
            var email = document.getElementById('blc-cc-email').value.trim();
            var qty = parseInt(document.getElementById('blc-cc-quantity').value) || 1;

            if (!fname) { showMessage('blc-cc-info-msg', 'First name is required.', 'error'); return; }
            if (!lname) { showMessage('blc-cc-info-msg', 'Last name is required.', 'error'); return; }
            if (!email || !isValidEmail(email)) { showMessage('blc-cc-info-msg', 'Valid email is required.', 'error'); return; }

            // Validate check number if needed
            if (ccPaymentMethod.indexOf('check') !== -1) {
                var checkNum = document.getElementById('blc-cc-check-number').value.trim();
                if (!checkNum) { showMessage('blc-cc-info-msg', 'Check number is required.', 'error'); return; }
            }

            var totalAmount = qty * config.price;

            // Validate split amounts for cash+check
            if (ccPaymentMethod === 'cash+check') {
                var cashAmt = parseFloat(document.getElementById('blc-cc-cash-amount').value) || 0;
                var checkAmt = parseFloat(document.getElementById('blc-cc-check-amount').value) || 0;
                var splitSum = Math.round((cashAmt + checkAmt) * 100) / 100;
                var splitWarn = document.getElementById('blc-cc-split-warning');

                if (cashAmt <= 0) { showMessage('blc-cc-info-msg', 'Cash amount must be greater than $0.', 'error'); return; }
                if (checkAmt <= 0) { showMessage('blc-cc-info-msg', 'Check amount must be greater than $0.', 'error'); return; }
                if (Math.abs(splitSum - totalAmount) > 0.01) {
                    splitWarn.textContent = 'Cash ($' + cashAmt.toFixed(2) + ') + Check ($' + checkAmt.toFixed(2) + ') = $' + splitSum.toFixed(2) + ' but total is $' + totalAmount.toFixed(2);
                    splitWarn.style.display = 'block';
                    showMessage('blc-cc-info-msg', 'Cash + Check amounts must equal the total ($' + totalAmount.toFixed(2) + ').', 'error');
                    return;
                }
                if (splitWarn) { splitWarn.style.display = 'none'; }
            }

            hideMessage('blc-cc-info-msg');

            var amount = totalAmount.toFixed(2);
            var methodLabel;
            if (ccPaymentMethod === 'cash') {
                methodLabel = 'Cash';
            } else if (ccPaymentMethod === 'check') {
                methodLabel = 'Check #' + document.getElementById('blc-cc-check-number').value.trim();
            } else if (ccPaymentMethod === 'cash+check') {
                var ca = parseFloat(document.getElementById('blc-cc-cash-amount').value) || 0;
                var ka = parseFloat(document.getElementById('blc-cc-check-amount').value) || 0;
                methodLabel = 'Cash $' + ca.toFixed(2) + ' + Check #' + document.getElementById('blc-cc-check-number').value.trim() + ' $' + ka.toFixed(2);
            } else {
                methodLabel = ccPaymentMethod;
            }

            document.getElementById('blc-cc-review-name').textContent = fname + ' ' + lname;
            document.getElementById('blc-cc-review-email').textContent = email;
            document.getElementById('blc-cc-review-qty').textContent = qty + ' ticket(s)';
            document.getElementById('blc-cc-review-amount').textContent = '$' + amount;
            document.getElementById('blc-cc-review-method').textContent = methodLabel;

            showStep('blc-cc-step-review');
        });

        // Step 4 -> 3 (back)
        document.getElementById('blc-cc-back-info').addEventListener('click', function () {
            showStep('blc-cc-step-info');
        });

        // Step 4: Confirm
        document.getElementById('blc-cc-confirm').addEventListener('click', function () {
            var fname = document.getElementById('blc-cc-fname').value.trim();
            var lname = document.getElementById('blc-cc-lname').value.trim();
            var email = document.getElementById('blc-cc-email').value.trim();
            var qty = parseInt(document.getElementById('blc-cc-quantity').value) || 1;
            var checkNum = document.getElementById('blc-cc-check-number').value.trim();

            var bodyData = {
                buyer_name: fname + ' ' + lname,
                buyer_email: email,
                quantity: qty,
                payment_method: ccPaymentMethod,
                check_number: checkNum
            };

            if (ccPaymentMethod === 'cash+check') {
                bodyData.cash_amount = parseFloat(document.getElementById('blc-cc-cash-amount').value) || 0;
                bodyData.check_amount = parseFloat(document.getElementById('blc-cc-check-amount').value) || 0;
            }

            showMessage('blc-cc-review-msg', '<span class="blc-spinner"></span> Processing...', 'loading');
            document.getElementById('blc-cc-confirm').disabled = true;

            fetch(config.restUrl + 'cash-check-order', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-BLC-Admin-Token': ccAdminToken
                },
                body: JSON.stringify(bodyData)
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                document.getElementById('blc-cc-confirm').disabled = false;
                if (data.success) {
                    document.getElementById('blc-cc-done-msg').textContent = data.message;
                    showStep('blc-cc-step-done');
                    pollTicketCount();
                } else {
                    showMessage('blc-cc-review-msg', data.message || 'Failed to process order.', 'error');
                }
            })
            .catch(function () {
                document.getElementById('blc-cc-confirm').disabled = false;
                showMessage('blc-cc-review-msg', 'Connection error. Please try again.', 'error');
            });
        });

        // Done: close
        document.getElementById('blc-cc-done-close').addEventListener('click', function () {
            closeModal();
            showStep('blc-cc-step-pin');
        });

        // Close on overlay click
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeModal();
        });
    }

    // ========================================================================
    // Update PayPal order amount to include fees
    // ========================================================================
    // Override the create-order to pass the total with fee
    // This is handled in the createOrder callback above via the server-side amount

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
        initCashCheck();

        // Poll ticket count every 30 seconds
        setInterval(pollTicketCount, 30000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
