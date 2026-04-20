<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Support;

defined( 'ABSPATH' ) || exit;

class SanitizationHelper {

    /**
     * @return string|int|float
     */
    public static function sanitize( string $value, string $type = 'string' ) {
        switch ( $type ) {
            case 'float':
                return (float) $value;
            case 'integer':
            case 'int':
                return (int) $value;
            default:
                return sanitize_text_field( $value );
        }
    }
}
