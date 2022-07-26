<?php


class Cardlink_Payment_Gateway_Delete {

	public static $instance = null;

	public static function get_instance() {
		null === self::$instance AND self::$instance = new self();

		return self::$instance;
	}

	private $api_request_url = 'Cardlink_Payment_Gateway_Woocommerce';
	private $success_url;
	private $fail_url;
	private $redirect_page_id;
	private $payment_gateway_instance;

	public function __construct() {

		$this->success_url              = get_site_url() . "/?wc-api=" . $this->api_request_url . "&result=success";
		$this->fail_url                 = get_site_url() . "/?wc-api=" . $this->api_request_url . "&result=success";
		$payment_gateways               = WC_Payment_Gateways::instance();
		$this->payment_gateway_instance = $payment_gateways->payment_gateways()['cardlink_payment_gateway_woocommerce'];
		$this->redirect_page_id         = $this->payment_gateway_instance->get_option( 'redirect_page_id' );

		add_action( 'wp_ajax_delete_token', array( &$this, 'delete_token' ) );
		add_action( 'wp_ajax_set_redirection_status', array( &$this, 'set_redirection_status' ) );
		add_action( 'wp_ajax_nopriv_set_redirection_status', array( &$this, 'set_redirection_status' ) );
		add_action( 'wp_ajax_check_order_status', array( &$this, 'check_order_status' ) );
		add_action( 'wp_ajax_nopriv_check_order_status', array( &$this, 'check_order_status' ) );
	}

	function respond( $status, $response ) {
		wp_die( json_encode( array(
			'status'   => $status,
			'response' => $response
		) ) );
	}

	public function set_redirection_status() {

		$order_id = $_POST['order_id'];
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			self::respond( false, false );
		}

		update_post_meta( $order_id, 'redirected_for_payment', true );

		self::respond( true, true );
	}

	public function check_order_status() {

		$order_id = $_POST['order_id'];
		$response = [
			'redirect_url' => false,
			'redirected'   => get_post_meta( $order_id, 'redirected_for_payment', true ),
		];

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			self::respond( false, $response );
		}

		if ( $response['redirected'] !== '1' ) {
			if ( $this->redirect_page_id == "-1" ) {
				$response['redirect_url'] = $this->payment_gateway_instance->get_return_url( $order );
			} else {
				$response['redirect_url'] = ( $this->redirect_page_id == "" || $this->redirect_page_id == 0 ) ? $this->payment_gateway_instance->get_return_url( $order ) : get_permalink( $this->redirect_page_id );
			}
		}
		self::respond( true, $response );
	}

	function delete_token() {
		$selected_card_id    = $_POST['params']['selected_card_id'];
		$selected_card_value = $_POST['params']['selected_card_value'];

		$parsedData = [];

		$parsedData['selected_card_id']    = intval( substr( $selected_card_id, 5 ) );
		$parsedData['selected_card_value'] = $selected_card_value;
		$updateTokens                      = self::update_tokens( $parsedData );

		if ( ! empty( $updateTokens['payment_cards_html'] ) ) {
			$status = 'success';
			self::respond( $status, $updateTokens );
		} else {
			$status = 'error';
			self::respond( $status, __( 'No results' ) );
		}
	}

	static function update_tokens( $parsedData ) {

		$response = [];

		WC_Payment_Tokens::delete( $parsedData['selected_card_id'] );

		$Cardlink_Payment_Gateway       = new Cardlink_Payment_Gateway_Woocommerce();
		$response['payment_cards_html'] = $Cardlink_Payment_Gateway->get_payment_cards_html();

		return $response;
	}

}