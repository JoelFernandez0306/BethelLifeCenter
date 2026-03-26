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

})(jQuery);
