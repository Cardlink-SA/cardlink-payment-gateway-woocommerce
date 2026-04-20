<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Gateway;

defined( 'ABSPATH' ) || exit;

use Flavor\CardlinkPaymentGateway\Acquirer\AcquirerRegistry;
use Flavor\CardlinkPaymentGateway\Database\TransactionRepository;
use Flavor\CardlinkPaymentGateway\Payment\DigestService;
use Flavor\CardlinkPaymentGateway\Payment\FormBuilder;
use Flavor\CardlinkPaymentGateway\Payment\FormRenderer;
use Flavor\CardlinkPaymentGateway\Payment\ResponseHandler;
use Flavor\CardlinkPaymentGateway\Support\Logger;
use Flavor\CardlinkPaymentGateway\Support\OrderHelper;
use Flavor\CardlinkPaymentGateway\XmlApi\XmlApiService;
use WC_Order;
use WC_Payment_Gateway;

abstract class AbstractCardlinkGateway extends WC_Payment_Gateway {

    protected DigestService $digest_service;
    protected FormBuilder $form_builder;
    protected FormRenderer $form_renderer;
    protected ResponseHandler $response_handler;
    protected AcquirerRegistry $acquirer_registry;
    protected TransactionRepository $transaction_repo;
    protected Logger $logger;
    protected OrderHelper $order_helper;
    protected ?XmlApiService $xml_api_service = null;

    // Settings shared between gateways.
    protected string $merchant_id = '';
    protected string $shared_secret_key = '';
    protected string $environment = 'no';
    protected int $acquirer_index = 0;
    protected string $enable_log = 'no';
    protected string $css_url = '';
    protected string $popup = 'no';
    protected string $redirect_page_id = '-1';
    protected string $order_note = '';
    protected string $api_class_name = '';

    protected function inject_services(
        DigestService $digest_service,
        FormBuilder $form_builder,
        FormRenderer $form_renderer,
        ResponseHandler $response_handler,
        AcquirerRegistry $acquirer_registry,
        TransactionRepository $transaction_repo,
        Logger $logger,
        OrderHelper $order_helper
    ): void {
        $this->digest_service    = $digest_service;
        $this->form_builder      = $form_builder;
        $this->form_renderer     = $form_renderer;
        $this->response_handler  = $response_handler;
        $this->acquirer_registry = $acquirer_registry;
        $this->transaction_repo  = $transaction_repo;
        $this->logger            = $logger;
        $this->order_helper      = $order_helper;
    }

    /**
     * Get the Cardlink POST URL based on environment and acquirer.
     */
    protected function get_post_url(): string {
        $is_test = ( $this->environment === 'yes' );
        return $this->acquirer_registry->get_url( $this->acquirer_index, $is_test );
    }

    /**
     * Get the WooCommerce API callback URL for this gateway.
     */
    protected function get_confirm_url( string $result = 'success' ): string {
        return add_query_arg( 'result', $result, WC()->api_request_url( $this->api_class_name ) );
    }

    /**
     * Process payment - common redirect logic.
     */
    public function process_payment( $order_id ): array {
        $order = wc_get_order( $order_id );

        $this->save_checkout_meta( $order_id );

        // Set a flag so the receipt page knows this is a fresh submission (not a reload).
        if ( WC()->session ) {
            WC()->session->set( 'cardlink_receipt_ready_' . $order_id, $this->id );
        }

        return [
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url( true ),
        ];
    }

    /**
     * Save installments, card store, and selected card from POST data.
     */
    protected function save_checkout_meta( int $order_id ): void {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by WooCommerce checkout process.
        $gateway_id = $this->id;

        // Installments.
        $doseis_key = $gateway_id . '-card-doseis';
        if ( isset( $_POST[ $doseis_key ] ) ) {
            $doseis = absint( wp_unslash( $_POST[ $doseis_key ] ) );
            if ( $doseis > 0 ) {
                $this->order_helper->add_meta( $order_id, '_doseis', $doseis );
            }
        }

        // Store card.
        $store_key = $gateway_id . '-card-store';
        if ( isset( $_POST[ $store_key ] ) ) {
            $this->order_helper->add_meta( $order_id, '_cardlink_store_card', sanitize_text_field( wp_unslash( $_POST[ $store_key ] ) ) );
        }

        // Selected card.
        $card_key = $gateway_id . '-card';
        if ( isset( $_POST[ $card_key ] ) ) {
            $this->order_helper->add_meta( $order_id, '_cardlink_card', sanitize_text_field( wp_unslash( $_POST[ $card_key ] ) ) );
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    /**
     * Receipt page - shows redirect form only after a fresh form submission.
     *
     * A session flag set in process_payment() ensures the payment form is not
     * rendered on page reload, preventing unintended auto-redirects.
     */
    public function receipt_page( $order_id ): void {
        $session_key = 'cardlink_receipt_ready_' . $order_id;

        if ( ! WC()->session || WC()->session->get( $session_key ) !== $this->id ) {
            return;
        }

        // Consume the flag so a reload will not render the form again.
        WC()->session->set( $session_key, null );

        echo '<p>' . esc_html__( 'Thank you for your order. We are now redirecting you to make payment.', 'cardlink-payment-gateway' ) . '</p>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in FormRenderer::render_payment_form().
        echo $this->generate_payment_form( (int) $order_id );
    }

    /**
     * Generate the payment form HTML. Must be implemented by child classes.
     */
    abstract protected function generate_payment_form( int $order_id ): string;

    /**
     * Get the redirect URL after payment.
     */
    protected function get_redirect_url( WC_Order $order ): string {
        if ( $this->redirect_page_id === '-1' || empty( $this->redirect_page_id ) || $this->redirect_page_id === '0' ) {
            return $this->get_return_url( $order );
        }

        $page_url = get_permalink( (int) $this->redirect_page_id );
        return $page_url ? $page_url : $this->get_return_url( $order );
    }

    /**
     * Configure the logger from settings.
     */
    protected function configure_logger(): void {
        $this->logger->set_enabled( $this->enable_log === 'yes' );
    }

    /**
     * Redirect to the order pay page with an error message.
     *
     * Uses a query parameter because WC session may not be available
     * during WC API callbacks (e.g. 3DS returns).
     */
    protected function redirect_with_error( \WC_Order $order, string $error_msg ): void {
        $redirect_url = add_query_arg( 'cardlink_error', rawurlencode( $error_msg ), $order->get_checkout_payment_url() );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Display payment error notice from query parameter on the order-pay page.
     */
    public static function display_payment_error_notice(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! empty( $_GET['cardlink_error'] ) ) {
            $error = sanitize_text_field( wp_unslash( $_GET['cardlink_error'] ) );
            wc_print_notice( $error, 'error' );
        }
    }

    /**
     * Get the shared secret key used for digest computation/verification.
     */
    public function get_shared_secret_key(): string {
        return $this->shared_secret_key;
    }

    /**
     * Set the XML API service for secondary operations (refund, capture, void, status).
     */
    public function set_xml_api_service( XmlApiService $xml_api_service ): void {
        $this->xml_api_service = $xml_api_service;
    }

    /**
     * Process a refund via Cardlink XML API.
     *
     * WooCommerce calls this method when a refund is initiated from the admin.
     * It checks the transaction settlement status and performs a refund or void accordingly.
     *
     * @param int        $order_id Order ID.
     * @param float|null $amount   Refund amount.
     * @param string     $reason   Refund reason.
     * @return bool|\WP_Error
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new \WP_Error( 'cardlink_refund_error', __( 'Order not found.', 'cardlink-payment-gateway' ) );
        }

        if ( null === $this->xml_api_service ) {
            return new \WP_Error( 'cardlink_refund_error', __( 'XML API service is not available.', 'cardlink-payment-gateway' ) );
        }

        if ( ! $amount || $amount <= 0 ) {
            return new \WP_Error( 'cardlink_refund_error', __( 'Invalid refund amount.', 'cardlink-payment-gateway' ) );
        }

        $cardlink_order_id = $order->get_meta( '_cardlink_orderid', true );
        if ( empty( $cardlink_order_id ) ) {
            return new \WP_Error( 'cardlink_refund_error', __( 'Cardlink order ID not found. This order may have been placed before XML API integration.', 'cardlink-payment-gateway' ) );
        }
        $currency = $order->get_currency();

        // Check transaction status to determine refund or void.
        $status_response = $this->xml_api_service->status(
            $cardlink_order_id,
            $this->merchant_id,
            $this->shared_secret_key,
            $this->acquirer_index,
            $this->environment
        );

        if ( ! $status_response->isSuccess() ) {
            $error_msg = $status_response->getError() ?? __( 'Failed to retrieve transaction status.', 'cardlink-payment-gateway' );
            $order->add_order_note(
                /* translators: %s: error message */
                sprintf( __( 'Cardlink refund failed: %s', 'cardlink-payment-gateway' ), $error_msg )
            );
            return new \WP_Error( 'cardlink_refund_error', $error_msg );
        }

        $settl_status = $status_response->getSettlementStatus();

        // Settlement status 10 = in transit, cannot refund or void.
        if ( $settl_status === 10 ) {
            $msg = __( 'Transaction is currently in settlement transit. Please try again later.', 'cardlink-payment-gateway' );
            $order->add_order_note( sprintf( __( 'Cardlink refund failed: %s', 'cardlink-payment-gateway' ), $msg ) );
            return new \WP_Error( 'cardlink_refund_error', $msg );
        }

        // Date-based decision: same day → XML Cancel (Void); next day+ → XML Refund.
        // Same-day XML Refund and next-day XML Cancel are not supported by Cardlink.
        if ( $this->order_helper->is_payment_same_day( $order ) ) {
            $response = $this->xml_api_service->cancel(
                $cardlink_order_id,
                (float) $amount,
                $this->merchant_id,
                $this->shared_secret_key,
                $this->acquirer_index,
                $this->environment,
                $currency
            );
            $action_label = __( 'void', 'cardlink-payment-gateway' );
        } else {
            $response = $this->xml_api_service->refund(
                $cardlink_order_id,
                (float) $amount,
                $this->merchant_id,
                $this->shared_secret_key,
                $this->acquirer_index,
                $this->environment,
                $currency
            );
            $action_label = __( 'refund', 'cardlink-payment-gateway' );
        }

        if ( $response->isSuccess() ) {
            // Track cumulative XML-refunded amount (only for actual refunds, not same-day voids).
            if ( ! $this->order_helper->is_payment_same_day( $order ) ) {
                $prev_xml_refunded = (float) $order->get_meta( '_cardlink_xml_refunded', true );
                $order->update_meta_data( '_cardlink_xml_refunded', $prev_xml_refunded + (float) $amount );
                $order->save();
            }
            $order->add_order_note(
                /* translators: 1: action type (refund/void), 2: amount, 3: currency */
                sprintf(
                    __( 'Cardlink %1$s successful: %2$s %3$s', 'cardlink-payment-gateway' ),
                    $action_label,
                    number_format( (float) $amount, 2, '.', '' ),
                    $currency
                )
            );
            return true;
        }

        $error_msg = $response->getError() ?? __( 'Unknown error', 'cardlink-payment-gateway' );
        $order->add_order_note(
            /* translators: 1: action type (refund/void), 2: error message */
            sprintf( __( 'Cardlink %1$s failed: %2$s', 'cardlink-payment-gateway' ), $action_label, $error_msg )
        );
        return new \WP_Error( 'cardlink_refund_error', $error_msg );
    }
}
