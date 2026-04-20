<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Admin;

defined( 'ABSPATH' ) || exit;

use Flavor\CardlinkPaymentGateway\Gateway\CardlinkGateway;
use Flavor\CardlinkPaymentGateway\Support\OrderHelper;
use Flavor\CardlinkPaymentGateway\XmlApi\XmlApiService;

class OrderActions {

    private const CARDLINK_GATEWAY_IDS = [
        'cardlink_payment_gateway_woocommerce',
    ];

    private XmlApiService $xml_api;
    private OrderHelper $order_helper;

    public function __construct( XmlApiService $xml_api, OrderHelper $order_helper ) {
        $this->xml_api      = $xml_api;
        $this->order_helper = $order_helper;
    }

    /**
     * Add Cardlink actions to the WooCommerce order actions dropdown.
     *
     * @param array     $actions Existing actions.
     * @param \WC_Order $order   The order.
     * @return array
     */
    public function add_actions( array $actions, $order ): array {
        if ( ! $order instanceof \WC_Order ) {
            return $actions;
        }

        $gateway = $this->get_gateway();
        if ( ! $gateway || ! $gateway->is_xml_api_enabled() ) {
            return $actions;
        }

        $payment_method = $order->get_payment_method();
        if ( ! in_array( $payment_method, self::CARDLINK_GATEWAY_IDS, true ) ) {
            return $actions;
        }

        $status = $order->get_status();

        // Capture + Void: for on-hold orders (pre-authorized).
        // Pre-authorized transactions can be cancelled/reversed on any day (not yet captured).
        if ( $status === 'on-hold' ) {
            $actions['cardlink_capture'] = __( 'Cardlink: Capture payment', 'cardlink-payment-gateway' );
            $actions['cardlink_void']    = __( 'Cardlink: Void payment', 'cardlink-payment-gateway' );
        }

        // Void: for processing orders (captured, same day only).
        // From the next day onwards, only XML Refund is supported (via the WooCommerce refund button).
        if ( $status === 'processing' && $this->order_helper->is_payment_same_day( $order ) ) {
            $actions['cardlink_void'] = __( 'Cardlink: Void payment', 'cardlink-payment-gateway' );
        }

        return $actions;
    }

    /**
     * Handle the Capture order action.
     *
     * @param \WC_Order $order
     */
    public function capture( \WC_Order $order ): void {
        $gateway = $this->get_gateway();
        if ( ! $gateway || ! $gateway->is_xml_api_enabled() ) {
            $order->add_order_note( __( 'Cardlink capture failed: Gateway not available or XML API disabled.', 'cardlink-payment-gateway' ) );
            return;
        }

        $cardlink_order_id = $order->get_meta( '_cardlink_orderid', true );
        if ( empty( $cardlink_order_id ) ) {
            $order->add_order_note( __( 'Cardlink capture failed: Cardlink order ID not found.', 'cardlink-payment-gateway' ) );
            return;
        }
        $amount   = (float) $order->get_total();
        $currency = $order->get_currency();

        $response = $this->xml_api->capture(
            $cardlink_order_id,
            $amount,
            $gateway->get_merchant_id(),
            $gateway->get_shared_secret_key(),
            $gateway->get_acquirer_index(),
            $gateway->get_environment(),
            $currency
        );

        if ( $response->isSuccess() ) {
            // Reset transaction date to today so that same-day Void / next-day Refund
            // applies from the capture date, not the original pre-auth date.
            $order->update_meta_data( '_cardlink_transaction_date', ( new \DateTime( 'now', wp_timezone() ) )->format( 'Y-m-d' ) );
            $order->add_order_note(
                sprintf( __( 'Cardlink capture successful: %1$s %2$s', 'cardlink-payment-gateway' ), number_format( $amount, 2, '.', '' ), $currency )
            );
            $order->payment_complete(); // saves order (including updated meta)
        } else {
            $error = $response->getError() ?? __( 'Unknown error', 'cardlink-payment-gateway' );
            $order->add_order_note( sprintf( __( 'Cardlink capture failed: %s', 'cardlink-payment-gateway' ), $error ) );
        }
    }

    /**
     * Handle the Void order action.
     *
     * @param \WC_Order $order
     */
    public function void( \WC_Order $order ): void {
        // Pre-authorized (on-hold) orders can be cancelled/reversed on any day.
        // Captured (processing) orders can only be voided on the same calendar day.
        $is_preauth = $order->has_status( 'on-hold' );
        if ( ! $is_preauth && ! $this->order_helper->is_payment_same_day( $order ) ) {
            $order->add_order_note( __( 'Cardlink void failed: Void (XML Cancel) is only available on the same day as the transaction. Use the WooCommerce refund button to issue an XML Refund.', 'cardlink-payment-gateway' ) );
            return;
        }

        $gateway = $this->get_gateway();
        if ( ! $gateway || ! $gateway->is_xml_api_enabled() ) {
            $order->add_order_note( __( 'Cardlink void failed: Gateway not available or XML API disabled.', 'cardlink-payment-gateway' ) );
            return;
        }

        $cardlink_order_id = $order->get_meta( '_cardlink_orderid', true );
        if ( empty( $cardlink_order_id ) ) {
            $order->add_order_note( __( 'Cardlink void failed: Cardlink order ID not found.', 'cardlink-payment-gateway' ) );
            return;
        }
        $amount   = (float) $order->get_total();
        $currency = $order->get_currency();

        $response = $this->xml_api->cancel(
            $cardlink_order_id,
            $amount,
            $gateway->get_merchant_id(),
            $gateway->get_shared_secret_key(),
            $gateway->get_acquirer_index(),
            $gateway->get_environment(),
            $currency
        );

        if ( $response->isSuccess() ) {
            $order->add_order_note(
                sprintf( __( 'Cardlink void successful: %1$s %2$s', 'cardlink-payment-gateway' ), number_format( $amount, 2, '.', '' ), $currency )
            );
            $order->update_status( 'cancelled', __( 'Payment voided via Cardlink.', 'cardlink-payment-gateway' ) );
        } else {
            $error = $response->getError() ?? __( 'Unknown error', 'cardlink-payment-gateway' );
            $order->add_order_note( sprintf( __( 'Cardlink void failed: %s', 'cardlink-payment-gateway' ), $error ) );
        }
    }

    private function get_gateway(): ?CardlinkGateway {
        $gateways = WC()->payment_gateways()->payment_gateways();
        return $gateways['cardlink_payment_gateway_woocommerce'] ?? null;
    }
}
