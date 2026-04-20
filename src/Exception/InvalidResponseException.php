<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Exception;

defined( 'ABSPATH' ) || exit;

class InvalidResponseException extends \RuntimeException {

    public function __construct( string $message = 'Invalid payment response.' ) {
        parent::__construct( $message );
    }
}
