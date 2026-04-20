<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Acquirer;

defined( 'ABSPATH' ) || exit;

class AcquirerRegistry {

    /** @var AcquirerInterface[] */
    private array $acquirers;

    public function __construct() {
        $this->acquirers = [
            0 => new CardlinkAcquirer(),
            1 => new NexiAcquirer(),
            2 => new WorldlineAcquirer(),
        ];
    }

    public function get( int $index ): AcquirerInterface {
        if ( ! isset( $this->acquirers[ $index ] ) ) {
            return $this->acquirers[0];
        }
        return $this->acquirers[ $index ];
    }

    public function get_url( int $index, bool $is_test ): string {
        return $this->get( $index )->get_url( $is_test );
    }

    /**
     * @return AcquirerInterface[]
     */
    public function get_all(): array {
        return $this->acquirers;
    }

    /**
     * @return array<int, string>
     */
    public function get_names(): array {
        $names = [];
        foreach ( $this->acquirers as $index => $acquirer ) {
            $names[ $index ] = $acquirer->get_name();
        }
        return $names;
    }
}
