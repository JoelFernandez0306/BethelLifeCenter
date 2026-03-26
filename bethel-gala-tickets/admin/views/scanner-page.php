<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap blc-gala-scanner-wrap">
    <h1>Door Scanner</h1>

    <?php $stats = BLC_Gala_Scanner::get_stats(); ?>
    <div class="blc-scanner-stats" style="margin: 15px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
        <strong>Check-in Stats:</strong>
        <?php echo esc_html( $stats['checked_in'] ); ?> checked in /
        <?php echo esc_html( $stats['total_tickets'] ); ?> total tickets sold
        (<?php echo esc_html( $stats['remaining'] ); ?> remaining to arrive)
    </div>

    <div id="blc-scanner-container" style="max-width: 500px;">
        <div style="margin-bottom: 15px;">
            <button id="blc-start-camera" class="button button-primary button-large">Start Camera Scanner</button>
            <button id="blc-stop-camera" class="button button-large" style="display:none;">Stop Camera</button>
        </div>

        <div id="blc-qr-reader" style="width: 100%; margin-bottom: 15px;"></div>

        <div style="margin-bottom: 15px;">
            <label for="blc-manual-code"><strong>Or enter ticket code manually:</strong></label>
            <div style="display: flex; gap: 10px; margin-top: 5px;">
                <input type="text" id="blc-manual-code" class="regular-text" placeholder="Enter ticket code" style="flex: 1; text-transform: uppercase;" />
                <button id="blc-manual-checkin" class="button button-primary">Check In</button>
            </div>
        </div>

        <div id="blc-scan-result" style="display: none; padding: 20px; border-radius: 6px; text-align: center; margin-top: 15px;">
            <div id="blc-scan-icon" style="font-size: 48px; margin-bottom: 10px;"></div>
            <div id="blc-scan-name" style="font-size: 24px; font-weight: bold; margin-bottom: 5px;"></div>
            <div id="blc-scan-status" style="font-size: 16px;"></div>
        </div>
    </div>
</div>

<script>
(function() {
    var restUrl = '<?php echo esc_js( rest_url( 'blc-gala/v1/' ) ); ?>';
    var nonce = '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>';
    var scanner = null;

    function checkInTicket(code) {
        code = code.trim().toUpperCase();
        if (!code) return;

        fetch(restUrl + 'validate/' + encodeURIComponent(code), {
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            showResult(data);
        })
        .catch(function() {
            showResult({ status: 'error', message: 'Network error. Please try again.' });
        });
    }

    function showResult(data) {
        var container = document.getElementById('blc-scan-result');
        var icon = document.getElementById('blc-scan-icon');
        var name = document.getElementById('blc-scan-name');
        var status = document.getElementById('blc-scan-status');

        container.style.display = 'block';

        if (data.code === 'invalid_ticket' || data.code === 'not_found') {
            container.style.background = '#f8d7da';
            container.style.borderColor = '#f5c6cb';
            icon.textContent = '\u274C';
            name.textContent = 'INVALID TICKET';
            status.textContent = data.message || 'This ticket code is not valid.';
        } else if (data.status === 'already_checked_in') {
            container.style.background = '#fff3cd';
            container.style.borderColor = '#ffc107';
            icon.textContent = '\u26A0\uFE0F';
            name.textContent = data.attendee_name || 'Unknown';
            status.textContent = 'Already checked in at ' + (data.checked_in_at || 'earlier');
        } else if (data.status === 'checked_in') {
            container.style.background = '#d4edda';
            container.style.borderColor = '#c3e6cb';
            icon.textContent = '\u2705';
            name.textContent = data.attendee_name || 'Guest';
            status.textContent = 'Welcome! Successfully checked in.';
        } else if (data.code === 'unpaid_ticket') {
            container.style.background = '#f8d7da';
            container.style.borderColor = '#f5c6cb';
            icon.textContent = '\u274C';
            name.textContent = 'UNPAID';
            status.textContent = 'This ticket has not been paid for.';
        } else {
            container.style.background = '#f8d7da';
            icon.textContent = '\u274C';
            name.textContent = 'ERROR';
            status.textContent = data.message || 'Unknown error.';
        }

        // Auto-hide after 5 seconds
        setTimeout(function() {
            container.style.display = 'none';
        }, 5000);
    }

    // Camera scanner using html5-qrcode
    document.getElementById('blc-start-camera').addEventListener('click', function() {
        if (typeof Html5Qrcode === 'undefined') {
            alert('Camera scanner library not loaded. Please use manual entry.');
            return;
        }

        this.style.display = 'none';
        document.getElementById('blc-stop-camera').style.display = 'inline-block';

        scanner = new Html5Qrcode('blc-qr-reader');
        scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            function(decodedText) {
                // Extract ticket code from URL or use raw text
                var code = decodedText;
                var match = decodedText.match(/validate\/([A-Za-z0-9]+)/);
                if (match) code = match[1];

                checkInTicket(code);

                // Pause scanning briefly to prevent duplicate scans
                if (scanner) {
                    scanner.pause(true);
                    setTimeout(function() {
                        if (scanner) scanner.resume();
                    }, 3000);
                }
            },
            function() { /* ignore scan failures */ }
        ).catch(function(err) {
            alert('Could not start camera: ' + err);
            document.getElementById('blc-start-camera').style.display = 'inline-block';
            document.getElementById('blc-stop-camera').style.display = 'none';
        });
    });

    document.getElementById('blc-stop-camera').addEventListener('click', function() {
        if (scanner) {
            scanner.stop().then(function() {
                scanner = null;
                document.getElementById('blc-qr-reader').innerHTML = '';
            });
        }
        this.style.display = 'none';
        document.getElementById('blc-start-camera').style.display = 'inline-block';
    });

    // Manual entry
    document.getElementById('blc-manual-checkin').addEventListener('click', function() {
        var code = document.getElementById('blc-manual-code').value;
        checkInTicket(code);
        document.getElementById('blc-manual-code').value = '';
    });

    document.getElementById('blc-manual-code').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('blc-manual-checkin').click();
        }
    });
})();
</script>
