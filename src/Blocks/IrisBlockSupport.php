<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Blocks;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class IrisBlockSupport extends AbstractPaymentMethodType {

    protected $name = 'cardlink_payment_gateway_iris_block';

    private string $plugin_path;
    private string $plugin_url;
    private string $version;
    private array $gateway_settings = [];

    public function __construct( string $plugin_path, string $plugin_url, string $version ) {
        $this->plugin_path = $plugin_path;
        $this->plugin_url  = $plugin_url;
        $this->version     = $version;
    }

    public function initialize(): void {
        $this->gateway_settings = get_option( 'woocommerce_cardlink_payment_gateway_woocommerce_iris_settings', [] );
    }

    public function is_active(): bool {
        return ( $this->gateway_settings['enabled'] ?? 'no' ) === 'yes';
    }

    public function get_payment_method_script_handles(): array {
        $asset_path = $this->plugin_path . 'assets/js/blocks/build/iris-block.min.asset.php';
        $asset      = file_exists( $asset_path ) ? require $asset_path : [
            'dependencies' => [],
            'version'      => $this->version,
        ];

        wp_register_script(
            'wc-cardlink-payments-iris-block',
            $this->plugin_url . 'assets/js/blocks/build/iris-block.min.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        if ( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations( 'wc-cardlink-payments-iris-block', 'cardlink-payment-gateway' );
        }

        return [ 'wc-cardlink-payments-iris-block' ];
    }

    public function get_payment_method_data(): array {
        return [
            'title'       => $this->gateway_settings['title'] ?? '',
            'description' => $this->gateway_settings['description'] ?? '',
            'supports'    => [ 'products' ],
        ];
    }
}
