<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Admin;

defined( 'ABSPATH' ) || exit;

use Flavor\CardlinkPaymentGateway\Gateway\CardlinkGateway;
use Flavor\CardlinkPaymentGateway\Support\OrderHelper;
use Flavor\CardlinkPaymentGateway\XmlApi\XmlApiService;

class OrderMetaBox {

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
     * Register the meta box for both legacy and HPOS order screens.
     */
    public function register(): void {
        $gateway = $this->get_gateway_instance();
        if ( ! $gateway || ! $gateway->is_xml_api_enabled() ) {
            return;
        }

        $screen = $this->get_order_screen();

        add_meta_box(
            'cardlink_transaction_info',
            __( 'Cardlink Transaction', 'cardlink-payment-gateway' ),
            [ $this, 'render' ],
            $screen,
            'side',
            'high'
        );
    }

    /**
     * Render the meta box content.
     *
     * @param \WP_Post|\WC_Order $post_or_order
     */
    public function render( $post_or_order ): void {
        $order = $this->resolve_order( $post_or_order );
        if ( ! $order ) {
            return;
        }

        $payment_method = $order->get_payment_method();
        if ( ! in_array( $payment_method, self::CARDLINK_GATEWAY_IDS, true ) ) {
            echo '<p>' . esc_html__( 'This order was not paid via Cardlink.', 'cardlink-payment-gateway' ) . '</p>';
            return;
        }

        $gateway = $this->get_gateway_instance();
        if ( ! $gateway ) {
            echo '<p>' . esc_html__( 'Gateway not available.', 'cardlink-payment-gateway' ) . '</p>';
            return;
        }

        // Do not query Cardlink if the payment has not been completed yet.
        $cardlink_order_id = $order->get_meta( '_cardlink_orderid', true );
        if ( empty( $cardlink_order_id ) ) {
            echo '<p>' . esc_html__( 'Payment has not been completed yet. Transaction info will be available after a successful payment.', 'cardlink-payment-gateway' ) . '</p>';
            return;
        }

        $order_id       = (string) $order->get_id();
        $is_same_day    = $this->order_helper->is_payment_same_day( $order ) ? '1' : '0';
        $order_total      = number_format( (float) $order->get_total(), 2, '.', '' );
        $xml_refunded     = (float) $order->get_meta( '_cardlink_xml_refunded', true );
        $max_refundable   = number_format( max( 0.0, (float) $order->get_total() - (float) $order->get_total_refunded() - $xml_refunded ), 2, '.', '' );
        $captured_amount  = (float) $order->get_meta( '_cardlink_captured_amount', true );
        $remaining_capture = number_format( max( 0.0, (float) $order->get_total() - $captured_amount ), 2, '.', '' );
        $currency         = $order->get_currency();

        echo '<div id="cardlink-metabox-content">';
        echo '<p class="cardlink-metabox-loading">' . esc_html__( 'Loading transaction info...', 'cardlink-payment-gateway' ) . '</p>';
        echo '</div>';

        wp_nonce_field( 'cardlink_xml_action', 'cardlink_xml_nonce' );
        echo '<input type="hidden" id="cardlink-order-id" value="' . esc_attr( $order_id ) . '" />';
        echo '<input type="hidden" id="cardlink-is-same-day" value="' . esc_attr( $is_same_day ) . '" />';
        echo '<input type="hidden" id="cardlink-order-total" value="' . esc_attr( $order_total ) . '" />';
        echo '<input type="hidden" id="cardlink-max-refundable" value="' . esc_attr( $max_refundable ) . '" />';
        echo '<input type="hidden" id="cardlink-remaining-capture" value="' . esc_attr( $remaining_capture ) . '" />';
        echo '<input type="hidden" id="cardlink-currency" value="' . esc_attr( $currency ) . '" />';

        // Inline script for AJAX.
        $this->render_inline_script();
    }

    /**
     * Handle AJAX requests for transaction actions.
     */
    public function handle_ajax(): void {
        check_ajax_referer( 'cardlink_xml_action', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'cardlink-payment-gateway' ) ] );
        }

        $gateway = $this->get_gateway_instance();
        if ( ! $gateway || ! $gateway->is_xml_api_enabled() ) {
            wp_send_json_error( [ 'message' => __( 'XML API is not enabled.', 'cardlink-payment-gateway' ) ] );
        }

        $action   = sanitize_text_field( wp_unslash( $_POST['cardlink_action'] ?? '' ) );
        $order_id = absint( wp_unslash( $_POST['order_id'] ?? 0 ) );

        if ( ! $order_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid order ID.', 'cardlink-payment-gateway' ) ] );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( [ 'message' => __( 'Order not found.', 'cardlink-payment-gateway' ) ] );
        }

        $gateway = $this->get_gateway_instance();
        if ( ! $gateway ) {
            wp_send_json_error( [ 'message' => __( 'Gateway not available.', 'cardlink-payment-gateway' ) ] );
        }

        $merchant_id    = $gateway->get_merchant_id();
        $shared_secret  = $gateway->get_shared_secret_key();
        $acquirer_index = $gateway->get_acquirer_index();
        $environment    = $gateway->get_environment();
        $cardlink_order_id = $order->get_meta( '_cardlink_orderid', true );
        if ( empty( $cardlink_order_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Cardlink order ID not found. This order may have been placed before XML API integration.', 'cardlink-payment-gateway' ) ] );
        }
        $amount         = (float) $order->get_total();
        $currency       = $order->get_currency();

        switch ( $action ) {
            case 'status':
                $response = $this->xml_api->status( $cardlink_order_id, $merchant_id, $shared_secret, $acquirer_index, $environment );
                if ( $response->isSuccess() ) {
                    wp_send_json_success( [
                        'status'            => $response->getStatus(),
                        'transaction_id'    => $response->getTransactionId(),
                        'order_amount'      => $response->getOrderAmount(),
                        'currency'          => $response->getCurrency(),
                        'payment_ref'       => $response->getPaymentRef(),
                        'settlement_status' => $response->getSettlementStatus(),
                        'is_settled'        => $response->isSettled(),
                        'can_void'          => $response->canVoid(),
                        'in_transit'        => $response->isInSettlementTransit(),
                        'description'       => $response->getDescription(),
                    ] );
                } else {
                    wp_send_json_error( [ 'message' => $response->getError() ?? __( 'Failed to fetch status.', 'cardlink-payment-gateway' ) ] );
                }
                break;

            case 'capture':
                $capture_amount = floatval( sanitize_text_field( wp_unslash( $_POST['capture_amount'] ?? '' ) ) );
                if ( $capture_amount <= 0 ) {
                    $capture_amount = $amount; // default to full order total
                }
                $response = $this->xml_api->capture( $cardlink_order_id, $capture_amount, $merchant_id, $shared_secret, $acquirer_index, $environment, $currency );
                if ( $response->isSuccess() ) {
                    // Track cumulative captured amount for partial-capture support.
                    $prev_captured = (float) $order->get_meta( '_cardlink_captured_amount', true );
                    $new_captured  = $prev_captured + $capture_amount;
                    $order->update_meta_data( '_cardlink_captured_amount', $new_captured );
                    // Reset transaction date to today so that same-day Void / next-day Refund
                    // applies from the capture date, not the original pre-auth date.
                    $order->update_meta_data( '_cardlink_transaction_date', ( new \DateTime( 'now', wp_timezone() ) )->format( 'Y-m-d' ) );
                    $order->add_order_note(
                        sprintf( __( 'Cardlink capture successful: %1$s %2$s', 'cardlink-payment-gateway' ), number_format( $capture_amount, 2, '.', '' ), $currency )
                    );
                    $order->payment_complete(); // saves order (including updated meta)
                    $remaining_capture = number_format( max( 0.0, $amount - $new_captured ), 2, '.', '' );
                    wp_send_json_success( [
                        'message'          => __( 'Capture successful.', 'cardlink-payment-gateway' ),
                        'remaining_capture' => $remaining_capture,
                    ] );
                } else {
                    $error = $response->getError() ?? __( 'Capture failed.', 'cardlink-payment-gateway' );
                    $order->add_order_note( sprintf( __( 'Cardlink capture failed: %s', 'cardlink-payment-gateway' ), $error ) );
                    wp_send_json_error( [ 'message' => $error ] );
                }
                break;

            case 'void':
                // For captured (processing) orders, void is only available same day.
                // For pre-authorized (on-hold) orders, cancel/reverse is allowed on any day.
                $is_preauth_order = $order->has_status( 'on-hold' );
                if ( ! $is_preauth_order && ! $this->order_helper->is_payment_same_day( $order ) ) {
                    wp_send_json_error( [ 'message' => __( 'Void (XML Cancel) is only available on the same day as the transaction. Use the WooCommerce refund button to issue an XML Refund.', 'cardlink-payment-gateway' ) ] );
                }
                $response = $this->xml_api->cancel( $cardlink_order_id, $amount, $merchant_id, $shared_secret, $acquirer_index, $environment, $currency );
                if ( $response->isSuccess() ) {
                    $order->add_order_note(
                        sprintf( __( 'Cardlink cancel/reverse successful: %1$s %2$s', 'cardlink-payment-gateway' ), number_format( $amount, 2, '.', '' ), $currency )
                    );
                    $order->update_status( 'cancelled', __( 'Payment cancelled/reversed via Cardlink.', 'cardlink-payment-gateway' ) );
                    wp_send_json_success( [ 'message' => __( 'Cancel (Reverse) successful.', 'cardlink-payment-gateway' ) ] );
                } else {
                    $error = $response->getError() ?? __( 'Cancel failed.', 'cardlink-payment-gateway' );
                    $order->add_order_note( sprintf( __( 'Cardlink cancel/reverse failed: %s', 'cardlink-payment-gateway' ), $error ) );
                    wp_send_json_error( [ 'message' => $error ] );
                }
                break;

            case 'refund':
                // XML Refund is only supported from the next day onwards.
                if ( $this->order_helper->is_payment_same_day( $order ) ) {
                    wp_send_json_error( [ 'message' => __( 'XML Refund is not available on the same day as the transaction. Use Void (XML Cancel) instead.', 'cardlink-payment-gateway' ) ] );
                }

                $refund_amount = floatval( sanitize_text_field( wp_unslash( $_POST['refund_amount'] ?? '' ) ) );

                if ( $refund_amount <= 0 ) {
                    wp_send_json_error( [ 'message' => __( 'Invalid refund amount. Please enter a value greater than zero.', 'cardlink-payment-gateway' ) ] );
                }

                $response = $this->xml_api->refund( $cardlink_order_id, $refund_amount, $merchant_id, $shared_secret, $acquirer_index, $environment, $currency );
                if ( $response->isSuccess() ) {
                    // Track cumulative XML-refunded amount for partial-refund support.
                    $prev_xml_refunded = (float) $order->get_meta( '_cardlink_xml_refunded', true );
                    $new_xml_refunded  = $prev_xml_refunded + $refund_amount;
                    $order->update_meta_data( '_cardlink_xml_refunded', $new_xml_refunded );
                    $order->add_order_note(
                        sprintf(
                            /* translators: 1: refund amount, 2: currency */
                            __( 'Cardlink refund successful: %1$s %2$s', 'cardlink-payment-gateway' ),
                            number_format( $refund_amount, 2, '.', '' ),
                            $currency
                        )
                    );
                    $order->save();
                    $new_max = number_format( max( 0.0, (float) $order->get_total() - (float) $order->get_total_refunded() - $new_xml_refunded ), 2, '.', '' );
                    wp_send_json_success( [
                        'message'        => __( 'Refund successful.', 'cardlink-payment-gateway' ),
                        'max_refundable' => $new_max,
                    ] );
                } else {
                    $error = $response->getError() ?? __( 'Refund failed.', 'cardlink-payment-gateway' );
                    $order->add_order_note( sprintf( __( 'Cardlink refund failed: %s', 'cardlink-payment-gateway' ), $error ) );
                    wp_send_json_error( [ 'message' => $error ] );
                }
                break;

            default:
                wp_send_json_error( [ 'message' => __( 'Unknown action.', 'cardlink-payment-gateway' ) ] );
        }
    }

    /**
     * Get the correct screen ID for the order edit page (HPOS compatible).
     */
    private function get_order_screen(): string {
        if ( class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
            $controller = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class );
            if ( method_exists( $controller, 'custom_orders_table_usage_is_enabled' ) && $controller->custom_orders_table_usage_is_enabled() ) {
                return 'woocommerce_page_wc-orders';
            }
        }
        return 'shop_order';
    }

    /**
     * Resolve a WC_Order from either a WP_Post or WC_Order.
     */
    private function resolve_order( $post_or_order ): ?\WC_Order {
        if ( $post_or_order instanceof \WC_Order ) {
            return $post_or_order;
        }
        if ( $post_or_order instanceof \WP_Post ) {
            return wc_get_order( $post_or_order->ID ) ?: null;
        }
        return null;
    }

    private function get_gateway_instance(): ?CardlinkGateway {
        $gateways = WC()->payment_gateways()->payment_gateways();
        return $gateways['cardlink_payment_gateway_woocommerce'] ?? null;
    }

    private function render_inline_script(): void {
        ?>
        <script type="text/javascript">
        (function($) {
            var orderId          = $('#cardlink-order-id').val();
            var nonce            = $('#cardlink_xml_nonce').val();
            var isSameDay        = $('#cardlink-is-same-day').val() === '1';
            var orderTotal       = parseFloat($('#cardlink-order-total').val()) || 0;
            var maxRefundable    = parseFloat($('#cardlink-max-refundable').val()) || 0;
            var remainingCapture = parseFloat($('#cardlink-remaining-capture').val());
            if (isNaN(remainingCapture)) { remainingCapture = orderTotal; }
            var currency         = $('#cardlink-currency').val() || '';

            function cardlinkAjax(action, callback, extraData) {
                var data = $.extend({
                    action: 'cardlink_xml_action',
                    cardlink_action: action,
                    order_id: orderId,
                    nonce: nonce
                }, extraData || {});
                $.post(ajaxurl, data, callback);
            }

            function renderAmountInput(id, value, max) {
                return '<input type="number" id="' + id + '" value="' + value.toFixed(2) + '"' +
                       ' min="0.01" max="' + max.toFixed(2) + '" step="0.01" style="width:110px" />';
            }

            function renderStatus(data) {
                var html = '<table class="widefat striped" style="border:0">';
                html += '<tr><td><strong><?php echo esc_js( __( 'Status', 'cardlink-payment-gateway' ) ); ?></strong></td><td>' + (data.status || '-') + '</td></tr>';
                html += '<tr><td><strong><?php echo esc_js( __( 'Transaction ID', 'cardlink-payment-gateway' ) ); ?></strong></td><td>' + (data.transaction_id || '-') + '</td></tr>';
                html += '<tr><td><strong><?php echo esc_js( __( 'Amount', 'cardlink-payment-gateway' ) ); ?></strong></td><td>' + (data.order_amount || '-') + ' ' + (data.currency || '') + '</td></tr>';
                html += '<tr><td><strong><?php echo esc_js( __( 'Payment Ref', 'cardlink-payment-gateway' ) ); ?></strong></td><td>' + (data.payment_ref || '-') + '</td></tr>';

                var settlLabel = '-';
                if (data.settlement_status !== null && data.settlement_status !== undefined) {
                    if (data.is_settled) settlLabel = '<?php echo esc_js( __( 'Settled', 'cardlink-payment-gateway' ) ); ?>';
                    else if (data.in_transit) settlLabel = '<?php echo esc_js( __( 'In transit', 'cardlink-payment-gateway' ) ); ?>';
                    else if (data.can_void) settlLabel = '<?php echo esc_js( __( 'Not settled', 'cardlink-payment-gateway' ) ); ?>';
                }
                html += '<tr><td><strong><?php echo esc_js( __( 'Settlement', 'cardlink-payment-gateway' ) ); ?></strong></td><td>' + settlLabel + '</td></tr>';
                html += '</table>';

                html += '<div style="margin-top:10px;">';

                if (data.status === 'AUTHORIZED' || data.status === 'CAPTUREDPARTIALLY') {
                    // ── Pre-auth or partially-captured: Capture (partial or full, multiple times allowed) ───────
                    var captureMax = remainingCapture > 0 ? remainingCapture : orderTotal;
                    html += '<p style="margin:0 0 5px;font-weight:600"><?php echo esc_js( __( 'Capture', 'cardlink-payment-gateway' ) ); ?></p>';
                    html += '<div style="display:flex;gap:5px;align-items:center;flex-wrap:wrap;">';
                    html += renderAmountInput('cardlink-capture-amount', captureMax, captureMax);
                    html += '<span>' + currency + '</span>';
                    html += '<button type="button" class="button button-primary" id="cardlink-capture-btn"><?php echo esc_js( __( 'Capture', 'cardlink-payment-gateway' ) ); ?></button>';
                    html += '</div>';
                    html += '<p style="margin:3px 0 0;color:#646970;font-size:11px"><?php echo esc_js( __( 'Max:', 'cardlink-payment-gateway' ) ); ?> ' + captureMax.toFixed(2) + ' ' + currency + '</p>';

                    // Cancel/Reverse — available whenever the API allows it (not restricted to same day for preauth).
                    if (data.can_void) {
                        html += '<div style="margin-top:8px;display:flex;gap:5px;">';
                        html += '<button type="button" class="button" id="cardlink-void-btn"><?php echo esc_js( __( 'Cancel (Reverse)', 'cardlink-payment-gateway' ) ); ?></button>';
                        html += '</div>';
                    }

                } else if (data.status === 'CAPTURED' || data.status === 'REFUNDED' || data.status === 'REFUNDEDPARTIALLY' || data.status === 'PARTIALLY_REFUNDED') {
                    // ── Captured / Partially-refunded: Void (same-day) or Refund (next-day) ─
                    if (data.status === 'CAPTURED' && isSameDay && data.can_void) {
                        // Same-day Void — no partial support.
                        html += '<div style="display:flex;gap:5px;flex-wrap:wrap;">';
                        html += '<button type="button" class="button" id="cardlink-void-btn"><?php echo esc_js( __( 'Void', 'cardlink-payment-gateway' ) ); ?></button>';
                        html += '</div>';
                    } else if (!isSameDay) {
                        // Next-day: Partial or full XML Refund (multiple times allowed).
                        if (maxRefundable > 0) {
                            html += '<p style="margin:0 0 5px;font-weight:600"><?php echo esc_js( __( 'XML Refund', 'cardlink-payment-gateway' ) ); ?></p>';
                            html += '<div style="display:flex;gap:5px;align-items:center;flex-wrap:wrap;">';
                            html += renderAmountInput('cardlink-refund-amount', maxRefundable, maxRefundable);
                            html += '<span>' + currency + '</span>';
                            html += '<button type="button" class="button button-primary" id="cardlink-refund-btn"><?php echo esc_js( __( 'Refund', 'cardlink-payment-gateway' ) ); ?></button>';
                            html += '</div>';
                            html += '<p style="margin:3px 0 0;color:#646970;font-size:11px"><?php echo esc_js( __( 'Max:', 'cardlink-payment-gateway' ) ); ?> ' + maxRefundable.toFixed(2) + ' ' + currency + '</p>';
                        } else {
                            html += '<p style="color:#646970;font-size:12px"><?php echo esc_js( __( 'No refundable amount remaining.', 'cardlink-payment-gateway' ) ); ?></p>';
                        }
                    }
                }

                html += '<div style="margin-top:8px;">';
                html += '<button type="button" class="button" id="cardlink-refresh-btn"><?php echo esc_js( __( 'Refresh', 'cardlink-payment-gateway' ) ); ?></button>';
                html += '</div>';
                html += '</div>';

                $('#cardlink-metabox-content').html(html);
                bindButtons();
            }

            function renderError(msg) {
                var html = '<p style="color:#d63638">' + msg + '</p>';
                html += '<button type="button" class="button" id="cardlink-refresh-btn"><?php echo esc_js( __( 'Retry', 'cardlink-payment-gateway' ) ); ?></button>';
                $('#cardlink-metabox-content').html(html);
                bindButtons();
            }

            function bindButtons() {
                $('#cardlink-capture-btn').on('click', function() {
                    var captureAmount = parseFloat($('#cardlink-capture-amount').val());
                    if (isNaN(captureAmount) || captureAmount <= 0) {
                        alert('<?php echo esc_js( __( 'Please enter a valid capture amount greater than zero.', 'cardlink-payment-gateway' ) ); ?>');
                        return;
                    }
                    var confirmMsg = '<?php echo esc_js( __( 'Capture', 'cardlink-payment-gateway' ) ); ?> ' + captureAmount.toFixed(2) + ' ' + currency + '?';
                    if (!confirm(confirmMsg)) return;
                    $(this).prop('disabled', true).text('<?php echo esc_js( __( 'Processing...', 'cardlink-payment-gateway' ) ); ?>');
                    cardlinkAjax('capture', function(res) {
                        if (res.success && res.data.remaining_capture !== undefined) {
                            remainingCapture = parseFloat(res.data.remaining_capture) || 0;
                            $('#cardlink-remaining-capture').val(remainingCapture.toFixed(2));
                        }
                        alert(res.data.message);
                        loadStatus();
                    }, { capture_amount: captureAmount.toFixed(2) });
                });

                $('#cardlink-void-btn').on('click', function() {
                    if (!confirm('<?php echo esc_js( __( 'Are you sure you want to void this transaction?', 'cardlink-payment-gateway' ) ); ?>')) return;
                    $(this).prop('disabled', true).text('<?php echo esc_js( __( 'Processing...', 'cardlink-payment-gateway' ) ); ?>');
                    cardlinkAjax('void', function(res) {
                        alert(res.data.message);
                        loadStatus();
                    });
                });

                $('#cardlink-refund-btn').on('click', function() {
                    var refundAmount = parseFloat($('#cardlink-refund-amount').val());
                    if (isNaN(refundAmount) || refundAmount <= 0) {
                        alert('<?php echo esc_js( __( 'Please enter a valid refund amount greater than zero.', 'cardlink-payment-gateway' ) ); ?>');
                        return;
                    }
                    if (refundAmount > maxRefundable + 0.001) {
                        alert('<?php echo esc_js( __( 'Refund amount exceeds the remaining refundable balance.', 'cardlink-payment-gateway' ) ); ?>');
                        return;
                    }
                    var confirmMsg = '<?php echo esc_js( __( 'Refund', 'cardlink-payment-gateway' ) ); ?> ' + refundAmount.toFixed(2) + ' ' + currency + '?';
                    if (!confirm(confirmMsg)) return;
                    $(this).prop('disabled', true).text('<?php echo esc_js( __( 'Processing...', 'cardlink-payment-gateway' ) ); ?>');
                    cardlinkAjax('refund', function(res) {
                        if (res.success && res.data.max_refundable !== undefined) {
                            maxRefundable = parseFloat(res.data.max_refundable) || 0;
                            $('#cardlink-max-refundable').val(maxRefundable.toFixed(2));
                        }
                        alert(res.data.message);
                        loadStatus();
                    }, { refund_amount: refundAmount.toFixed(2) });
                });

                $('#cardlink-refresh-btn').on('click', function() {
                    loadStatus();
                });
            }

            function loadStatus() {
                $('#cardlink-metabox-content').html('<p><?php echo esc_js( __( 'Loading transaction info...', 'cardlink-payment-gateway' ) ); ?></p>');
                cardlinkAjax('status', function(res) {
                    if (res.success) {
                        renderStatus(res.data);
                    } else {
                        renderError(res.data.message || '<?php echo esc_js( __( 'Error loading transaction status.', 'cardlink-payment-gateway' ) ); ?>');
                    }
                });
            }

            $(document).ready(function() {
                if (orderId) {
                    loadStatus();
                }
            });
        })(jQuery);
        </script>
        <?php
    }
}
