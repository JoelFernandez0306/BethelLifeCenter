/**
 * Gala Tickets — Admin JavaScript
 */
(function($) {
    'use strict';

    // Download QR code as PNG
    $('#blc-download-qr').on('click', function() {
        var svgOrImg = $('#blc-promo-qr img, #blc-promo-qr svg').first();
        if (!svgOrImg.length) {
            alert('No QR code found. Please save settings first.');
            return;
        }

        // If it's an img tag, open it in a new tab for download
        if (svgOrImg.is('img')) {
            var src = svgOrImg.attr('src');
            var link = document.createElement('a');
            link.href = src;
            link.download = 'gala-promo-qr.png';
            link.target = '_blank';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    });

    // Test PayPal connection
    $('#blc-test-paypal').on('click', function() {
        var btn = $(this);
        var result = $('#blc-test-paypal-result');
        btn.prop('disabled', true);
        result.html('<em>Testing...</em>').css('color', '#666');

        $.ajax({
            url: (window.blcGalaAdmin ? blcGalaAdmin.restUrl : '/wp-json/blc-gala/v1/') + 'test-paypal',
            method: 'POST',
            headers: { 'X-WP-Nonce': window.blcGalaAdmin ? blcGalaAdmin.nonce : '' },
            contentType: 'application/json',
            data: '{}',
            success: function(data) {
                if (data.success) {
                    result.html('<strong style="color: #0a7c00;">' + data.message + '</strong>');
                } else {
                    result.html('<strong style="color: #d63638;">' + data.message + '</strong>');
                }
            },
            error: function() {
                result.html('<strong style="color: #d63638;">Could not reach the server. Please try again.</strong>');
            },
            complete: function() {
                btn.prop('disabled', false);
            }
        });
    });

    // Download a Quick Pay QR code as a PNG
    $(document).on('click', '.blc-qp-download', function() {
        var card = $(this).closest('.blc-qp-card');
        var img = card.find('.blc-qp-card-qr img').first();

        if (!img.length) {
            alert('No QR code found for this item.');
            return;
        }

        var name = (card.data('label') || 'quick-pay').toString()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');

        var link = document.createElement('a');
        link.href = img.attr('src');
        link.download = name + '-qr.png';
        link.target = '_blank';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });

    // Print a single Quick Pay QR code on its own sheet
    $(document).on('click', '.blc-qp-print', function() {
        var card = $(this).closest('.blc-qp-card');
        var img = card.find('.blc-qp-card-qr img').first();

        if (!img.length) {
            alert('No QR code found for this item.');
            return;
        }

        var label = card.data('label') || '';
        var amount = card.data('amount') || '';

        var win = window.open('', '_blank', 'width=700,height=800');
        if (!win) {
            alert('Please allow pop-ups for this site to print the QR code.');
            return;
        }

        var esc = function(s) {
            return $('<div>').text(s == null ? '' : String(s)).html();
        };

        win.document.write(
            '<!doctype html><html><head><title>' + esc(label) + '</title>' +
            '<style>' +
            'body{font-family:Georgia,serif;text-align:center;padding:50px 20px;margin:0;}' +
            'h1{font-size:34px;margin:0 0 6px;}' +
            '.amt{font-size:64px;font-weight:700;margin:0 0 24px;}' +
            'img{width:340px;height:340px;image-rendering:pixelated;}' +
            '.hint{font-size:19px;color:#444;margin-top:24px;}' +
            '@media print{body{padding:30px 20px;}}' +
            '</style></head><body>' +
            '<h1>' + esc(label) + '</h1>' +
            '<div class="amt">' + esc(amount) + '</div>' +
            '<img src="' + img.attr('src') + '" alt="QR Code" />' +
            '<div class="hint">Scan with your phone camera to pay</div>' +
            '</body></html>'
        );
        win.document.close();

        // Wait for the QR image to decode before opening the print dialog,
        // otherwise the sheet can print blank.
        win.onload = function() {
            win.focus();
            win.print();
        };
    });

})(jQuery);
