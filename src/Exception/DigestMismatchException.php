<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Exception;

defined( 'ABSPATH' ) || exit;

class DigestMismatchException extends \RuntimeException {

    public function __construct( string $message = 'Digest verification failed.' ) {
        parent::__construct( $message );
    }
}
