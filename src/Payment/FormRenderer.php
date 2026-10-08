<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Payment;

defined( 'ABSPATH' ) || exit;

class FormRenderer {

    /**
     * Render the payment form HTML with hidden fields.
     */
    public function render_payment_form(
        string $post_url,
        array $form_fields,
        string $digest,
        bool $use_iframe,
        string $gateway_id,
        int $order_id,
        bool $auto_submit = true
    ): string {
        $target = $use_iframe ? 'payment_iframe' : '_top';

        $html = '<form action="' . esc_url( $post_url ) . '" method="POST" id="payment_form" target="' . esc_attr( $target ) . '">';

        foreach ( $form_fields as $key => $value ) {
            $html .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $this->encode_value( (string) $value ) ) . '"/>';
        }

        $html .= '<input type="hidden" name="digest" value="' . esc_attr( $digest ) . '"/>';

        // Submit button: fallback while auto-submitting, the only way to pay otherwise.
        $html .= '<div class="payment_buttons">';
        $html .= '<input type="submit" class="button alt" id="submit_payment_form" value="' . esc_attr__( 'Pay via Cardlink', 'cardlink-payment-gateway' ) . '"/>';
        $html .= '</div>';

        if ( $use_iframe ) {
            $html .= $this->render_iframe_html( $gateway_id, $order_id, ! $auto_submit );
        }

        $html .= '</form>';

        if ( $auto_submit ) {
            $html .= $this->render_auto_submit_js( $use_iframe );
        } elseif ( $use_iframe ) {
            $html .= $this->render_show_modal_on_submit_js( $gateway_id );
        }

        return $html;
    }

    /**
     * Render the tokenizer redirect form.
     */
    public function render_tokenizer_form( string $post_url, array $form_fields ): string {
        $html = '<form action="' . esc_url( $post_url ) . '" method="POST" id="payment_form" target="_top">';

        foreach ( $form_fields as $key => $value ) {
            $html .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $this->encode_value( (string) $value ) ) . '"/>';
        }

        $html .= '</form>';
        $html .= $this->render_auto_submit_js( false );

        return $html;
    }

    private function encode_value( string $value ): string {
        $encoding = mb_detect_encoding( $value, mb_detect_order(), true );
        if ( $encoding && $encoding !== 'UTF-8' ) {
            $converted = iconv( $encoding, 'UTF-8', $value );
            if ( false !== $converted ) {
                return $converted;
            }
        }
        return $value;
    }

    private function render_iframe_html( string $gateway_id, int $order_id, bool $hidden = false ): string {
        $html  = '<div class="' . esc_attr( $gateway_id ) . '_modal"' . ( $hidden ? ' style="display:none"' : '' ) . '>';
        $html .= '<div class="' . esc_attr( $gateway_id ) . '_modal_wrapper">';
        $html .= '<iframe name="payment_iframe" id="payment_iframe" data-order-id="' . esc_attr( (string) $order_id ) . '"></iframe>';
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }

    /**
     * Open the (initially hidden) iframe modal when the customer submits the form manually.
     */
    private function render_show_modal_on_submit_js( string $gateway_id ): string {
        $js  = '<script type="text/javascript">';
        $js .= 'jQuery(function($){';
        $js .= '$("#payment_form").on("submit", function(){ $(".' . esc_js( $gateway_id ) . '_modal").css("display", "flex"); });';
        $js .= '});';
        $js .= '</script>';
        return $js;
    }

    private function render_auto_submit_js( bool $use_iframe ): string {
        $message = esc_js( __( 'Thank you for your order. We are now redirecting you to make payment.', 'cardlink-payment-gateway' ) );

        $js = '<script type="text/javascript">';

        if ( ! $use_iframe ) {
            $js .= 'jQuery(function($){';
            $js .= '$.blockUI({message: "' . $message . '", baseZ: 99999, overlayCSS: {background: "#fff", opacity: 0.6}, css: {padding: "20px", zIndex: "9999999", textAlign: "center", color: "#555", border: "3px solid #aaa", backgroundColor: "#fff", cursor: "wait", lineHeight: "24px"}});';
            $js .= '$("#payment_form").submit();';
            $js .= '});';
        } else {
            $js .= 'jQuery(function($){';
            $js .= '$("#submit_payment_form").hide();';
            $js .= '$("#payment_form").submit();';
            $js .= '});';
        }

        $js .= '</script>';
        return $js;
    }
}
