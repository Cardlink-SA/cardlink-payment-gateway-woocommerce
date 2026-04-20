<?php
/**
 * Plugin Name:       Cardlink Payment Gateway
 * Plugin URI:        https://www.cardlink.gr/
 * Description:       Cardlink Payment Gateway allows you to accept payment through various schemes such as Visa, Mastercard, Maestro, American Express, Diners, Discover cards on your WooCommerce Powered Site via Cardlink payment gateway.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Cardlink
 * Author URI:        https://www.cardlink.gr/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       cardlink-payment-gateway
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0.0
 * WC tested up to:   9.6
 */

defined( 'ABSPATH' ) || exit;

define( 'FLAVOR_CARDLINK_VERSION', '1.1.0' );
define( 'FLAVOR_CARDLINK_FILE', __FILE__ );
define( 'FLAVOR_CARDLINK_PATH', plugin_dir_path( __FILE__ ) );
define( 'FLAVOR_CARDLINK_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/vendor/autoload.php';

// Declare HPOS compatibility.
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

// Activation / Deactivation.
register_activation_hook( __FILE__, [ \Flavor\CardlinkPaymentGateway\Activator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \Flavor\CardlinkPaymentGateway\Deactivator::class, 'deactivate' ] );

// Settings link on plugins page.
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( array $links ): array {
    $url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=cardlink_payment_gateway_woocommerce' );
    array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'cardlink-payment-gateway' ) . '</a>' );
    return $links;
} );

// Boot the plugin after all plugins are loaded.
add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error is-dismissible"><p>';
            esc_html_e( 'Cardlink Payment Gateway requires WooCommerce to be installed and active.', 'cardlink-payment-gateway' );
            echo '</p></div>';
        } );
        return;
    }

    \Flavor\CardlinkPaymentGateway\Plugin::instance( FLAVOR_CARDLINK_FILE )->init();
}, 10 );
