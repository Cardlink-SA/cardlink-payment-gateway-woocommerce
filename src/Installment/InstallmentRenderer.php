<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Installment;

defined( 'ABSPATH' ) || exit;

class InstallmentRenderer {

    private InstallmentCalculator $calculator;

    public function __construct( InstallmentCalculator $calculator ) {
        $this->calculator = $calculator;
    }

    /**
     * Render installments dropdown HTML.
     */
    public function render(
        string $gateway_id,
        float $amount,
        int $default_max,
        string $variation_string
    ): string {
        if ( is_add_payment_method_page() ) {
            return '';
        }

        $max = $this->calculator->calculate( $amount, $default_max, $variation_string );

        if ( $max <= 1 ) {
            return '';
        }

        $field_name = esc_attr( $gateway_id ) . '-card-doseis';

        $html  = '<div class="form-row">';
        $html .= '<label for="' . $field_name . '">';
        $html .= esc_html__( 'Choose Installments', 'cardlink-payment-gateway' ) . ' *';
        $html .= '</label>';
        $html .= '<select id="' . $field_name . '" name="' . $field_name . '" class="input-select">';
        $html .= '<option value="1">' . esc_html__( 'Without installments', 'cardlink-payment-gateway' ) . '</option>';

        for ( $i = 2; $i <= $max; $i++ ) {
            $html .= '<option value="' . esc_attr( (string) $i ) . '">' . esc_html( (string) $i ) . '</option>';
        }

        $html .= '</select>';
        $html .= '</div>';

        return $html;
    }
}
