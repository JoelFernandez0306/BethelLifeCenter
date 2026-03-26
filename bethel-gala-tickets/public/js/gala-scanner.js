/**
 * Always on Mission Gala — Volunteer Door Scanner
 * PIN-protected public scanner page. No WordPress login required.
 */
(function () {
    'use strict';

    var config = window.blcScanner || {};
    var scannerInstance = null;
    var scannerToken = null;

    // ========================================================================
    // Token Management (localStorage)
    // ========================================================================
    function getStoredToken() {
        var token = localStorage.getItem('blcScannerToken');
        var date = localStorage.getItem('blcScannerTokenDate');
        var today = new Date().toISOString().slice(0, 10);

        if (token && date === today) {
            return token;
        }

        // Expired — clear it
        localStorage.removeItem('blcScannerToken');
        localStorage.removeItem('blcScannerTokenDate');
        return null;
    }

    function storeToken(token, expires) {
        var today = new Date().toISOString().slice(0, 10);
        localStorage.setItem('blcScannerToken', token);
        localStorage.setItem('blcScannerTokenDate', today);
    }

    // ========================================================================
    // PIN Authentication
    // ========================================================================
    function initPinForm() {
        var form = document.getElementById('blc-scanner-pin-form');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var pin = document.getElementById('blc-scanner-pin-input').value.trim();
            if (!pin) return;

            showMessage('blc-pin-message', 'Verifying...', 'loading');

            fetch(config.restUrl + 'scanner-auth', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pin: pin })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.token) {
                    scannerToken = data.token;
                    storeToken(data.token, data.expires);
                    showScannerUI();
                    hideMessage('blc-pin-message');
                } else {
                    var msg = data.message || 'Incorrect PIN. Please try again.';
                    showMessage('blc-pin-message', msg, 'error');
                }
            })
            .catch(function () {
                showMessage('blc-pin-message', 'Connection error. Please try again.', 'error');
            });
        });
    }

    function showScannerUI() {
        var pinSection = document.getElementById('blc-scanner-pin-section');
        var uiSection = document.getElementById('blc-scanner-ui-section');
        if (pinSection) pinSection.style.display = 'none';
        if (uiSection) uiSection.style.display = 'block';
        refreshStats();
    }

    // ========================================================================
    // Ticket Check-In
    // ========================================================================
    function checkInTicket(code) {
        code = code.trim().toUpperCase();
        if (!code) return;

        fetch(config.restUrl + 'scan-validate/' + encodeURIComponent(code), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-BLC-Scanner-Token': scannerToken
            }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            showScanResult(data);
            refreshStats();
        })
        .catch(function () {
            showScanResult({ status: 'error', message: 'Network error. Please try again.' });
        });
    }

    function formatCheckedInTime(dateStr) {
        if (!dateStr) return 'earlier';
        var d = new Date(dateStr.replace(' ', 'T'));
        if (isNaN(d.getTime())) return dateStr;
        var hours = d.getHours();
        var mins = d.getMinutes();
        var ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;
        var minStr = mins < 10 ? '0' + mins : '' + mins;
        return hours + ':' + minStr + ' ' + ampm;
    }

    function showScanResult(data) {
        var container = document.getElementById('blc-scan-result');
        var icon = document.getElementById('blc-scan-result-icon');
        var name = document.getElementById('blc-scan-result-name');
        var status = document.getElementById('blc-scan-result-status');

        if (!container) return;
        container.style.display = 'block';

        // Reset classes
        container.className = 'blc-scan-result';

        var hideDelay = 5000;

        if (data.code === 'invalid_ticket' || data.code === 'not_found') {
            container.classList.add('blc-scan-invalid');
            icon.textContent = '\u274C';
            name.textContent = 'INVALID TICKET';
            status.textContent = data.message || 'This ticket code is not valid.';
        } else if (data.code === 'invalid_token' || data.code === 'missing_token') {
            container.classList.add('blc-scan-invalid');
            icon.textContent = '\u274C';
            name.textContent = 'SESSION EXPIRED';
            status.textContent = 'Please refresh the page and re-enter the PIN.';
            hideDelay = 10000;
        } else if (data.status === 'already_checked_in') {
            container.classList.add('blc-scan-warning');
            icon.textContent = '\u26A0\uFE0F';
            name.textContent = data.attendee_name || 'Unknown';
            status.textContent = 'DUPLICATE — Already checked in at ' + formatCheckedInTime(data.checked_in_at);
            hideDelay = 8000;
        } else if (data.status === 'checked_in') {
            container.classList.add('blc-scan-success');
            icon.textContent = '\u2705';
            name.textContent = data.attendee_name || 'Guest';
            status.textContent = 'Welcome! Successfully checked in.';
        } else if (data.code === 'unpaid_ticket') {
            container.classList.add('blc-scan-invalid');
            icon.textContent = '\u274C';
            name.textContent = 'UNPAID TICKET';
            status.textContent = 'This ticket has not been paid for.';
        } else {
            container.classList.add('blc-scan-invalid');
            icon.textContent = '\u274C';
            name.textContent = 'ERROR';
            status.textContent = data.message || 'Unknown error.';
        }

        // Auto-hide (longer for warnings so volunteer notices)
        setTimeout(function () {
            container.style.display = 'none';
        }, hideDelay);
    }

    // ========================================================================
    // Camera Scanner
    // ========================================================================
    function initCameraControls() {
        var startBtn = document.getElementById('blc-scan-start-camera');
        var stopBtn = document.getElementById('blc-scan-stop-camera');

        if (!startBtn || !stopBtn) return;

        startBtn.addEventListener('click', function () {
            if (typeof Html5Qrcode === 'undefined') {
                alert('Camera library not loaded. Please use manual entry.');
                return;
            }

            startBtn.style.display = 'none';
            stopBtn.style.display = 'inline-block';

            scannerInstance = new Html5Qrcode('blc-scan-qr-reader');
            scannerInstance.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                function (decodedText) {
                    // Extract ticket code from URL or use raw text
                    var code = decodedText;
                    var match = decodedText.match(/(?:validate|scan-validate)\/([A-Za-z0-9]+)/);
                    if (match) code = match[1];

                    checkInTicket(code);

                    // Pause briefly to prevent duplicate scans
                    if (scannerInstance) {
                        scannerInstance.pause(true);
                        setTimeout(function () {
                            if (scannerInstance) {
                                try { scannerInstance.resume(); } catch (e) {}
                            }
                        }, 3000);
                    }
                },
                function () { /* ignore scan failures */ }
            ).catch(function (err) {
                alert('Could not start camera: ' + err);
                startBtn.style.display = 'inline-block';
                stopBtn.style.display = 'none';
            });
        });

        stopBtn.addEventListener('click', function () {
            if (scannerInstance) {
                scannerInstance.stop().then(function () {
                    scannerInstance = null;
                    document.getElementById('blc-scan-qr-reader').innerHTML = '';
                });
            }
            stopBtn.style.display = 'none';
            startBtn.style.display = 'inline-block';
        });
    }

    // ========================================================================
    // Manual Entry
    // ========================================================================
    function initManualEntry() {
        var btn = document.getElementById('blc-scan-manual-btn');
        var input = document.getElementById('blc-scan-manual-code');

        if (!btn || !input) return;

        btn.addEventListener('click', function () {
            checkInTicket(input.value);
            input.value = '';
        });

        input.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                btn.click();
            }
        });
    }

    // ========================================================================
    // Check-in Stats
    // ========================================================================
    function refreshStats() {
        fetch(config.restUrl + 'checkin-stats', {
            method: 'GET',
            headers: { 'X-BLC-Scanner-Token': scannerToken }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var el = document.getElementById('blc-scanner-checked-in');
            if (el && typeof data.checked_in !== 'undefined') {
                el.textContent = data.checked_in + ' / ' + data.total_tickets;
            }
        })
        .catch(function () { /* silent */ });
    }

    // ========================================================================
    // Helpers
    // ========================================================================
    function showMessage(id, text, type) {
        var el = document.getElementById(id);
        if (!el) return;
        el.textContent = text;
        el.className = 'blc-message blc-' + type;
        el.style.display = 'block';
    }

    function hideMessage(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.style.display = 'none';
    }

    // ========================================================================
    // Init
    // ========================================================================
    function init() {
        // Check for existing valid token
        scannerToken = getStoredToken();

        if (scannerToken) {
            showScannerUI();
        }

        initPinForm();
        initCameraControls();
        initManualEntry();

        // Refresh stats every 30 seconds when scanner is visible
        setInterval(function () {
            if (scannerToken) refreshStats();
        }, 30000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
