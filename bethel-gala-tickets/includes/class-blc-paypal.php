<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BLC_Gala_PayPal {

    private $client_id;
    private $secret;
    private $base_url;

    public function __construct() {
        $this->client_id = trim( get_option( 'blc_gala_paypal_client_id', '' ) );
        $this->secret    = trim( get_option( 'blc_gala_paypal_secret', '' ) );
        $sandbox         = get_option( 'blc_gala_paypal_sandbox', 1 );
        $this->base_url  = $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    /**
     * Get the PayPal JavaScript SDK URL for the frontend.
     */
    public function get_sdk_url() {
        $client_id = trim( $this->client_id );
        $intent    = 'capture';
        $currency  = 'USD';
        return "https://www.paypal.com/sdk/js?client-id={$client_id}&intent={$intent}&currency={$currency}&disable-funding=credit";
    }

    /**
     * Get an OAuth2 access token from PayPal.
     */
    private function get_access_token() {
        $response = wp_remote_post( $this->base_url . '/v1/oauth2/token', array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $this->client_id . ':' . $this->secret ),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body'    => 'grant_type=client_credentials',
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['access_token'] ) ) {
            return new WP_Error( 'paypal_auth_error', 'Failed to get PayPal access token.' );
        }

        return $body['access_token'];
    }

    /**
     * Create a PayPal order for ticket purchase.
     *
     * @param string $order_uuid Our internal order UUID.
     * @param float  $amount     Total amount in USD.
     * @param string $description Description for the line item.
     * @return array|WP_Error PayPal order data or error.
     */
    public function create_order( $order_uuid, $amount, $description = 'Gala Ticket' ) {
        $access_token = $this->get_access_token();
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        $payload = array(
            'intent'         => 'CAPTURE',
            'purchase_units' => array(
                array(
                    'reference_id' => $order_uuid,
                    'description'  => $description,
                    'amount'       => array(
                        'currency_code' => 'USD',
                        'value'         => number_format( $amount, 2, '.', '' ),
                    ),
                ),
            ),
        );

        $response = wp_remote_post( $this->base_url . '/v2/checkout/orders', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $code = wp_remote_retrieve_response_code( $response );

        if ( $code >= 400 || empty( $body['id'] ) ) {
            $msg = isset( $body['message'] ) ? $body['message'] : 'Failed to create PayPal order.';
            return new WP_Error( 'paypal_order_error', $msg );
        }

        return $body;
    }

    /**
     * Capture a PayPal order after buyer approval.
     *
     * @param string $paypal_order_id PayPal order ID.
     * @return array|WP_Error Capture result or error.
     */
    public function capture_order( $paypal_order_id ) {
        $access_token = $this->get_access_token();
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        $response = wp_remote_post( $this->base_url . '/v2/checkout/orders/' . $paypal_order_id . '/capture', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ),
            'body'    => '{}',
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $code = wp_remote_retrieve_response_code( $response );

        if ( $code >= 400 || empty( $body['status'] ) ) {
            $msg = isset( $body['message'] ) ? $body['message'] : 'Failed to capture PayPal payment.';
            return new WP_Error( 'paypal_capture_error', $msg );
        }

        // Extract capture ID
        $capture_id = '';
        if ( ! empty( $body['purchase_units'][0]['payments']['captures'][0]['id'] ) ) {
            $capture_id = $body['purchase_units'][0]['payments']['captures'][0]['id'];
        }

        return array(
            'status'          => $body['status'],
            'paypal_order_id' => $paypal_order_id,
            'capture_id'      => $capture_id,
            'payer'           => $this->extract_payer_details( $body ),
            'raw'             => $body,
        );
    }

    /**
     * Pull the payer's name and card details out of a capture response.
     *
     * PayPal only returns card digits when the buyer paid by card as a guest.
     * Paying from a PayPal balance means there is no card involved, so last4
     * and brand come back empty and only the account name is available.
     *
     * @param array $body Decoded capture response.
     * @return array {payer_name, payer_email, card_last4, card_brand}
     */
    public function extract_payer_details( $body ) {
        $details = array(
            'payer_name'  => '',
            'payer_email' => '',
            'card_last4'  => '',
            'card_brand'  => '',
        );

        $source = isset( $body['payment_source'] ) ? $body['payment_source'] : array();

        if ( ! empty( $source['card'] ) ) {
            $card = $source['card'];
            $details['card_last4'] = isset( $card['last_digits'] ) ? $card['last_digits'] : '';
            $details['card_brand'] = isset( $card['brand'] ) ? $card['brand'] : '';
            if ( ! empty( $card['name'] ) ) {
                $details['payer_name'] = $card['name'];
            }
        }

        if ( ! empty( $source['paypal'] ) ) {
            $pp = $source['paypal'];
            if ( empty( $details['payer_name'] ) && ! empty( $pp['name'] ) ) {
                $details['payer_name'] = self::join_name( $pp['name'] );
            }
            if ( ! empty( $pp['email_address'] ) ) {
                $details['payer_email'] = $pp['email_address'];
            }
        }

        // Fall back to the top-level payer object when payment_source is sparse.
        if ( ! empty( $body['payer'] ) ) {
            $payer = $body['payer'];
            if ( empty( $details['payer_name'] ) && ! empty( $payer['name'] ) ) {
                $details['payer_name'] = self::join_name( $payer['name'] );
            }
            if ( empty( $details['payer_email'] ) && ! empty( $payer['email_address'] ) ) {
                $details['payer_email'] = $payer['email_address'];
            }
        }

        return $details;
    }

    /**
     * Flatten PayPal's {given_name, surname} pair into one display name.
     */
    private static function join_name( $name ) {
        $given   = isset( $name['given_name'] ) ? $name['given_name'] : '';
        $surname = isset( $name['surname'] ) ? $name['surname'] : '';
        return trim( $given . ' ' . $surname );
    }

    /**
     * Verify that PayPal credentials are configured.
     */
    public function is_configured() {
        return ! empty( $this->client_id ) && ! empty( $this->secret );
    }
}
