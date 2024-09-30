<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentResult;
use Automattic\WooCommerce\Blocks\Payments\PaymentContext;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WC_Gateway_Cardlink_Iris_Blocks_Support extends AbstractPaymentMethodType {


	protected $name = 'cardlink_payment_gateway_iris_block';

	public function initialize() {
		$this->settings = get_option('woocommerce_cardlink_payment_gateway_woocommerce_iris_settings', []);
		add_action( 'woocommerce_rest_checkout_process_payment_with_context', [ $this, 'add_payment_request_order_meta' ], 8, 2 );
	}

	public function is_active() {
		return true;
		return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
	}

	public function get_payment_method_script_handles() {
		$plugin_path = plugin_dir_url( dirname( dirname( __FILE__ ) ) );
		$script_asset_path = $plugin_path . '/public/js/cardlink-iris-block.min.asset.php';
		$script_asset      = file_exists( $script_asset_path ) ? require( $script_asset_path ) : array(
			'dependencies' => array(),
			'version'      => CARDLINK_PAYMENT_GATEWAY_VERSION
		);
		$script_url        = $plugin_path . '/public/js/blocks/cardlink-iris-block.min.js';

		wp_register_script( 'wc-cardlink-payments-iris-block', $script_url, $script_asset['dependencies'], $script_asset['version'], true );

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'wc-cardlink-payments-iris-block', 'cardlink-payment-gateway', $plugin_path . '/languages/' );
		}

		return [ 'wc-cardlink-payments-iris-block' ];
	}

	public function get_payment_method_data() {

		return [
			'title'        => $this->get_setting( 'title' ),
			'description'  => $this->get_setting( 'description' ),
			'supports'     => ['products'],
		];
	}

	public function add_payment_request_order_meta( PaymentContext $context, PaymentResult &$result ) {

		$data = $context->payment_data;

		if ( $context->payment_method == 'cardlink_payment_gateway_woocommerce_iris' ) {
			$context->set_payment_data($data);
		}

	}
}
