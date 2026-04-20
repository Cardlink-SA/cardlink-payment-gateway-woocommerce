<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway;

defined( 'ABSPATH' ) || exit;

use Flavor\CardlinkPaymentGateway\Database\Migrator;

class Activator {

    public static function activate(): void {
        Migrator::create_table();
    }
}
