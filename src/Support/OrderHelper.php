<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Support;

defined( 'ABSPATH' ) || exit;

use WC_Order;

class OrderHelper {

    /**
     * @param mixed $value
     */
    public function add_meta( int $order_id, string $key, $value ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order instanceof WC_Order ) {
            return;
        }
        $order->update_meta_data( $key, $value );
        $order->save_meta_data();
    }

    /**
     * @return mixed
     */
    public function get_meta( WC_Order $order, string $key, bool $single = true ) {
        return $order->get_meta( $key, $single );
    }

    public function delete_meta( WC_Order $order, string $key ): void {
        $order->delete_meta_data( $key );
        $order->save_meta_data();
    }

    /**
     * Check whether the Cardlink transaction on this order occurred today (same calendar day).
     *
     * Same-day → XML Cancel (Void) is allowed.
     * Next day+ → XML Refund must be used instead.
     *
     * Uses the `_cardlink_transaction_date` meta saved at payment/authorization time.
     * Falls back to WooCommerce's date_paid for already-existing processing orders.
     */
    public function is_payment_same_day( WC_Order $order ): bool {
        $today = ( new \DateTime( 'now', wp_timezone() ) )->format( 'Y-m-d' );

        $transaction_date = $order->get_meta( '_cardlink_transaction_date', true );
        if ( ! empty( $transaction_date ) ) {
            return $transaction_date === $today;
        }

        // Fallback for orders that existed before _cardlink_transaction_date was introduced.
        $date_paid = $order->get_date_paid();
        if ( $date_paid ) {
            return $date_paid->date( 'Y-m-d' ) === $today;
        }

        return false;
    }
}
