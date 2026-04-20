<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Payment;

defined( 'ABSPATH' ) || exit;

use Flavor\CardlinkPaymentGateway\Exception\DigestMismatchException;

class DigestService {

    public function compute( string $data ): string {
        return base64_encode( hash( 'sha256', $data, true ) );
    }

    public function compute_for_fields( array $fields, string $secret ): string {
        $concatenated = implode( '', $fields );
        $data         = $this->ensure_utf8( $concatenated ) . $secret;
        return $this->compute( $data );
    }

    public function verify( string $expected, string $actual_data ): bool {
        $computed = $this->compute( $actual_data );
        return hash_equals( $expected, $computed );
    }

    /**
     * Verify the main digest from a Cardlink response.
     *
     * @throws DigestMismatchException
     */
    public function verify_response( array $post_data, string $secret ): void {
        $excluded_keys = [
            '_charset_', 'digest', 'submitButton',
            'xlsbonusadjamt', 'xlsbonustxid', 'xlsbonusstatus',
            'xlsbonusdetails', 'xlsbonusdigest',
        ];

        $values = '';
        foreach ( $post_data as $key => $value ) {
            if ( in_array( $key, $excluded_keys, true ) ) {
                continue;
            }
            $values .= $value;
        }

        $data     = $this->ensure_utf8( $values ) . $secret;
        $computed = $this->compute( $data );
        $received = $post_data['digest'] ?? '';

        if ( ! hash_equals( $computed, $received ) ) {
            throw new DigestMismatchException(
                sprintf( 'Digest mismatch. Expected: %s, Got: %s', $computed, $received )
            );
        }
    }

    /**
     * Verify the XLS bonus digest if present.
     *
     * @throws DigestMismatchException
     */
    public function verify_bonus_digest( array $post_data, string $secret ): void {
        $bonus_digest = $post_data['xlsbonusdigest'] ?? '';
        if ( empty( $bonus_digest ) ) {
            return;
        }

        $bonus_fields = [
            $post_data['xlsbonusadjamt'] ?? '',
            $post_data['xlsbonustxid'] ?? '',
            $post_data['xlsbonusstatus'] ?? '',
            $post_data['xlsbonusdetails'] ?? '',
        ];

        $data     = $this->ensure_utf8( implode( '', $bonus_fields ) ) . $secret;
        $computed = $this->compute( $data );

        if ( ! hash_equals( $computed, $bonus_digest ) ) {
            throw new DigestMismatchException( 'Bonus digest mismatch.' );
        }
    }

    private function ensure_utf8( string $text ): string {
        $encoding = mb_detect_encoding( $text, mb_detect_order(), true );
        if ( $encoding && $encoding !== 'UTF-8' ) {
            $converted = iconv( $encoding, 'UTF-8', $text );
            if ( false !== $converted ) {
                return $converted;
            }
        }
        return $text;
    }
}
