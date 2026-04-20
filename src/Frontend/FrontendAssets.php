<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Frontend;

defined( 'ABSPATH' ) || exit;

class FrontendAssets {

    private string $plugin_url;
    private string $version;

    public function __construct( string $plugin_url, string $version ) {
        $this->plugin_url = $plugin_url;
        $this->version    = $version;
    }

    public function enqueue(): void {
        if ( ! is_checkout() && ! is_account_page() ) {
            return;
        }

        wp_enqueue_style(
            'cardlink-payment-gateway-public',
            $this->plugin_url . 'assets/css/public.css',
            [],
            $this->version
        );

        wp_enqueue_script(
            'cardlink-payment-gateway-public',
            $this->plugin_url . 'assets/js/public.js',
            [ 'jquery' ],
            $this->version,
            true
        );

        wp_localize_script( 'cardlink-payment-gateway-public', 'cardlinkGateway', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'home_url' => home_url(),
            'nonce'    => wp_create_nonce( 'cardlink_ajax_nonce' ),
        ] );

    }
}
