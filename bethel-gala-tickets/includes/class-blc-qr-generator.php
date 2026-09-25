<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * QR Code generator backed by the bundled phpqrcode library.
 */
class BLC_Gala_QR_Generator {

    /**
     * Load the bundled phpqrcode library, with its disk cache turned off.
     *
     * The library ships without its cache/ directory, and writing inside a
     * plugin folder is not reliable across hosts, so it would otherwise emit a
     * burst of mkdir/file_put_contents warnings for every QR code it builds.
     * Encoding is fast enough without the cache.
     *
     * @return bool True when the library is available.
     */
    private static function load_library() {
        $qr_file = BLC_GALA_PLUGIN_DIR . 'vendor/phpqrcode/qrlib.php';

        if ( ! file_exists( $qr_file ) ) {
            return false;
        }

        if ( ! defined( 'QR_CACHEABLE' ) ) {
            define( 'QR_CACHEABLE', false );
        }
        if ( ! defined( 'QR_LOG_DIR' ) ) {
            define( 'QR_LOG_DIR', false );
        }

        require_once $qr_file;

        return true;
    }

    /**
     * Render a QR code and return the raw PNG bytes.
     *
     * phpqrcode writes the image straight to output, so it has to be captured
     * from an output buffer. That buffer will also swallow anything else PHP
     * prints — and this library is old enough to raise deprecation notices on
     * PHP 8. On a server with display_errors on, those notices land inside the
     * captured bytes and produce a corrupt image, so error display is muted
     * for the duration of the call.
     *
     * @param string $data Payload to encode.
     * @return string PNG bytes, or an empty string on failure.
     */
    private static function capture_png( $data ) {
        $previous = ini_get( 'display_errors' );
        // @codingStandardsIgnoreStart - required so notices cannot corrupt the image
        ini_set( 'display_errors', '0' );

        ob_start();
        QRcode::png( $data, null, QR_ECLEVEL_M, 10, 2 );
        $png = ob_get_clean();

        ini_set( 'display_errors', $previous );
        // @codingStandardsIgnoreEnd

        // A valid PNG always starts with this signature. Anything else means
        // stray output made it through, so treat it as a failure.
        if ( ! is_string( $png ) || substr( $png, 0, 8 ) !== "\x89PNG\r\n\x1a\n" ) {
            return '';
        }

        return $png;
    }

    /**
     * Generate a QR code as an <img> tag for the given data.
     *
     * @param string $data The data to encode.
     * @param int    $size The width/height in pixels.
     * @return string HTML markup.
     */
    public function generate_svg( $data, $size = 200 ) {
        if ( self::load_library() ) {
            $png = self::capture_png( $data );

            if ( $png !== '' ) {
                return '<img src="data:image/png;base64,' . base64_encode( $png ) . '"'
                     . ' width="' . (int) $size . '" height="' . (int) $size . '"'
                     . ' alt="QR Code" style="image-rendering: pixelated;" />';
            }
        }

        return $this->generate_svg_fallback( $data, $size );
    }

    /**
     * Fallback when the bundled library is missing or fails.
     */
    private function generate_svg_fallback( $data, $size ) {
        $url = 'https://api.qrserver.com/v1/create-qr-code/?size=' . (int) $size . 'x' . (int) $size
             . '&data=' . rawurlencode( $data );

        return '<img src="' . esc_url( $url ) . '" width="' . (int) $size . '" height="' . (int) $size . '" alt="QR Code" />';
    }

    /**
     * Generate a QR code and return it as a base64 PNG data URI.
     */
    public function generate_base64_png( $data, $size = 200 ) {
        if ( self::load_library() ) {
            $png = self::capture_png( $data );

            if ( $png !== '' ) {
                return 'data:image/png;base64,' . base64_encode( $png );
            }
        }

        return 'https://api.qrserver.com/v1/create-qr-code/?size=' . (int) $size . 'x' . (int) $size
             . '&data=' . rawurlencode( $data );
    }

    /**
     * Generate a QR code and save it into the uploads directory.
     *
     * Email clients block base64 data URIs, so ticket QR codes are served as
     * real files instead.
     *
     * @return string|WP_Error File URL on success, WP_Error on failure.
     */
    public function save_qr_to_file( $data, $filename = 'promo-qr.png' ) {
        if ( ! self::load_library() ) {
            return new WP_Error( 'qr_library_missing', 'QR code library is not available.' );
        }

        $upload_dir = wp_upload_dir();

        if ( ! empty( $upload_dir['error'] ) ) {
            return new WP_Error( 'qr_upload_dir', 'Uploads directory is not writable.' );
        }

        $gala_dir = trailingslashit( $upload_dir['basedir'] ) . 'blc-gala/';

        if ( ! file_exists( $gala_dir ) && ! wp_mkdir_p( $gala_dir ) ) {
            return new WP_Error( 'qr_mkdir_failed', 'Could not create the QR code directory.' );
        }

        $safe_name = sanitize_file_name( $filename );
        $file_path = $gala_dir . $safe_name;

        // Same reasoning as capture_png(): keep library notices out of the page.
        $previous = ini_get( 'display_errors' );
        // @codingStandardsIgnoreStart
        ini_set( 'display_errors', '0' );
        QRcode::png( $data, $file_path, QR_ECLEVEL_M, 10, 2 );
        ini_set( 'display_errors', $previous );
        // @codingStandardsIgnoreEnd

        if ( ! file_exists( $file_path ) || filesize( $file_path ) === 0 ) {
            return new WP_Error( 'qr_generation_failed', 'Could not generate QR code file.' );
        }

        return trailingslashit( $upload_dir['baseurl'] ) . 'blc-gala/' . $safe_name;
    }
}
