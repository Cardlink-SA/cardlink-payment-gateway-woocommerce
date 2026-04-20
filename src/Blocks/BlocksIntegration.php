<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Blocks;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

class BlocksIntegration {

    private string $plugin_path;
    private string $plugin_url;
    private string $version;

    public function __construct( string $plugin_path, string $plugin_url, string $version ) {
        $this->plugin_path = $plugin_path;
        $this->plugin_url  = $plugin_url;
        $this->version     = $version;
    }

    public function register(): void {
        if ( ! class_exists( PaymentMethodRegistry::class ) ) {
            return;
        }

        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function ( PaymentMethodRegistry $registry ) {
                $registry->register( new CardlinkBlockSupport( $this->plugin_path, $this->plugin_url, $this->version ) );
                $registry->register( new IrisBlockSupport( $this->plugin_path, $this->plugin_url, $this->version ) );
            }
        );
    }
}
