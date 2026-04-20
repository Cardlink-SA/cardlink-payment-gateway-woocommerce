<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Payment;

defined( 'ABSPATH' ) || exit;

use Flavor\CardlinkPaymentGateway\Exception\DigestMismatchException;
use Flavor\CardlinkPaymentGateway\Exception\InvalidResponseException;
use Flavor\CardlinkPaymentGateway\Support\Logger;
use Flavor\CardlinkPaymentGateway\Support\OrderHelper;
use Flavor\CardlinkPaymentGateway\Support\SanitizationHelper;
use Flavor\CardlinkPaymentGateway\Token\TokenManager;
use WC_Order;

class ResponseHandler {

    private DigestService $digest_service;
    private TokenManager $token_manager;
    private OrderHelper $order_helper;
    private Logger $logger;

    public function __construct(
        DigestService $digest_service,
        TokenManager $token_manager,
        OrderHelper $order_helper,
        Logger $logger
    ) {
        $this->digest_service = $digest_service;
        $this->token_manager  = $token_manager;
        $this->order_helper   = $order_helper;
        $this->logger         = $logger;
    }

    /**
     * Handle a Cardlink payment response.
     */
    public function handle(
        array $post_data,
        string $shared_secret,
        string $gateway_id,
        bool $send_order_note = true,
        bool $is_background = false
    ): WC_Order {
        $this->logger->log_response( $post_data );

        $fields   = $this->parse_response_fields( $post_data );
        $order_id = $this->extract_order_id( $post_data );
        $order    = wc_get_order( $order_id );

        if ( ! $order instanceof WC_Order ) {
            throw new InvalidResponseException(
                sprintf( 'Order %d not found.', $order_id )
            );
        }

        // Verify digests.
        try {
            $this->digest_service->verify_response( $post_data, $shared_secret );
            $this->digest_service->verify_bonus_digest( $post_data, $shared_secret );
        } catch ( DigestMismatchException $e ) {
            $this->logger->log( 'Digest verification failed: ' . $e->getMessage(), 'error' );
            $this->handle_failed_payment(
                $order,
                'ERROR',
                __( "A technical problem occurred. The transaction wasn't successful, payment wasn't received.", 'cardlink-payment-gateway' )
            );
            return $order;
        }

        $status = strtoupper( $fields['status'] );

        switch ( $status ) {
            case 'CAPTURED':
                $this->handle_successful_payment( $order, $fields, $gateway_id, $send_order_note, $is_background );
                break;

            case 'AUTHORIZED':
                $this->handle_authorized_payment( $order, $fields, $gateway_id, $send_order_note, $is_background );
                break;

            case 'CANCELED':
                $this->handle_failed_payment(
                    $order,
                    $status,
                    __( 'Payment canceled by the customer.', 'cardlink-payment-gateway' ),
                    'notice'
                );
                break;

            case 'REFUSED':
                $this->handle_failed_payment(
                    $order,
                    $status,
                    __( 'Payment was refused.', 'cardlink-payment-gateway' )
                );
                break;

            case 'ERROR':
            default:
                $message = ! empty( $fields['message'] )
                    ? $fields['message']
                    : __( 'A technical problem occurred.', 'cardlink-payment-gateway' );
                $this->handle_failed_payment( $order, $status, $message );
                break;
        }

        return $order;
    }

    public function extract_order_id( array $post_data ): int {
        $orderid_post = sanitize_text_field( $post_data['orderid'] ?? '' );

        if ( preg_match( '/^(.*?)at/', $orderid_post, $matches ) ) {
            $id = (int) $matches[1];
            if ( $id > 0 ) {
                return $id;
            }
        }

        // Fallback to session.
        if ( WC()->session ) {
            $session_id = WC()->session->get( 'order_id' );
            if ( $session_id ) {
                return (int) $session_id;
            }
        }

        return (int) $orderid_post;
    }

    private function parse_response_fields( array $post_data ): array {
        return [
            'mid'            => SanitizationHelper::sanitize( $post_data['mid'] ?? '', 'integer' ),
            'orderid'        => SanitizationHelper::sanitize( $post_data['orderid'] ?? '' ),
            'status'         => SanitizationHelper::sanitize( $post_data['status'] ?? '' ),
            'orderAmount'    => SanitizationHelper::sanitize( $post_data['orderAmount'] ?? '', 'float' ),
            'currency'       => SanitizationHelper::sanitize( $post_data['currency'] ?? '' ),
            'paymentTotal'   => SanitizationHelper::sanitize( $post_data['paymentTotal'] ?? '', 'float' ),
            'message'        => isset( $post_data['message'] ) ? stripslashes( sanitize_text_field( $post_data['message'] ) ) : '',
            'riskScore'      => SanitizationHelper::sanitize( $post_data['riskScore'] ?? '', 'float' ),
            'payMethod'      => SanitizationHelper::sanitize( $post_data['payMethod'] ?? '' ),
            'txId'           => SanitizationHelper::sanitize( $post_data['txId'] ?? '', 'float' ),
            'paymentRef'     => SanitizationHelper::sanitize( $post_data['paymentRef'] ?? '' ),
            'extToken'       => SanitizationHelper::sanitize( $post_data['extToken'] ?? '' ),
            'extTokenPanEnd' => SanitizationHelper::sanitize( $post_data['extTokenPanEnd'] ?? '' ),
            'extTokenExp'    => SanitizationHelper::sanitize( $post_data['extTokenExp'] ?? '' ),
        ];
    }

    private function handle_successful_payment(
        WC_Order $order,
        array $fields,
        string $gateway_id,
        bool $send_note,
        bool $is_background = false
    ): void {
        $payment_ref = $fields['paymentRef'];

        // Store the Cardlink orderid for later XML API secondary transactions.
        if ( ! empty( $fields['orderid'] ) ) {
            $order->update_meta_data( '_cardlink_orderid', $fields['orderid'] );
        }

        // Store transaction date (Y-m-d, WP timezone) to enforce same-day Void vs. next-day Refund rule.
        $order->update_meta_data( '_cardlink_transaction_date', ( new \DateTime( 'now', wp_timezone() ) )->format( 'Y-m-d' ) );
        $order->save();

        $order->payment_complete( $payment_ref );

        $pay_method = strtoupper( $fields['payMethod'] ?? '' );
        $is_iris    = $pay_method === 'IRIS' || $gateway_id === 'cardlink_payment_gateway_woocommerce_iris';

        // Ensure the payment method title reflects the actual payment method used.
        if ( $is_iris ) {
            $order->set_payment_method_title( __( 'IRIS', 'cardlink-payment-gateway' ) );
            $order->save();

            $note = sprintf(
                /* translators: %s: payment reference */
                __( 'Payment Via IRIS. Transaction ID: %s', 'cardlink-payment-gateway' ),
                $payment_ref
            );
        } else {
            $note = sprintf(
                /* translators: %s: payment reference */
                __( 'Payment Via Cardlink. Transaction ID: %s', 'cardlink-payment-gateway' ),
                $payment_ref
            );
        }

        if ( $send_note ) {
            $order->add_order_note( $note, true );
        }

        // Save token if tokenization response present.
        $ext_token = $fields['extToken'];
        if ( ! empty( $ext_token ) && ! empty( $fields['extTokenPanEnd'] ) && ! empty( $fields['extTokenExp'] ) ) {
            $current_user_id = $order->get_customer_id();
            if ( $current_user_id > 0 ) {
                $exp_year  = substr( $fields['extTokenExp'], 0, 4 );
                $exp_month = substr( $fields['extTokenExp'], 4, 2 );

                $this->token_manager->save_token_from_response(
                    $current_user_id,
                    $gateway_id,
                    $ext_token,
                    $fields['extTokenPanEnd'],
                    $exp_year,
                    $exp_month,
                    $fields['payMethod']
                );
            }
        }

        // Skip cart/session operations in background context (no browser session available).
        if ( ! $is_background ) {
            WC()->cart->empty_cart();

            $this->order_helper->add_meta( $order->get_id(), '_cardlink_message', [
                'message'      => ( $is_iris
                    ? __( 'Payment Via IRIS<br />Transaction ID: ', 'cardlink-payment-gateway' )
                    : __( 'Payment Via Cardlink<br />Transaction ID: ', 'cardlink-payment-gateway' )
                ) . $payment_ref,
                'message_type' => 'success',
            ] );
        }
    }

    private function handle_authorized_payment(
        WC_Order $order,
        array $fields,
        string $gateway_id,
        bool $send_note,
        bool $is_background = false
    ): void {
        $payment_ref = $fields['paymentRef'];

        // Store the Cardlink orderid for later XML API secondary transactions.
        if ( ! empty( $fields['orderid'] ) ) {
            $order->update_meta_data( '_cardlink_orderid', $fields['orderid'] );
        }

        // Store transaction date (Y-m-d, WP timezone) to enforce same-day Void vs. next-day Refund rule.
        $order->update_meta_data( '_cardlink_transaction_date', ( new \DateTime( 'now', wp_timezone() ) )->format( 'Y-m-d' ) );

        // Ensure the payment method title reflects the actual payment method used.
        $pay_method = strtoupper( $fields['payMethod'] ?? '' );
        $is_iris    = $pay_method === 'IRIS' || $gateway_id === 'cardlink_payment_gateway_woocommerce_iris';

        if ( $is_iris ) {
            $order->set_payment_method_title( __( 'IRIS', 'cardlink-payment-gateway' ) );
        }

        $order->set_transaction_id( $payment_ref );
        $order->update_status( 'on-hold', sprintf(
            $is_iris
                ? __( 'IRIS payment authorized (pre-auth). Transaction ID: %s. Awaiting capture.', 'cardlink-payment-gateway' )
                : __( 'Cardlink payment authorized (pre-auth). Transaction ID: %s. Awaiting capture.', 'cardlink-payment-gateway' ),
            $payment_ref
        ) );

        $note = sprintf(
            $is_iris
                ? __( 'Payment pre-authorized via IRIS. Transaction ID: %s. Use Capture to complete the payment.', 'cardlink-payment-gateway' )
                : __( 'Payment pre-authorized via Cardlink. Transaction ID: %s. Use Capture to complete the payment.', 'cardlink-payment-gateway' ),
            $payment_ref
        );

        if ( $send_note ) {
            $order->add_order_note( $note, true );
        }

        // Save token if tokenization response present.
        $ext_token = $fields['extToken'];
        if ( ! empty( $ext_token ) && ! empty( $fields['extTokenPanEnd'] ) && ! empty( $fields['extTokenExp'] ) ) {
            $current_user_id = $order->get_customer_id();
            if ( $current_user_id > 0 ) {
                $exp_year  = substr( $fields['extTokenExp'], 0, 4 );
                $exp_month = substr( $fields['extTokenExp'], 4, 2 );

                $this->token_manager->save_token_from_response(
                    $current_user_id,
                    $gateway_id,
                    $ext_token,
                    $fields['extTokenPanEnd'],
                    $exp_year,
                    $exp_month,
                    $fields['payMethod']
                );
            }
        }

        // Skip cart/session operations in background context (no browser session available).
        if ( ! $is_background ) {
            WC()->cart->empty_cart();

            $this->order_helper->add_meta( $order->get_id(), '_cardlink_message', [
                'message'      => __( 'Payment pre-authorized via Cardlink<br />Transaction ID: ', 'cardlink-payment-gateway' ) . $payment_ref,
                'message_type' => 'success',
            ] );
        }
    }

    private function handle_failed_payment(
        WC_Order $order,
        string $status,
        string $message,
        string $type = 'error'
    ): void {
        $order->update_status( 'failed', $message );

        $display_message = ! empty( $message )
            ? $message
            : __( "A technical problem occurred. The transaction wasn't successful, payment wasn't received.", 'cardlink-payment-gateway' );

        $this->order_helper->add_meta( $order->get_id(), '_cardlink_message', [
            'message'      => $display_message,
            'message_type' => $type,
        ] );
    }
}
