<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Exception;

defined( 'ABSPATH' ) || exit;

class GatewayConfigException extends \RuntimeException {

    public function __construct( string $message = 'Gateway configuration error.' ) {
        parent::__construct( $message );
    }
}
