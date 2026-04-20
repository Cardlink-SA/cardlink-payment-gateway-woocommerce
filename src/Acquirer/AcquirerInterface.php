<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Acquirer;

defined( 'ABSPATH' ) || exit;

interface AcquirerInterface {

    public function get_name(): string;

    public function get_production_url(): string;

    public function get_test_url(): string;

    public function get_url( bool $is_test ): string;

    public function supports_iris(): bool;
}
