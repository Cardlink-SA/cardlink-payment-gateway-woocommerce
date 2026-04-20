<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Installment;

defined( 'ABSPATH' ) || exit;

class InstallmentCalculator {

    /**
     * Parse a variation string like "100:3,200:6,500:12" into rules.
     *
     * @return array<int, array{amount: float, max: int}>
     */
    public function parse_variation_rules( string $variation_string ): array {
        $rules = [];

        if ( empty( $variation_string ) ) {
            return $rules;
        }

        $parts = explode( ',', $variation_string );

        foreach ( $parts as $part ) {
            $pair = explode( ':', trim( $part ) );
            if ( count( $pair ) !== 2 || ! is_numeric( $pair[0] ) || ! is_numeric( $pair[1] ) ) {
                continue;
            }
            $rules[] = [
                'amount' => (float) $pair[0],
                'max'    => (int) $pair[1],
            ];
        }

        return $rules;
    }

    /**
     * Calculate maximum installments for a given order amount.
     */
    public function calculate( float $amount, int $default_max, string $variation_string ): int {
        $rules = $this->parse_variation_rules( $variation_string );

        if ( empty( $rules ) ) {
            return $default_max;
        }

        $max = $default_max;

        foreach ( $rules as $rule ) {
            if ( $amount >= $rule['amount'] ) {
                $max = $rule['max'];
            }
        }

        return $max;
    }
}
