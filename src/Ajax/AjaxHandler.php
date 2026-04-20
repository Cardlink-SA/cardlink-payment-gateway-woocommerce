<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Ajax;

defined( 'ABSPATH' ) || exit;

use Flavor\CardlinkPaymentGateway\Support\OrderHelper;
use Flavor\CardlinkPaymentGateway\Token\TokenFormRenderer;
use Flavor\CardlinkPaymentGateway\Token\TokenManager;
use WC_Order;
use WC_Payment_Tokens;

class AjaxHandler {

    private TokenManager $token_manager;
    private OrderHelper $order_helper;
    private TokenFormRenderer $token_form_renderer;

    public function __construct(
        TokenManager $token_manager,
        OrderHelper $order_helper,
        TokenFormRenderer $token_form_renderer
    ) {
        $this->token_manager       = $token_manager;
        $this->order_helper        = $order_helper;
        $this->token_form_renderer = $token_form_renderer;
    }

    public function register(): void {
        add_action( 'wp_ajax_cardlink_delete_token', [ $this, 'handle_delete_token' ] );
        add_action( 'wp_ajax_cardlink_set_redirection_status', [ $this, 'handle_set_redirection_status' ] );
        add_action( 'wp_ajax_nopriv_cardlink_set_redirection_status', [ $this, 'handle_set_redirection_status' ] );
        add_action( 'wp_ajax_cardlink_check_order_status', [ $this, 'handle_check_order_status' ] );
        add_action( 'wp_ajax_nopriv_cardlink_check_order_status', [ $this, 'handle_check_order_status' ] );
    }

    /**
     * Handle token deletion via AJAX.
     */
    public function handle_delete_token(): void {
        check_ajax_referer( 'cardlink_ajax_nonce', 'security' );

        if ( ! is_user_logged_in() ) {
            $this->respond( 'error', __( 'You must be logged in.', 'cardlink-payment-gateway' ) );
        }

        $card_id = sanitize_text_field( wp_unslash( $_POST['card_id'] ?? '' ) );

        if ( empty( $card_id ) ) {
            $this->respond( 'error', __( 'Invalid card ID.', 'cardlink-payment-gateway' ) );
        }

        // Remove "card-" prefix to get token ID.
        $token_id = (int) substr( $card_id, 5 );

        // Verify the token belongs to the current user.
        $token = WC_Payment_Tokens::get( $token_id );
        if ( ! $token || $token->get_user_id() !== get_current_user_id() ) {
            $this->respond( 'error', __( 'You do not have permission to delete this card.', 'cardlink-payment-gateway' ) );
        }

        $this->token_manager->delete_token( $token_id );

        // Return updated payment cards HTML.
        $user_id    = get_current_user_id();
        $gateway_id = 'cardlink_payment_gateway_woocommerce';
        $html       = $this->token_form_renderer->render( $gateway_id, $user_id );

        $this->respond( 'success', $html );
    }

    /**
     * Mark order as redirected for payment (iframe mode).
     */
    public function handle_set_redirection_status(): void {
        check_ajax_referer( 'cardlink_ajax_nonce', 'security' );

        $order_id = absint( wp_unslash( $_POST['order_id'] ?? 0 ) );

        if ( $order_id <= 0 ) {
            $this->respond( 'error', __( 'Invalid order ID.', 'cardlink-payment-gateway' ) );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            $this->respond( 'error', __( 'Order not found.', 'cardlink-payment-gateway' ) );
        }

        if ( ! $this->can_access_order( $order ) ) {
            $this->respond( 'error', __( 'Unauthorized.', 'cardlink-payment-gateway' ) );
        }

        $this->order_helper->add_meta( $order_id, 'redirected_for_payment', 'true' );
        $this->respond( 'success', '' );
    }

    /**
     * Check order status for iframe polling.
     */
    public function handle_check_order_status(): void {
        check_ajax_referer( 'cardlink_ajax_nonce', 'security' );

        $order_id = absint( wp_unslash( $_POST['order_id'] ?? 0 ) );

        if ( $order_id <= 0 ) {
            $this->respond( 'error', __( 'Invalid order ID.', 'cardlink-payment-gateway' ) );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            $this->respond( 'error', __( 'Order not found.', 'cardlink-payment-gateway' ) );
        }

        if ( ! $this->can_access_order( $order ) ) {
            $this->respond( 'error', __( 'Unauthorized.', 'cardlink-payment-gateway' ) );
        }

        $redirected = $this->order_helper->get_meta( $order, 'redirected_for_payment' );

        if ( $redirected !== 'true' ) {
            // Payment callback has cleared the flag - redirect needed.
            $gateway_settings = get_option( 'woocommerce_cardlink_payment_gateway_woocommerce_settings', [] );
            $redirect_page_id = $gateway_settings['redirect_page_id'] ?? '-1';

            if ( $redirect_page_id === '-1' || empty( $redirect_page_id ) || $redirect_page_id === '0' ) {
                $redirect_url = $order->get_checkout_order_received_url();
            } else {
                $redirect_url = get_permalink( (int) $redirect_page_id );
            }

            $this->respond( 'success', $redirect_url ?: $order->get_checkout_order_received_url() );
        }

        // Still waiting.
        $this->respond( 'pending', '' );
    }

    /**
     * Check if the current user/session can access the given order.
     */
    private function can_access_order( WC_Order $order ): bool {
        if ( is_user_logged_in() && $order->get_customer_id() === get_current_user_id() ) {
            return true;
        }

        if ( WC()->session ) {
            $session_order_id = WC()->session->get( 'order_id' );
            if ( $session_order_id && (int) $session_order_id === $order->get_id() ) {
                return true;
            }
        }

        return false;
    }

    private function respond( string $status, string $response ): void {
        wp_send_json( [
            'status'   => $status,
            'response' => $response,
        ] );
    }
}
