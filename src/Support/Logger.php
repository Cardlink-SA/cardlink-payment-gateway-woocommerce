<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Support;

defined( 'ABSPATH' ) || exit;

class Logger {

    private bool $enabled = false;

    public function set_enabled( bool $enabled ): void {
        $this->enabled = $enabled;
    }

    public function is_enabled(): bool {
        return $this->enabled;
    }

    public function log( string $message, string $level = 'info' ): void {
        if ( ! $this->enabled ) {
            return;
        }

        if ( function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->log( $level, $message, [ 'source' => 'cardlink-payment-gateway' ] );
        }
    }

    public function log_request( array $data ): void {
        $this->log( '---- Cardlink Transaction Request -----' );
        $this->log( 'Data: ' . wp_json_encode( $data, JSON_PRETTY_PRINT ) );
        $this->log( '---- End of Cardlink Transaction Request ----' );
    }

    public function log_response( array $data ): void {
        $this->log( '---- Cardlink Transaction Response -----' );
        $this->log( 'Data: ' . wp_json_encode( $data, JSON_PRETTY_PRINT ) );
        $this->log( '---- End of Cardlink Transaction Response ----' );
    }

    /**
     * Log the digest of an outgoing payment form.
     *
     * Only the Cardlink orderid is logged, never the concatenated fields: they
     * contain the customer's email, phone and address.
     */
    public function log_digest( string $orderid, string $digest ): void {
        $this->log( '---- Cardlink Transaction digest -----' );
        $this->log( 'Order ID: ' . $orderid );
        $this->log( 'Digest: ' . $digest );
        $this->log( '---- End of Cardlink Transaction digest ----' );
    }
}
