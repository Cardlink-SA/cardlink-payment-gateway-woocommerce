<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Rest;

defined( 'ABSPATH' ) || exit;

use Flavor\CardlinkPaymentGateway\Exception\DigestMismatchException;
use Flavor\CardlinkPaymentGateway\Gateway\CardlinkGateway;
use Flavor\CardlinkPaymentGateway\Payment\DigestService;
use Flavor\CardlinkPaymentGateway\Token\TokenManager;
use WP_REST_Request;

class TokenizerEndpoint {

    private DigestService $digest_service;
    private TokenManager $token_manager;

    public function __construct(
        DigestService $digest_service,
        TokenManager $token_manager
    ) {
        $this->digest_service = $digest_service;
        $this->token_manager  = $token_manager;
    }

    public function handle( WP_REST_Request $request ): void {
        $post_data = $request->get_body_params();
        $result    = $request->get_param( 'result' );

        $redirect_url = wc_get_endpoint_url( 'payment-methods', '', wc_get_page_permalink( 'myaccount' ) );

        if ( $result !== 'success' ) {
            $redirect_url = add_query_arg( 'tok_message', urlencode( __( 'Card storage was cancelled.', 'cardlink-payment-gateway' ) ), $redirect_url );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        // Get shared secret from gateway.
        $gateway = CardlinkGateway::get_instance();
        if ( ! $gateway ) {
            wp_safe_redirect( $redirect_url );
            exit;
        }

        $shared_secret = $gateway->get_shared_secret_key();

        // Verify digest.
        try {
            $this->digest_service->verify_response( $post_data, $shared_secret );
        } catch ( DigestMismatchException $e ) {
            $redirect_url = add_query_arg( 'tok_message', urlencode( __( 'Card verification failed.', 'cardlink-payment-gateway' ) ), $redirect_url );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        // Extract user ID from orderid field (format: {user_id}at{timestamp}).
        $orderid = sanitize_text_field( $post_data['orderid'] ?? '' );
        $user_id = 0;
        if ( preg_match( '/^(.*?)at/', $orderid, $matches ) ) {
            $user_id = (int) $matches[1];
        }

        if ( $user_id <= 0 ) {
            $user_id = get_current_user_id();
        }

        // Save token.
        $ext_token     = sanitize_text_field( $post_data['extToken'] ?? '' );
        $ext_token_pan = sanitize_text_field( $post_data['extTokenPanEnd'] ?? '' );
        $ext_token_exp = sanitize_text_field( $post_data['extTokenExp'] ?? '' );
        $pay_method    = sanitize_text_field( $post_data['payMethod'] ?? '' );

        if ( ! empty( $ext_token ) && ! empty( $ext_token_pan ) && ! empty( $ext_token_exp ) ) {
            $exp_year  = substr( $ext_token_exp, 0, 4 );
            $exp_month = substr( $ext_token_exp, 4, 2 );

            $this->token_manager->save_token_from_response(
                $user_id,
                $gateway->id,
                $ext_token,
                $ext_token_pan,
                $exp_year,
                $exp_month,
                $pay_method
            );

            $redirect_url = add_query_arg( 'tok_message', urlencode( __( 'Card stored successfully.', 'cardlink-payment-gateway' ) ), $redirect_url );
        }

        wp_safe_redirect( $redirect_url );
        exit;
    }
}
