<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Admin;

defined( 'ABSPATH' ) || exit;

use Flavor\CardlinkPaymentGateway\Support\OrderHelper;

class OrderMessageHandler {

    private OrderHelper $order_helper;

    public function __construct( OrderHelper $order_helper ) {
        $this->order_helper = $order_helper;
    }

    /**
     * Display Cardlink message on the thank-you / order-received page.
     */
    public function display_on_thankyou(): void {
        if ( ! is_order_received_page() ) {
            return;
        }

        global $wp;
        $order_id = absint( $wp->query_vars['order-received'] ?? 0 );
        if ( ! $order_id ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $message_data = $this->order_helper->get_meta( $order, '_cardlink_message' );
        if ( empty( $message_data ) || ! is_array( $message_data ) ) {
            return;
        }

        $message      = $message_data['message'] ?? '';
        $message_type = $message_data['message_type'] ?? 'notice';

        if ( ! empty( $message ) ) {
            wc_add_notice( $message, $message_type );
        }

        // Remove the message after displaying.
        $this->order_helper->delete_meta( $order, '_cardlink_message' );
    }
}
