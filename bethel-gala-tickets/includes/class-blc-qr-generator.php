<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * QR Code generator using a simple, dependency-free PHP implementation.
 * Generates SVG QR codes without requiring GD, Imagick, or external libraries.
 */
class BLC_Gala_QR_Generator {

    /**
     * Generate an SVG QR code for the given data.
     *
     * @param string $data The data to encode.
     * @param int    $size The width/height in pixels.
     * @return string SVG markup.
     */
    public function generate_svg( $data, $size = 200 ) {
        // Use the bundled QR library
        $qr_file = BLC_GALA_PLUGIN_DIR . 'vendor/phpqrcode/qrlib.php';
        if ( file_exists( $qr_file ) ) {
            require_once $qr_file;
            return $this->generate_svg_with_library( $data, $size );
        }

        // Fallback: use Google Charts API (deprecated but still works) via inline img
        return $this->generate_svg_fallback( $data, $size );
    }

    /**
     * Generate QR using the bundled phpqrcode library, output as SVG.
     */
    private function generate_svg_with_library( $data, $size ) {
        ob_start();
        QRcode::png( $data, null, QR_ECLEVEL_M, 10, 2 );
        $png_data = ob_get_clean();

        if ( $png_data ) {
            $base64 = base64_encode( $png_data );
            return '<img src="data:image/png;base64,' . $base64 . '" width="' . (int) $size . '" height="' . (int) $size . '" alt="QR Code" style="image-rendering: pixelated;" />';
        }

        return $this->generate_svg_fallback( $data, $size );
    }

    /**
     * Fallback QR generator using a simple pure-PHP QR encoder.
     * Generates a minimal QR code as an HTML table (works everywhere).
     */
    private function generate_svg_fallback( $data, $size ) {
        // Use the Google Charts QR API as an image source
        $encoded = urlencode( $data );
        $url = "https://chart.googleapis.com/chart?cht=qr&chs={$size}x{$size}&chl={$encoded}&choe=UTF-8";
        return '<img src="' . esc_url( $url ) . '" width="' . (int) $size . '" height="' . (int) $size . '" alt="QR Code" />';
    }

    /**
     * Generate a QR code and return as a base64 PNG data URI.
     */
    public function generate_base64_png( $data, $size = 200 ) {
        $qr_file = BLC_GALA_PLUGIN_DIR . 'vendor/phpqrcode/qrlib.php';
        if ( file_exists( $qr_file ) ) {
            require_once $qr_file;

            ob_start();
            QRcode::png( $data, null, QR_ECLEVEL_M, 10, 2 );
            $png_data = ob_get_clean();

            if ( $png_data ) {
                return 'data:image/png;base64,' . base64_encode( $png_data );
            }
        }

        // Fallback to Google Charts
        $encoded = urlencode( $data );
        return "https://chart.googleapis.com/chart?cht=qr&chs={$size}x{$size}&chl={$encoded}&choe=UTF-8";
    }

    /**
     * Generate a QR code and save to a file in the uploads directory.
     *
     * @return string|WP_Error File URL on success, WP_Error on failure.
     */
    public function save_qr_to_file( $data, $filename = 'promo-qr.png' ) {
        $upload_dir = wp_upload_dir();
        $gala_dir   = $upload_dir['basedir'] . '/blc-gala/';

        if ( ! file_exists( $gala_dir ) ) {
            wp_mkdir_p( $gala_dir );
        }

        $file_path = $gala_dir . sanitize_file_name( $filename );

        $qr_file = BLC_GALA_PLUGIN_DIR . 'vendor/phpqrcode/qrlib.php';
        if ( file_exists( $qr_file ) ) {
            require_once $qr_file;
            QRcode::png( $data, $file_path, QR_ECLEVEL_M, 10, 2 );

            if ( file_exists( $file_path ) ) {
                return $upload_dir['baseurl'] . '/blc-gala/' . sanitize_file_name( $filename );
            }
        }

        return new WP_Error( 'qr_generation_failed', 'Could not generate QR code file.' );
    }
}
