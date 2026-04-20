<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Admin;

defined( 'ABSPATH' ) || exit;

class AdminAssets {

    private string $plugin_url;
    private string $version;

    public function __construct( string $plugin_url, string $version ) {
        $this->plugin_url = $plugin_url;
        $this->version    = $version;
    }

    public function enqueue(): void {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'woocommerce_page_wc-settings' ) {
            return;
        }

        wp_enqueue_style(
            'cardlink-payment-gateway-admin',
            $this->plugin_url . 'assets/css/admin.css',
            [],
            $this->version
        );

        wp_enqueue_script(
            'cardlink-payment-gateway-admin',
            $this->plugin_url . 'assets/js/admin.js',
            [ 'jquery' ],
            $this->version,
            true
        );

        wp_localize_script( 'cardlink-payment-gateway-admin', 'crlGatewayStrings', [
            'noOfInstallments' => __( 'Number of installments', 'cardlink-payment-gateway' ),
            'totalOrderAmount' => __( 'Total order amount', 'cardlink-payment-gateway' ),
            'addVariation'     => __( 'Add variation', 'cardlink-payment-gateway' ),
        ] );

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin URL param for conditional asset loading.
        $section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
        if ( $section === 'cardlink_payment_gateway_woocommerce_iris' ) {
            wp_enqueue_script(
                'cardlink-iris-admin-settings',
                $this->plugin_url . 'assets/js/iris-admin-settings.js',
                [ 'jquery' ],
                $this->version,
                true
            );
            wp_localize_script( 'cardlink-iris-admin-settings', 'irisAdminSettings', [
                'midRequired'    => __( 'Merchant ID is required when not inheriting from Cardlink Payment Gateway.', 'cardlink-payment-gateway' ),
                'secretRequired' => __( 'Shared Secret Key is required when not inheriting from Cardlink Payment Gateway.', 'cardlink-payment-gateway' ),
            ] );
        }
    }
}
