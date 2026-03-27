<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BLC_Gala_Email {

    /**
     * Send ticket confirmation email with QR code.
     *
     * @param object $order   Order row from the database.
     * @param array  $tickets Array of ticket data (ticket_code, etc.).
     */
    public function send_ticket_email( $order, $tickets ) {
        $qr_gen     = new BLC_Gala_QR_Generator();
        $event_name = get_option( 'blc_gala_event_name', 'Always on Mission Gala 2026' );
        $event_date = get_option( 'blc_gala_event_date', '' );
        $tagline    = get_option( 'blc_gala_event_tagline', 'Hosted by Bethel Life Center' );
        $accent       = get_option( 'blc_gala_accent_color', '#C9A84C' );
        $secondary    = get_option( 'blc_gala_secondary_color', '#1B2A4A' );
        $address_text = get_option( 'blc_gala_event_address', '' );

        $formatted_date = '';
        if ( $event_date ) {
            $formatted_date = date_i18n( 'l, F j, Y \a\t g:i A', strtotime( $event_date ) );
        }

        // Build ticket QR codes HTML
        $tickets_html = '';
        foreach ( $tickets as $ticket ) {
            $qr_data = $ticket['ticket_code'];
            $qr_img  = $qr_gen->generate_base64_png( $qr_data, 250 );
            $tickets_html .= '
            <div style="text-align: center; margin: 20px 0; padding: 20px; border: 2px dashed ' . esc_attr( $accent ) . '; border-radius: 10px;">
                <p style="font-size: 14px; color: #666; margin: 0 0 10px;">Ticket Code: <strong>' . esc_html( $ticket['ticket_code'] ) . '</strong></p>
                <img src="' . esc_url( $qr_img ) . '" width="250" height="250" alt="Ticket QR Code" style="display: block; margin: 0 auto;" />
                <p style="font-size: 12px; color: #999; margin: 10px 0 0;">Present this QR code at the door</p>
            </div>';
        }

        $subject = 'Your Ticket to ' . $event_name;

        $body = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin: 0; padding: 0; background-color: #f4f4f4; font-family: Georgia, serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">

    <!-- Header -->
    <tr>
        <td style="background-color: ' . esc_attr( $secondary ) . '; padding: 30px; text-align: center;">
            <h1 style="color: ' . esc_attr( $accent ) . '; margin: 0; font-size: 28px;">' . esc_html( $event_name ) . '</h1>
            <p style="color: #ffffff; margin: 10px 0 0; font-size: 16px;">' . esc_html( $tagline ) . '</p>
        </td>
    </tr>

    <!-- Body -->
    <tr>
        <td style="padding: 30px;">
            <p style="font-size: 18px; color: #333;">Dear ' . esc_html( $order->buyer_name ) . ',</p>
            <p style="font-size: 16px; color: #555; line-height: 1.6;">
                Thank you for purchasing your ticket to the <strong>' . esc_html( $event_name ) . '</strong>!
                We are excited to have you join us for this special evening.
            </p>

            ' . ( $formatted_date || $address_text ? '<div style="font-size: 16px; color: #333; background: #f9f6ee; padding: 15px; border-radius: 6px; text-align: center;">'
                . ( $formatted_date ? '<p style="margin: 0 0 5px;"><strong>Date:</strong> ' . esc_html( $formatted_date ) . '</p>' : '' )
                . ( $address_text ? '<p style="margin: 0;"><strong>Location:</strong> ' . esc_html( str_replace( "\n", ', ', $address_text ) ) . '</p>' : '' )
            . '</div>' : '' ) . '

            <h2 style="color: ' . esc_attr( $secondary ) . '; border-bottom: 2px solid ' . esc_attr( $accent ) . '; padding-bottom: 10px;">Your Ticket(s)</h2>
            <p style="font-size: 14px; color: #666;">Please show the QR code below when you arrive at the event. You may display it on your phone or print this email.</p>

            ' . $tickets_html . '

            <h2 style="color: ' . esc_attr( $secondary ) . '; border-bottom: 2px solid ' . esc_attr( $accent ) . '; padding-bottom: 10px;">Order Details</h2>
            <table width="100%" style="font-size: 14px; color: #555;">
                <tr><td style="padding: 5px 0;"><strong>Name:</strong></td><td>' . esc_html( $order->buyer_name ) . '</td></tr>
                <tr><td style="padding: 5px 0;"><strong>Email:</strong></td><td>' . esc_html( $order->buyer_email ) . '</td></tr>
                <tr><td style="padding: 5px 0;"><strong>Quantity:</strong></td><td>' . esc_html( $order->quantity ) . ' ticket(s)</td></tr>
                <tr><td style="padding: 5px 0;"><strong>Amount Paid:</strong></td><td>$' . esc_html( number_format( $order->amount_paid, 2 ) ) . '</td></tr>
            </table>
        </td>
    </tr>

    <!-- Footer -->
    <tr>
        <td style="background-color: ' . esc_attr( $secondary ) . '; padding: 20px; text-align: center;">
            <p style="color: #ccc; font-size: 12px; margin: 0;">Bethel Life Center &mdash; ' . esc_html( $event_name ) . '</p>
            <p style="color: #999; font-size: 11px; margin: 5px 0 0;">This is an automated confirmation. Please do not reply to this email.</p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>';

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Bethel Life Center <noreply@bethellifecenter.org>',
        );

        return wp_mail( $order->buyer_email, $subject, $body, $headers );
    }

    /**
     * Send donation thank-you email.
     */
    public function send_donation_email( $order ) {
        $event_name = get_option( 'blc_gala_event_name', 'Always on Mission Gala 2026' );
        $accent     = get_option( 'blc_gala_accent_color', '#C9A84C' );
        $secondary  = get_option( 'blc_gala_secondary_color', '#1B2A4A' );

        $subject = 'Thank You for Your Donation — ' . $event_name;

        $body = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin: 0; padding: 0; background-color: #f4f4f4; font-family: Georgia, serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">

    <tr>
        <td style="background-color: ' . esc_attr( $secondary ) . '; padding: 30px; text-align: center;">
            <h1 style="color: ' . esc_attr( $accent ) . '; margin: 0; font-size: 28px;">' . esc_html( $event_name ) . '</h1>
            <p style="color: #ffffff; margin: 10px 0 0; font-size: 16px;">Thank You for Your Generosity</p>
        </td>
    </tr>

    <tr>
        <td style="padding: 30px;">
            <p style="font-size: 18px; color: #333;">Dear ' . esc_html( $order->buyer_name ) . ',</p>
            <p style="font-size: 16px; color: #555; line-height: 1.6;">
                Thank you for your generous donation of <strong>$' . esc_html( number_format( $order->amount_paid, 2 ) ) . '</strong>
                in support of the ' . esc_html( $event_name ) . ' and our missions work. Your contribution makes a real difference
                in the lives of those we serve.
            </p>
            <p style="font-size: 16px; color: #555; line-height: 1.6;">
                God bless you for your generous heart!
            </p>
        </td>
    </tr>

    <tr>
        <td style="background-color: ' . esc_attr( $secondary ) . '; padding: 20px; text-align: center;">
            <p style="color: #ccc; font-size: 12px; margin: 0;">Bethel Life Center</p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>';

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Bethel Life Center <noreply@bethellifecenter.org>',
        );

        return wp_mail( $order->buyer_email, $subject, $body, $headers );
    }
}
