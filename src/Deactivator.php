<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway;

defined( 'ABSPATH' ) || exit;

class Deactivator {

    public static function deactivate(): void {
        // No cleanup needed on deactivation.
    }
}
