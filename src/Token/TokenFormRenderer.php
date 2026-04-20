<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Token;

defined( 'ABSPATH' ) || exit;

use WC_Payment_Tokens;

class TokenFormRenderer {

    private string $plugin_url;

    public function __construct( string $plugin_url ) {
        $this->plugin_url = $plugin_url;
    }

    /**
     * Render saved card selection UI + store checkbox.
     */
    public function render( string $gateway_id, int $user_id ): string {
        if ( ! $user_id || is_add_payment_method_page() ) {
            return '';
        }

        $tokens = WC_Payment_Tokens::get_customer_tokens( $user_id, $gateway_id );
        $html   = '<div class="payment-cards__tokens">';

        if ( ! empty( $tokens ) ) {
            $html .= '<div class="payment-cards">';

            foreach ( $tokens as $token ) {
                $token_id   = $token->get_id();
                $card_type  = strtolower( $token->get_card_type() );
                $last4      = $token->get_last4();
                $exp_month  = $token->get_expiry_month();
                $exp_year   = $token->get_expiry_year();
                $icon       = $this->get_card_icon( $card_type );
                $token_val  = $token->get_token();

                $html .= '<div class="payment-cards__field">';
                $html .= '<label for="card-' . esc_attr( (string) $token_id ) . '">';
                $html .= '<input type="radio" id="card-' . esc_attr( (string) $token_id ) . '" ';
                $html .= 'name="' . esc_attr( $gateway_id ) . '-card" ';
                $html .= 'value="' . esc_attr( $token_val ) . '" checked />';
                $html .= '<span>' . $icon . ' ****' . esc_html( $last4 ) . ' ' . esc_html( $exp_month ) . '/' . esc_html( $exp_year ) . '</span>';
                $html .= '<a href="#" class="remove" data-card-id="card-' . esc_attr( (string) $token_id ) . '">&times;</a>';
                $html .= '</label>';
                $html .= '</div>';
            }

            // New card option.
            $html .= '<div class="payment-cards__field">';
            $html .= '<label for="new-card">';
            $html .= '<input type="radio" id="new-card" name="' . esc_attr( $gateway_id ) . '-card" value="new" />';
            $html .= '<span>' . esc_html__( 'Add your credit card', 'cardlink-payment-gateway' ) . '</span>';
            $html .= '</label>';
            $html .= '</div>';
            $html .= '</div>';

            // Store checkbox (hidden when existing card selected).
            $html .= '<div class="payment-cards-new-card payment-cards__field" style="display:none;">';
            $html .= '<label for="' . esc_attr( $gateway_id ) . '-card-store">';
            $html .= '<input type="checkbox" id="' . esc_attr( $gateway_id ) . '-card-store" ';
            $html .= 'name="' . esc_attr( $gateway_id ) . '-card-store" />';
            $html .= '<span>' . esc_html__( 'Store your card?', 'cardlink-payment-gateway' ) . '</span>';
            $html .= '</label>';
            $html .= '</div>';
        } else {
            // No saved cards - just show store checkbox.
            $html .= '<div class="payment-cards-new-card payment-cards__field">';
            $html .= '<label for="' . esc_attr( $gateway_id ) . '-card-store">';
            $html .= '<input type="checkbox" id="' . esc_attr( $gateway_id ) . '-card-store" ';
            $html .= 'name="' . esc_attr( $gateway_id ) . '-card-store" />';
            $html .= '<span>' . esc_html__( 'Store your card?', 'cardlink-payment-gateway' ) . '</span>';
            $html .= '</label>';
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    private function get_card_icon( string $card_type ): string {
        $img_path = $this->plugin_url . 'assets/img/';

        switch ( $card_type ) {
            case 'mastercard':
                return '<img src="' . esc_url( $img_path . 'mastercard.png' ) . '" alt="Mastercard" class="card-icon" />';
            case 'visa':
                return '<img src="' . esc_url( $img_path . 'visa.png' ) . '" alt="Visa" class="card-icon" />';
            default:
                return esc_html( ucfirst( $card_type ) );
        }
    }
}
