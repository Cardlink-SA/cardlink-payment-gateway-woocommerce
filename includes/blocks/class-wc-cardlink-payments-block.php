<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentResult;
use Automattic\WooCommerce\Blocks\Payments\PaymentContext;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WC_Gateway_Cardlink_Blocks_Support extends AbstractPaymentMethodType {


	protected $name = 'cardlink_payment_gateway_block';

	public function initialize() {
		$this->settings = get_option('woocommerce_cardlink_payment_gateway_woocommerce_settings', []);
		add_action( 'woocommerce_rest_checkout_process_payment_with_context', [ $this, 'add_payment_request_order_meta' ], 8, 2 );
	}

	public function is_active() {
		return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
	}

	public function get_payment_method_script_handles() {
		$plugin_path = plugin_dir_url( dirname( dirname( __FILE__ ) ) );
		$script_asset_path = $plugin_path . '/public/js/cardlink-block.min.asset.php';
		$script_asset      = file_exists( $script_asset_path ) ? require( $script_asset_path ) : array(
			'dependencies' => array(),
			'version'      => CARDLINK_PAYMENT_GATEWAY_VERSION
		);
		$script_url        = $plugin_path . '/public/js/blocks/cardlink-block.min.js';

		wp_register_script( 'wc-cardlink-payments-block', $script_url, $script_asset['dependencies'], $script_asset['version'], true );

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'wc-cardlink-payments-block', 'cardlink-payment-gateway', $plugin_path . '/languages/' );
		}

		return [ 'wc-cardlink-payments-block' ];
	}

	public function get_payment_method_data() {

		$installments_variation_data = $this->get_setting( 'installments_variation' );
		$installments_variation = null;
		if ($installments_variation_data) {
			$installments_split = explode( ',', $installments_variation_data );
			foreach ( $installments_split as $key => $value ) {
				$installment = explode( ':', $value );
				if ( is_array( $installment ) && count( $installment ) != 2 ) {
					// not valid rule for installments
					continue;
				}
				$amount = (float) $installment[0];
				$max_installments = (int) $installment[1];
				$installments_variation[$amount] = $max_installments;
			}
		}

		return [
			'title'        => $this->get_setting( 'title' ),
			'description'  => $this->get_setting( 'description' ),
			'supports'     => ['products'],
			'tokenization' => $this->get_setting( 'tokenization' ) == 'yes',
			'installments' => abs( $this->get_setting( 'installments' ) ),
			'installment_variations' => $installments_variation,
		];
	}

	public function add_payment_request_order_meta( PaymentContext $context, PaymentResult &$result ) {

		$data = $context->payment_data;

		if ( $context->payment_method == 'cardlink_payment_gateway_woocommerce' ) {
			$payment_method = $context->payment_method;
			$maybe_store_card = $context->payment_data['wc-' . $payment_method . '-new-payment-method'];
			$data[$payment_method . '-card-store']  = $maybe_store_card;
			$data[$payment_method . '-card-doseis'] = $context->payment_data['installmentsvalue'];
			if (array_key_exists('token', $data)) {
				$token_id    = $data['token'];
				$token_class = new WC_Payment_Token_Data_Store;
				$token       = $token_class->get_token_by_id( $token_id );
				$data[$payment_method . '-card'] = $token->token;
			}
			$context->set_payment_data($data);
		}

	}
}
