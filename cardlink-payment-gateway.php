<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.cardlink.gr/
 * @since             1.0.0
 * @package           Cardlink_Payment_Gateway
 * @author            Cardlink
 * @copyright         2022 Cardlink
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Cardlink Payment Gateway
 * Plugin URI:        https://www.cardlink.gr/
 * Description:       Cardlink Payment Gateway allows you to accept payment through various schemes such as Visa, Mastercard, Maestro, American Express, Diners, Discover cards on your website.
 * Version:           1.0.4
 * Requires at least: 6.0
 * Author:            Cardlink
 * Author URI:        https://www.cardlink.gr/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       cardlink-payment-gateway
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'CARDLINK_PAYMENT_GATEWAY_VERSION', '1.0.4' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-cardlink-payment-gateway-activator.php
 */
function activate_cardlink_payment_gateway() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-cardlink-payment-gateway-activator.php';
	Cardlink_Payment_Gateway_Activator::activate();
}

/**
 * Add links in plugin page
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'cardlink_payment_gateway_action_links' );
function cardlink_payment_gateway_action_links( $links ) {

	$links[] = '<a href="' . esc_url( get_admin_url( null, 'admin.php?page=wc-settings&tab=checkout&section=cardlink_payment_gateway_woocommerce' ) ) . '">Settings</a>';

	return $links;
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-cardlink-payment-gateway-deactivator.php
 */
function deactivate_cardlink_payment_gateway() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-cardlink-payment-gateway-deactivator.php';
	Cardlink_Payment_Gateway_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_cardlink_payment_gateway' );
register_deactivation_hook( __FILE__, 'deactivate_cardlink_payment_gateway' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-cardlink-payment-gateway.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_cardlink_payment_gateway() {

	$plugin = new Cardlink_Payment_Gateway();
	$plugin->run();

}

$plugin_path = trailingslashit( WP_PLUGIN_DIR ) . 'woocommerce/woocommerce.php';
if (in_array( $plugin_path, wp_get_active_and_valid_plugins() )) {
	run_cardlink_payment_gateway();
}
