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

})(jQuery);
