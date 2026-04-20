<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Acquirer;

defined( 'ABSPATH' ) || exit;

class NexiAcquirer implements AcquirerInterface {

    public function get_name(): string {
        return 'Nexi Checkout';
    }

    public function get_production_url(): string {
        return 'https://www.alphaecommerce.gr/vpos/shophandlermpi';
    }

    public function get_test_url(): string {
        return 'https://alphaecommerce-test.cardlink.gr/vpos/shophandlermpi';
    }

    public function get_url( bool $is_test ): string {
        return $is_test ? $this->get_test_url() : $this->get_production_url();
    }

    public function supports_iris(): bool {
        return true;
    }
}
