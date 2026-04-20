<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Rest;

defined( 'ABSPATH' ) || exit;

use Flavor\CardlinkPaymentGateway\Gateway\AbstractCardlinkGateway;
use Flavor\CardlinkPaymentGateway\Gateway\CardlinkGateway;
use Flavor\CardlinkPaymentGateway\Payment\ResponseHandler;
use WP_REST_Request;
use WP_REST_Response;

class PaymentConfirmationEndpoint {

    private ResponseHandler $response_handler;

    public function __construct( ResponseHandler $response_handler ) {
        $this->response_handler = $response_handler;
    }

    public function handle( WP_REST_Request $request ): WP_REST_Response {
        $post_data = $request->get_body_params();

        // Extract order ID.
        $order_id = $this->response_handler->extract_order_id( $post_data );
        $order    = wc_get_order( $order_id );

        if ( ! $order ) {
            return new WP_REST_Response( [ 'error' => 'Order not found.' ], 404 );
        }

        // Only process if order is not already completed/processing.
        if ( in_array( $order->get_status(), [ 'processing', 'completed' ], true ) ) {
            return new WP_REST_Response( [
                'status'   => 'already_processed',
                'order_id' => $order_id,
            ], 200 );
        }

        // Resolve the correct gateway for this order (IRIS or main Cardlink).
        $gateway = $this->resolve_gateway( $order->get_payment_method() );
        if ( ! $gateway ) {
            return new WP_REST_Response( [ 'error' => 'Gateway not initialized.' ], 500 );
        }

        $shared_secret = $gateway->get_shared_secret_key();
        $gateway_id    = $gateway->id;

        $order = $this->response_handler->handle(
            $post_data,
            $shared_secret,
            $gateway_id,
            true,  // $send_order_note
            true   // $is_background — skip cart/session ops, no browser context
        );

        $order->add_order_note(
            __( 'Payment received from Background confirmation service.', 'cardlink-payment-gateway' )
        );

        return new WP_REST_Response( [
            'status'   => $order->get_status(),
            'order_id' => $order->get_id(),
        ], 200 );
    }

    /**
     * Resolve the correct Cardlink gateway instance for the given payment method ID.
     *
     * Falls back to the main CardlinkGateway if the specific gateway cannot be found.
     */
    private function resolve_gateway( string $payment_method ): ?AbstractCardlinkGateway {
        $gateways = WC()->payment_gateways()->payment_gateways();

        if ( isset( $gateways[ $payment_method ] ) && $gateways[ $payment_method ] instanceof AbstractCardlinkGateway ) {
            return $gateways[ $payment_method ];
        }

        // Fallback to main Cardlink gateway.
        return CardlinkGateway::get_instance();
    }
}
