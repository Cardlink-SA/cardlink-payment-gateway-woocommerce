<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Acquirer;

defined( 'ABSPATH' ) || exit;

class CardlinkAcquirer implements AcquirerInterface {

    public function get_name(): string {
        return 'Cardlink Checkout';
    }

    public function get_production_url(): string {
        return 'https://ecommerce.cardlink.gr/vpos/shophandlermpi';
    }

    public function get_test_url(): string {
        return 'https://ecommerce-test.cardlink.gr/vpos/shophandlermpi';
    }

    public function get_url( bool $is_test ): string {
        return $is_test ? $this->get_test_url() : $this->get_production_url();
    }

    public function supports_iris(): bool {
        return false;
    }
}
