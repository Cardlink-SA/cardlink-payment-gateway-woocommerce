<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Rest;

defined( 'ABSPATH' ) || exit;

class RestController {

    private PaymentConfirmationEndpoint $payment_endpoint;
    private TokenizerEndpoint $tokenizer_endpoint;

    public function __construct(
        PaymentConfirmationEndpoint $payment_endpoint,
        TokenizerEndpoint $tokenizer_endpoint
    ) {
        $this->payment_endpoint   = $payment_endpoint;
        $this->tokenizer_endpoint = $tokenizer_endpoint;
    }

    public function register_routes(): void {
        // Payment confirmation from Cardlink background service - authenticated via digest signature.
        register_rest_route( 'wc-cardlink/v1', '/payment', [
            'methods'             => 'POST',
            'callback'            => [ $this->payment_endpoint, 'handle' ],
            'permission_callback' => '__return_true', // Server-to-server callback, verified via digest.
        ] );

        // Tokenizer callback from Cardlink - authenticated via digest signature.
        register_rest_route( 'wc-cardlink/v1', '/tokenizer', [
            'methods'             => 'POST',
            'callback'            => [ $this->tokenizer_endpoint, 'handle' ],
            'permission_callback' => '__return_true', // Server-to-server callback, verified via digest.
        ] );
    }
}
