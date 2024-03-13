<?php

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	return;
}


/**
 *  Gateway Class Helper
 */
class Cardlink_Payment_Gateway_Woocommerce_Helper {

	static function get_acquirers() {
		return [
			'Cardlink Checkout',
			'Nexi Checkout',
			'Worldline Greece Checkout'
		];
	}

	static function calculate_digest( $input ) {
		$digest = base64_encode( hash( 'sha256', ( $input ), true ) );

		return $digest;
	}

	static function get_post_url( $environment, $acquirer ) {
		if ( $environment == "yes" ) {
			switch ( $acquirer ) {
				case 0 :
					return $post_url = "https://ecommerce-test.cardlink.gr/vpos/shophandlermpi";
				case 1 :
					return $post_url = "https://alphaecommerce-test.cardlink.gr/vpos/shophandlermpi";
				case 2 :
					return $post_url = "https://eurocommerce-test.cardlink.gr/vpos/shophandlermpi";
			}
		} else {
			switch ( $acquirer ) {
				case 0 :
					return $post_url = "https://ecommerce.cardlink.gr/vpos/shophandlermpi";
				case 1 :
					return $post_url = "https://www.alphaecommerce.gr/vpos/shophandlermpi";
				case 2 :
					return $post_url = "https://vpos.eurocommerce.gr/vpos/shophandlermpi";
			}
		}
	}

	static function generic_add_meta( $orderid, $key, $value ) {
		$order = new WC_Order( $orderid );
		if ( method_exists( $order, 'add_meta_data' ) && method_exists( $order, 'save_meta_data' ) ) {
			$order->add_meta_data( $key, $value, true );
			$order->save_meta_data();
		} else {
			update_post_meta( $orderid, $key, $value );
		}
	}

	/**
	 * Verify a successful Payment!
	 * */
	static function check_response( $post_data, $enable_log, $shared_secret_key, $order_note, $id ) {

		global $woocommerce;


		if ( $enable_log == 'yes' ) {
			error_log( '---- eCommerce Response -----' );
			error_log( print_r( $post_data, true ) );
			error_log( '---- End of eCommerce Response ----' );
		}

		$mid = filter_var( $post_data['mid'], FILTER_SANITIZE_NUMBER_INT );

		$orderid_session = WC()->session->get( 'order_id' );
		$orderid_post    = filter_var( $post_data['orderid'], FILTER_SANITIZE_STRING );

		$reg = preg_match( '/^(.*?)at/', $orderid = $orderid_post, $matches );

		if ( ! empty( $matches ) ) {
			$orderid = $matches[1];
		} else {
			$orderid = $orderid_session;
		}

		if ( $orderid == '' ) {
			$orderid = $orderid_post;
			error_log( "Cardlink: something went wrong with order id " );
			error_log( print_r( $post_data, true ) );
			error_log( print_r( $matches, true ) );
			error_log( $orderid_session );
		}

		$status         = filter_var( $post_data['status'], FILTER_SANITIZE_STRING );
		$orderAmount    = filter_var( $post_data['orderAmount'], FILTER_SANITIZE_NUMBER_FLOAT );
		$currency       = filter_var( $post_data['currency'], FILTER_SANITIZE_STRING );
		$paymentTotal   = filter_var( $post_data['paymentTotal'], FILTER_SANITIZE_NUMBER_FLOAT );
		$message        = isset( $post_data['message'] ) ? filter_var( $post_data['message'], FILTER_SANITIZE_STRING ) : '';
		$riskScore      = isset( $post_data['riskScore'] ) ? filter_var( $post_data['riskScore'], FILTER_SANITIZE_NUMBER_FLOAT ) : '';
		$payMethod      = isset( $post_data['payMethod'] ) ? filter_var( $post_data['payMethod'], FILTER_SANITIZE_STRING ) : '';
		$txId           = isset( $post_data['txId'] ) ? filter_var( $post_data['txId'], FILTER_SANITIZE_NUMBER_FLOAT ) : '';
		$paymentRef     = isset( $post_data['paymentRef'] ) ? filter_var( $post_data['paymentRef'], FILTER_SANITIZE_STRING ) : '';
		$extToken       = isset( $post_data['extToken'] ) ? filter_var( $post_data['extToken'], FILTER_SANITIZE_STRING ) : '';
		$extTokenPanEnd = isset( $post_data['extTokenPanEnd'] ) ? filter_var( $post_data['extTokenPanEnd'], FILTER_SANITIZE_STRING ) : '';
		$extTokenExp    = isset( $post_data['extTokenExp'] ) ? $post_data['extTokenExp'] : '';
		$digest         = filter_var( $post_data['digest'], FILTER_SANITIZE_STRING );
		$xlsbonusdigest = '';
		if( array_key_exists( 'xlsbonusdigest', $post_data ) ){
			$xlsbonusdigest     = filter_var( $post_data['xlsbonusdigest'], FILTER_SANITIZE_STRING );
		}
		$extTokenExpYear  = substr( $extTokenExp, 0, 4 );
		$extTokenExpMonth = substr( $extTokenExp, 4, 2 );

		$form_data = '';
		$form_data_bonus = '';
		foreach ( $post_data as $k => $v ) {
			if ( ! in_array( $k, array( '_charset_', 'digest', 'submitButton', 'xlsbonusadjamt', 'xlsbonustxid', 'xlsbonusstatus', 'xlsbonusdetails', 'xlsbonusdigest' ) ) ) {
				$form_data .= filter_var( $post_data[ $k ], FILTER_SANITIZE_STRING );
			}
			if ( in_array( $k, array( 'xlsbonusadjamt', 'xlsbonustxid', 'xlsbonusstatus', 'xlsbonusdetails' ) ) ) {
				$form_data_bonus .= filter_var( $post_data[ $k ], FILTER_SANITIZE_STRING );
			}
		}		

		$form_data       		.= $shared_secret_key;
		$computed_digest 		= self::calculate_digest( $form_data );
		$form_data_bonus    	.= $shared_secret_key;
		$computed_digest_bonus 	= self::calculate_digest( $form_data_bonus );

		$order           = new WC_Order( $orderid );
		$current_user_id = $order->get_user_id();
		$message         = array( 'message' => '', 'message_type' => '' );

		$failed = true;
		if ( $digest == $computed_digest ){
			$failed = false;
		}
		if( $xlsbonusdigest != '' ){
			if ( $xlsbonusdigest == $computed_digest_bonus ){
				$failed = false;
			}else{
				$failed = true;
			}
		}

		if ( $failed ) {
			$message      = __( 'A technical problem occured. <br />The transaction wasn\'t successful, payment wasn\'t received.', 'cardlink-payment-gateway' );
			$message_type = 'error';
			$message      = array( 'message' => $message, 'message_type' => $message_type );
			self::generic_add_meta( $orderid, '_cardlink_message', $message );
			$order->update_status( 'failed', 'DIGEST' );
			if ( version_compare( WOOCOMMERCE_VERSION, '2.5', '<' ) ) {
				$checkout_url = $woocommerce->cart->get_checkout_url();
			} else {
				$checkout_url = wc_get_checkout_url();
			}
			wp_redirect( $checkout_url );
			exit;
		}

		update_post_meta( $orderid, 'redirected_for_payment', false );

		if ( $status == 'CAPTURED' || $status == 'AUTHORIZED' ) {
			$order->payment_complete( $paymentRef );

			if ( $order->get_status() == 'processing' ) {

				$order->add_order_note( __( 'Payment Via Cardlink<br />Transaction ID: ', 'cardlink-payment-gateway' ) . $paymentRef );
				$message = __( 'Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is currently being processed.', 'cardlink-payment-gateway' );

				if ( $order_note == 'yes' ) {
					$order->add_order_note( __( 'Payment Received.<br />Your order is currently being processed.<br />We will be shipping your order to you soon.<br />Cardlink ID: ', 'cardlink-payment-gateway' ) . $paymentRef, 1 );

				}
			} else if ( $order->get_status() == 'completed' ) {
				$message = __( 'Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is now complete.', 'cardlink-payment-gateway' );
				if ( $order_note == 'yes' ) {
					$order->add_order_note( __( 'Payment Received.<br />Your order is now complete.<br />Cardlink Transaction ID: ', 'cardlink-payment-gateway' ) . $paymentRef, 1 );
				}
			}

			$tokens      = WC_Payment_Tokens::get_customer_tokens( $current_user_id, $id );
			$token_class = new WC_Payment_Token_Data_Store;
			$card_exist  = false;
			foreach ( $tokens as $key => $tok ) {
				$token_meta = $token_class->get_metadata( $key );
				if ( $token_meta['card_type'][0] == $payMethod && $token_meta['last4'][0] == $extTokenPanEnd && $token_meta['expiry_year'][0] == $extTokenExpYear && $token_meta['expiry_month'][0] == $extTokenExpMonth ) {
					$card_exist = true;
				}
			}

			if ( $extToken && ! $card_exist ) {
				// Build the token
				$token = new WC_Payment_Token_CC();
				$token->set_token( $extToken ); // Token comes from payment processor
				$token->set_gateway_id( $id );
				$token->set_last4( $extTokenPanEnd );
				$token->set_expiry_year( $extTokenExpYear );
				$token->set_expiry_month( $extTokenExpMonth );
				$token->set_card_type( $payMethod );
				$token->set_user_id( $current_user_id );
				// Save the new token to the database
				$token->save();
				// Set this token as the users new default token
				WC_Payment_Tokens::set_users_default( $current_user_id, $token->get_id() );
			}

			$message_type = 'success';

			$message = array( 'message' => $message, 'message_type' => $message_type );

			self::generic_add_meta( $orderid, '_cardlink_message', $message );

			WC()->cart->empty_cart();

		} else if ( $status == 'CANCELED' ) {
			$message = array(
				'message'      => __( 'Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment was cancelled.', 'cardlink-payment-gateway' ),
				'message_type' => 'notice'
			);
			self::generic_add_meta( $orderid, '_cardlink_message', $message );
			$order->update_status( 'failed', 'ERROR ' . $message['message'] );

		} else if ( $status == 'REFUSED' ) {
			$client_message = __( 'Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment wasn\'t received.', 'cardlink-payment-gateway' );
			$message_type   = 'error';
			$message        = array( 'message' => $client_message, 'message_type' => $message_type );
			self::generic_add_meta( $orderid, '_cardlink_message', $message );
			$order->update_status( 'failed', 'REFUSED ' . $message );
		} else if ( $status == 'ERROR' ) {
			$client_message = __( 'Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment wasn\'t received.', 'cardlink-payment-gateway' );
			$message_type   = 'error';
			$message        = array( 'message' => $client_message, 'message_type' => $message_type );
			self::generic_add_meta( $orderid, '_cardlink_message', $message );
			$order->update_status( 'failed', 'ERROR ' . $message );
		} else {
			$client_message = __( 'Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment wasn\'t received.', 'cardlink-payment-gateway' );
			$message_type   = 'error';
			$message        = array( 'message' => $client_message, 'message_type' => $message_type );
			self::generic_add_meta( $orderid, '_cardlink_message', $message );
			$order->update_status( 'failed', 'Unknown: ' . $message );
		}

		return $order;
	}
	static function process_payment($order_id, $post_data, $method_id) {
		$order  = new WC_Order( $order_id );
		$doseis = isset( $post_data[ esc_attr( $method_id ) . '-card-doseis' ] ) ? intval( $post_data[ esc_attr( $method_id ) . '-card-doseis' ] ) : '';
		if ( $doseis > 0 ) {
			self::generic_add_meta( $order_id, '_doseis', $doseis );
		}

		$store_card = isset( $post_data[ esc_attr( $method_id ) . '-card-store' ] ) ? intval( $post_data[ esc_attr( $method_id ) . '-card-store' ] ) : 0;
		self::generic_add_meta( $order_id, '_cardlink_store_card', $store_card );

		$selected_card = isset( $post_data[ esc_attr( $method_id ) . '-card' ] ) ? intval( $post_data[ esc_attr( $method_id ) . '-card' ] ) : 0;
		self::generic_add_meta( $order_id, '_cardlink_card', $selected_card );

		$current_version = get_option( 'woocommerce_version', null );
		if ( version_compare( $current_version, '2.2.0', '<' ) ) { //older version
			return array(
				'result'   => 'success',
				'redirect' => add_query_arg( 'order', $order->id, add_query_arg( 'key', $order->order_key, get_permalink( woocommerce_get_page_id( 'pay' ) ) ) )
			);
		} else if ( version_compare( $current_version, '2.4.0', '<' ) ) { //older version
			return array
			(
				'result'   => 'success',
				'redirect' => add_query_arg( 'order-pay', $order->id, add_query_arg( 'key', $order->order_key, get_permalink( woocommerce_get_page_id( 'pay' ) ) ) )
			);
		} else if ( version_compare( $current_version, '3.0.0', '<' ) ) { //older version
			return array
			(
				'result'   => 'success',
				'redirect' => add_query_arg( 'order-pay', $order->id, add_query_arg( 'key', $order->order_key, wc_get_page_permalink( 'checkout' ) ) )
			);
		} else {
			return array(
				'result'   => 'success',
				'redirect' => add_query_arg( 'order-pay', $order->get_id(), add_query_arg( 'key', $order->get_order_key(), wc_get_page_permalink( 'checkout' ) ) )
			);
		}
	}

}

/**
 *  Gateway Class
 */
class Cardlink_Payment_Gateway_Woocommerce extends WC_Payment_Gateway {

	static $_instance;
	public $id;
	public $has_fields;
	public $notify_url;
	public $method_description;
	public $redirect_page_id;
	public $method_title;
	public $title;
	public $description;
	public $merchant_id;
	public $shared_secret_key;
	public $environment;
	public $acquirer;
	public $tokenization;
	public $installments;
	public $installments_variation;
	public $transaction_type;
	public $css_url;
	public $popup;
	public $enable_log;
	public $order_note;

	public $api_request_url = 'Cardlink_Payment_Gateway_Woocommerce';
	public $table_name = 'cardlink_gateway_transactions';

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct() {

		$this->id                 = 'cardlink_payment_gateway_woocommerce';
		$this->has_fields         = true;
		$this->method_title       = __( 'Cardlink Payment Gateway', 'cardlink-payment-gateway' );
		$this->method_description = __( 'Cardlink Payment Gateway allows you to accept payment through various schemes such as Visa, Mastercard, Maestro, American Express, Diners, Discover cards on your Woocommerce Powered Site.', 'cardlink-payment-gateway' );
		$this->notify_url         = WC()->api_request_url( $this->api_request_url );
		$this->redirect_page_id   = $this->get_option( 'redirect_page_id' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings
		$this->init_settings();

		// Define User set Variables
		$this->title                  = sanitize_text_field( $this->get_option( 'title' ) );
		$this->description            = sanitize_text_field( $this->get_option( 'description' ) );
		$this->merchant_id            = sanitize_text_field( $this->get_option( 'merchant_id' ) );
		$this->shared_secret_key      = sanitize_text_field( $this->get_option( 'shared_secret_key' ) );
		$this->environment            = sanitize_text_field( $this->get_option( 'environment' ) );
		$this->acquirer               = sanitize_text_field( $this->get_option( 'acquirer' ) );
		$this->tokenization           = sanitize_text_field( $this->get_option( 'tokenization' ) );
		$this->installments           = absint( $this->get_option( 'installments' ) );
		$this->installments_variation = sanitize_text_field( $this->get_option( 'installments_variation' ) );
		$this->transaction_type       = sanitize_text_field( $this->get_option( 'transaction_type' ) );
		$this->popup                  = sanitize_text_field( $this->get_option( 'popup' ) );
		$this->css_url                = sanitize_text_field( $this->get_option( 'css_url' ) );
		$this->enable_log             = sanitize_text_field( $this->get_option( 'enable_log' ) );
		$this->order_note             = $this->get_option( 'order_note' );

		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );

		add_action( 'woocommerce_api_' . strtolower( $this->api_request_url ), array(
			$this,
			'check_cardlink_response'
		) );

		if ( $this->acquirer == 0 ) {
			$this->icon = apply_filters( 'cardlink_icon', plugins_url() . '/cardlink-payment-gateway-woocommerce/public/img/cardlink.png' );
		} elseif ( $this->acquirer == 1 ) {
			$this->icon = apply_filters( 'cardlink_icon', plugins_url() . '/cardlink-payment-gateway-woocommerce/public/img/cardlink.png' );
		} elseif ( $this->acquirer == 2 ) {
			$this->icon = apply_filters( 'cardlink_icon', plugins_url() . '/cardlink-payment-gateway-woocommerce/public/img/cardlink.png' );
		}

	}

	public function admin_options() {
		echo '<h2>' . esc_html( $this->get_method_title() );
		wc_back_link( __( 'Return to payments', 'woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
		echo '</h2>';
		echo '<p>' . __( 'Cardlink Payment Gateway allows you to accept payment through credit cards.', 'cardlink-payment-gateway' ) . '</p>';
		echo '<table class="form-table">';
		$this->generate_settings_html();
		echo '</table>';
	}

	function init_form_fields() {

		$this->form_fields = array(
			'enabled'                => array(
				'title'       => __( 'Enable/Disable', 'cardlink-payment-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Cardlink Payment Gateway', 'cardlink-payment-gateway' ),
				'description' => __( 'Enable or disable the gateway.', 'cardlink-payment-gateway' ),
				'desc_tip'    => true,
				'default'     => 'yes'
			),
			'environment'            => array(
				'title'   => __( 'Test Environment', 'cardlink-payment-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Cardlink Test Environment', 'cardlink-payment-gateway' ),
				'default' => 'no',
			),
			'acquirer'               => array(
				'title'       => __( 'Select Acquirer', 'cardlink-payment-gateway' ),
				'type'        => 'select',
				'options'     => Cardlink_Payment_Gateway_Woocommerce_Helper::get_acquirers(),
				'description' => __( 'Select your acquirer bank', 'cardlink-payment-gateway' )
			),
			'title'                  => array(
				'title'       => __( 'Title', 'cardlink-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'cardlink-payment-gateway' ),
				'desc_tip'    => true,
				'default'     => __( 'Credit card via Cardlink', 'cardlink-payment-gateway' )
			),
			'description'            => array(
				'title'       => __( 'Description', 'cardlink-payment-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'cardlink-payment-gateway' ),
				'desc_tip'    => true,
				'default'     => __( 'Pay Via Cardlink: Accepts Visa, Mastercard, Maestro, American Express, Diners, Discover.', 'cardlink-payment-gateway' )
			),
			'merchant_id'            => array(
				'title'       => __( 'Merchant ID', 'cardlink-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'Enter Your Cardlink Merchant ID', 'cardlink-payment-gateway' ),
				'default'     => '',
				'desc_tip'    => true
			),
			'shared_secret_key'      => array(
				'title'       => __( 'Shared Secret key', 'cardlink-payment-gateway' ),
				'type'        => 'password',
				'description' => __( 'Enter your Shared Secret key', 'cardlink-payment-gateway' ),
				'default'     => '',
				'desc_tip'    => true
			),
			'installments'           => array(
				'title'       => __( 'Maximum number of installments regardless of the total order amount', 'cardlink-payment-gateway' ),
				'type'        => 'select',
				'options'     => $this->get_installments(),
				'description' => __( '1 to 60 Installments, 1 for one time payment. You must contact Cardlink first.', 'cardlink-payment-gateway' )
			),
			'installments_variation' => array(
				'title'       => __( 'Maximum number of installments depending on the total order amount', 'cardlink-payment-gateway' ),
				'type'        => 'hidden',
				'class'       => 'installments-variation',
				'description' => __( 'Add amount and installments for each row. The limit is 60.', 'cardlink-payment-gateway' )
			),
			'transaction_type'       => array(
				'title'       => __( 'Pre-Authorize', 'cardlink-payment-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable to capture preauthorized payments', 'cardlink-payment-gateway' ),
				'description' => __( 'Default payment method is Purchase, enable for Pre-Authorized payments. You will then need to accept them from Cardlink eCommerce Tool.', 'cardlink-payment-gateway' ),
				'default'     => 'no'
			),
			'tokenization'           => array(
				'title'       => __( 'Store card details', 'cardlink-payment-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Tokenization', 'cardlink-payment-gateway' ),
				'description' => __( 'If checked the user will have the ability to store credit card details for future purchases. You must contact Cardlink first.', 'cardlink-payment-gateway' ),
				'default'     => 'no',
			),
			'redirect_page_id'       => array(
				'title'       => __( 'Return page URL <br />(Successful or Failed Transactions)', 'cardlink-payment-gateway' ),
				'type'        => 'select',
				'options'     => $this->get_pages( 'Select Page' ),
				'description' => __( 'We recommend you to select the default “Thank You Page”, in order to automatically serve both successful and failed transactions, with the latter also offering the option to try the payment again.<br /> If you select a different page, you will have to handle failed payments yourself by adding custom code.', 'cardlink-payment-gateway' ),
				'default'     => "-1"
			),
			'popup'                  => array(
				'title'       => __( 'Pay in website', 'cardlink-payment-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable payment iframe', 'cardlink-payment-gateway' ),
				'description' => __( 'Customers will stay in website to complete payments without redirecting to Cardlink\'s eCommerce payment page.<br />You must have a valid SSL certificate installed on your domain.', 'cardlink-payment-gateway' ),
				'default'     => 'no',
			),
			'css_url'                => array(
				'title'       => __( 'Css url path', 'cardlink-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'Url of custom CSS stylesheet, to be used to display payment page styles.', 'cardlink-payment-gateway' ),
				'default'     => null
			),
			'enable_log'             => array(
				'title'       => __( 'Enable Debug mode', 'cardlink-payment-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enabling this will log certain information', 'cardlink-payment-gateway' ),
				'default'     => 'no',
				'description' => __( 'Enabling this (and the debug mode from your wp-config file) will log information, e.g. responses, which will help in debugging issues.', 'cardlink-payment-gateway' )
			)
		);
	}

	function get_pages( $title = false, $indent = true ) {
		$wp_pages  = get_pages( 'sort_column=menu_order' );
		$page_list = array();
		if ( $title ) {
			$page_list[] = $title;
		}
		foreach ( $wp_pages as $page ) {
			$prefix = '';
			// show indented child pages?
			if ( $indent ) {
				$has_parent = $page->post_parent;
				while ( $has_parent ) {
					$prefix     .= ' - ';
					$next_page  = get_post( $has_parent );
					$has_parent = $next_page->post_parent;
				}
			}
			// add to page list array array
			$page_list[ $page->ID ] = $prefix . $page->post_title;
		}
		$page_list[ - 1 ] = __( 'Thank you page', 'cardlink-payment-gateway' );

		return $page_list;
	}

	function get_installments() {

		$installment_list = [];

		for ( $i = 1; $i <= 60; $i ++ ) {
			$installment_list[ $i ] = $i;
		}

		return $installment_list;
	}

	public function get_option( $key, $empty_value = null ) {
		$option_value = parent::get_option( $key, $empty_value );

		return $option_value;
	}

	function payment_fields() {

		global $woocommerce;

		$amount = 0;

		if ( absint( get_query_var( 'order-pay' ) ) ) {
			$order_id = absint( get_query_var( 'order-pay' ) );
			$order    = new WC_Order( $order_id );
			$amount   = $order->get_total();
		} else if ( ! $woocommerce->cart->is_empty() ) {
			$amount = $woocommerce->cart->total;
		}

		if ( $description = $this->get_description() ) {
			echo wpautop( wptexturize( $description ) );
		}

		echo $this->get_payment_cards_html();

		$max_installments       = $this->installments;
		$installments_variation = $this->installments_variation;

		if ( ! empty( $installments_variation ) ) {
			$max_installments = 1; // initialize the max installments
			if ( isset( $installments_variation ) && ! empty( $installments_variation ) ) {
				$installments_split = explode( ',', $installments_variation );
				foreach ( $installments_split as $key => $value ) {
					$installment = explode( ':', $value );
					if ( is_array( $installment ) && count( $installment ) != 2 ) {
						// not valid rule for installments
						continue;
					}
					if ( ! is_numeric( $installment[0] ) || ! is_numeric( $installment[1] ) ) {
						// not valid rule for installments
						continue;
					}
					if ( $amount >= ( $installment[0] ) ) {
						$max_installments = $installment[1];
					}
				}
			}
		}

		if ( $max_installments > 1 ) {

			$doseis_field = '<div class="form-row">
                    <label for="' . esc_attr( $this->id ) . '-card-doseis">' . __( 'Choose Installments', 'cardlink-payment-gateway' ) . ' <span class="required">*</span></label>
                                <select id="' . esc_attr( $this->id ) . '-card-doseis" name="' . esc_attr( $this->id ) . '-card-doseis" class="input-select wc-credit-card-form-card-doseis">
                                ';
			for ( $i = 1; $i <= $max_installments; $i ++ ) {
				$doseis_field .= '<option value="' . $i . '">' . ( $i == 1 ? __( 'Without installments', 'cardlink-payment-gateway' ) : $i ) . '</option>';
			}
			$doseis_field .= '</select>
                        </div>';

			echo $doseis_field;
		}
	}

	function get_payment_cards_html() {
		ob_start();

		if ( is_user_logged_in() && $this->tokenization == 'yes' ) {

			$counter     = 0;
			$tokens      = WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), $this->id );
			$token_class = new WC_Payment_Token_Data_Store;
			$html        = '';

			echo '<div class="payment-cards__tokens">';
			if ( ! empty( $tokens ) ) {
				foreach ( $tokens as $key => $tok ) {
					if ( $counter == 0 ) {
						$checked = ' checked';
					} else {
						$checked = '';
					}
					$token_meta = $token_class->get_metadata( $key );
					$token      = $token_class->get_token_by_id( $key );
					if ( $token_meta['card_type'][0] == 'mastercard' ) {
						$icon = '<img src="' . plugin_dir_url( __DIR__ ) . '/public/img/mastercard.png" alt="mastercard">';
					} elseif ( $token_meta['card_type'][0] == 'visa' ) {
						$icon = '<img src="' . plugin_dir_url( __DIR__ ) . '/public/img/visa.png" alt="visa">';
					} else {
						$icon = $token_meta['card_type'][0];
					}
					$html .= '<div class="payment-cards__field">';
					$html .= '<label for="card-' . $key . '" class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
								<input type="radio" id="card-' . $key . '" name="' . esc_attr( $this->id ) . '-card" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" value="' . $token->token . '" ' . $checked . '><span>' .
					         $icon . ' ************' . $token_meta['last4'][0] . ' ' . $token_meta['expiry_month'][0] . '/' . $token_meta['expiry_year'][0] .
					         '</span><a href="#" title="' . __( 'Remove card', 'cardlink-payment-gateway' ) . '" class="remove" aria-label="' . __( 'Remove card', 'cardlink-payment-gateway' ) . '">×</a>' .
					         '</label>';
					$html .= '</div>';
					$counter ++;
				}
			}
			if ( $html !== "" ) {
				echo '<div class="payment-cards">';
				echo $html;
				echo '<div class="payment-cards__field">';
				echo '<label for="new-card" class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox"><input type="radio" id="new-card" name="' . esc_attr( $this->id ) . '-card" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" value="new"><span>' . __( 'Add your credit card', 'cardlink-payment-gateway' ) . '</span></label>';
				echo '</div>';
				echo '</div>';
				echo '<div class="payment-cards-new-card payment-cards__field" style="display:none">';
			} else {
				echo '<div class="payment-cards-new-card payment-cards__field">';
			}
			echo '<label for="' . esc_attr( $this->id ) . '-card-store"><input type="checkbox" id="' . esc_attr( $this->id ) . '-card-store" name="' . esc_attr( $this->id ) . '-card-store" value="1"><span>' . __( 'Store your card?', 'cardlink-payment-gateway' ) . '</span></label>';
			echo '</div>';
			echo '</div>';
		}

		return ob_get_clean();
	}

	function generate_cardlink_form( $order_id ) {

		global $wpdb;

		$locale = get_locale();
		if ( $locale == 'el' ) {
			$lang = 'el';
		} else {
			$lang = 'en';
		}

		$version  = 2;
		$currency = 'EUR';
		$post_url = Cardlink_Payment_Gateway_Woocommerce_Helper::get_post_url( $this->environment, $this->acquirer );


		if ( $this->transaction_type == 'yes' ) {
			$trType = 2;
		} else {
			$trType = 1;
		}

		$order = new WC_Order( $order_id );

		if ( method_exists( $order, 'get_meta' ) ) {
			$installments = $order->get_meta( '_doseis' );
			if ( $installments == '' ) {
				$installments = 1;
			}
		} else {
			$installments = get_post_meta( $order_id, '_doseis', 1 );
		}

		$store_card    = get_post_meta( $order_id, '_cardlink_store_card', true );
		$selected_card = get_post_meta( $order_id, '_cardlink_card', true );

		$countries_obj        = new WC_Countries();
		$country              = $order->get_billing_country();
		$country_states_array = $countries_obj->get_states();
		$state_code           = $order->get_billing_state();
		$state                = $country_states_array[ $country ][ $state_code ];

		$wpdb->insert( $wpdb->prefix . $this->table_name, array(
			'trans_ticket'      => $order_id,
			'merchantreference' => $order_id,
			'timestamp'         => current_time( 'mysql', 1 )
		) );


		$_SESSION['order_id'] = $order_id;
		WC()->session->set( 'order_id', $order_id );

		if ( $country != 'GR' ) {
			if ( $installments > 1 ) {
				$form_data_array = array(
					'version'              => $version,
					'mid'                  => $this->merchant_id,
					'lang'                 => $lang,
					'orderid'              => $order_id . 'at' . date( 'Ymdhisu' ),
					'orderDesc'            => 'Order #' . $order_id,
					'orderAmount'          => $order->get_total(),
					'currency'             => $currency,
					'payerEmail'           => $order->get_billing_email(),
					'payerPhone'           => $order->get_billing_phone(),
					'billCountry'          => $country,
					'billState'            => $state_code,
					'billZip'              => $order->get_billing_postcode(),
					'billCity'             => $order->get_billing_city(),
					'billAddress'          => $order->get_billing_address_1(),
					'shipCountry'          => $order->get_shipping_country(),
					'shipZip'              => $order->get_shipping_postcode(),
					'shipCity'             => $order->get_shipping_city(),
					'shipAddress'          => $order->get_shipping_address_1(),
					'trType'               => $trType,
					'extInstallmentoffset' => 0,
					'extInstallmentperiod' => $installments,
					'cssUrl'               => $this->css_url,
					'confirmUrl'           => get_site_url() . "/?wc-api=" . $this->api_request_url . "&result=success",
					'cancelUrl'            => get_site_url() . "/?wc-api=" . $this->api_request_url . "&result=failure",
				);
			} else {
				$form_data_array = array(
					'version'     => $version,
					'mid'         => $this->merchant_id,
					'lang'        => $lang,
					'orderid'     => $order_id . 'at' . date( 'Ymdhisu' ),
					'orderDesc'   => 'Order #' . $order_id,
					'orderAmount' => $order->get_total(),
					'currency'    => $currency,
					'payerEmail'  => $order->get_billing_email(),
					'payerPhone'  => $order->get_billing_phone(),
					'billCountry' => $country,
					'billState'   => $state_code,
					'billZip'     => $order->get_billing_postcode(),
					'billCity'    => $order->get_billing_city(),
					'billAddress' => $order->get_billing_address_1(),
					'shipCountry' => $order->get_shipping_country(),
					'shipZip'     => $order->get_shipping_postcode(),
					'shipCity'    => $order->get_shipping_city(),
					'shipAddress' => $order->get_shipping_address_1(),
					'trType'      => $trType,
					'cssUrl'      => $this->css_url,
					'confirmUrl'  => get_site_url() . "/?wc-api=" . $this->api_request_url . "&result=success",
					'cancelUrl'   => get_site_url() . "/?wc-api=" . $this->api_request_url . "&result=failure",
				);
			}
		} else {
			if ( $installments > 1 ) {
				$form_data_array = array(
					'version'              => $version,
					'mid'                  => $this->merchant_id,
					'lang'                 => $lang,
					'orderid'              => $order_id . 'at' . date( 'Ymdhisu' ),
					'orderDesc'            => 'Order #' . $order_id,
					'orderAmount'          => $order->get_total(),
					'currency'             => $currency,
					'payerEmail'           => $order->get_billing_email(),
					'payerPhone'           => $order->get_billing_phone(),
					'billCountry'          => $country,
					'billZip'              => $order->get_billing_postcode(),
					'billCity'             => $order->get_billing_city(),
					'billAddress'          => $order->get_billing_address_1(),
					'shipCountry'          => $order->get_shipping_country(),
					'shipZip'              => $order->get_shipping_postcode(),
					'shipCity'             => $order->get_shipping_city(),
					'shipAddress'          => $order->get_shipping_address_1(),
					'trType'               => $trType,
					'extInstallmentoffset' => 0,
					'extInstallmentperiod' => $installments,
					'cssUrl'               => $this->css_url,
					'confirmUrl'           => get_site_url() . "/?wc-api=" . $this->api_request_url . "&result=success",
					'cancelUrl'            => get_site_url() . "/?wc-api=" . $this->api_request_url . "&result=failure",
				);
			} else {
				$form_data_array = array(
					'version'     => $version,
					'mid'         => $this->merchant_id,
					'lang'        => $lang,
					'orderid'     => $order_id . 'at' . date( 'Ymdhisu' ),
					'orderDesc'   => 'Order #' . $order_id,
					'orderAmount' => $order->get_total(),
					'currency'    => $currency,
					'payerEmail'  => $order->get_billing_email(),
					'payerPhone'  => $order->get_billing_phone(),
					'billCountry' => $country,
					'billZip'     => $order->get_billing_postcode(),
					'billCity'    => $order->get_billing_city(),
					'billAddress' => $order->get_billing_address_1(),
					'shipCountry' => $order->get_shipping_country(),
					'shipZip'     => $order->get_shipping_postcode(),
					'shipCity'    => $order->get_shipping_city(),
					'shipAddress' => $order->get_shipping_address_1(),
					'trType'      => $trType,
					'cssUrl'      => $this->css_url,
					'confirmUrl'  => get_site_url() . "/?wc-api=" . $this->api_request_url . "&result=success",
					'cancelUrl'   => get_site_url() . "/?wc-api=" . $this->api_request_url . "&result=failure",
				);
			}
		}

		if ( is_user_logged_in() && $this->tokenization == 'yes' ) {
			if ( $store_card ) {
				$form_data_array['extTokenOptions'] = 100;
			} else {
				if ( $selected_card ) {
					$form_data_array['extTokenOptions'] = 110;
					$form_data_array['extToken']        = $selected_card;
				}
			}
		}

		$form_secret = $this->shared_secret_key;
		$form_data   = iconv( 'utf-8', 'utf-8//IGNORE', implode( "", $form_data_array ) ) . $form_secret;
		$digest      = Cardlink_Payment_Gateway_Woocommerce_Helper::calculate_digest( $form_data );

		if ( $this->enable_log == 'yes' ) {
			error_log( '---- Cardlink Transaction digest -----' );
			error_log( 'Data: ' );
			error_log( print_r( $form_data, true ) );
			error_log( 'Digest: ' );
			error_log( print_r( $digest, true ) );
			error_log( '---- End of Cardlink Transaction digest ----' );
		}

		$use_redirection = $this->popup == "no";
		$form_target     = $use_redirection ? '_top' : 'payment_iframe';
		$html            = '<form action="' . esc_url( $post_url ) . '" method="POST" id="payment_form" target="' . $form_target . '" accept-charset="UTF-8">';

		foreach ( $form_data_array as $key => $value ) {
			$html .= '<input type="hidden" id ="' . $key . '" name ="' . $key . '" value="' . iconv( 'utf-8', 'utf-8//IGNORE', $value ) . '"/>';
		}

		$html .= '<input type="hidden" id="digest" name ="digest" value="' . esc_attr( $digest ) . '"/>';
		$html .= '<!-- Button Fallback -->
            <div class="payment_buttons">
                <input type="submit" class="button alt" id="submit_cardlink_payment_form" value="' . __( 'Pay via Cardlink', 'cardlink-payment-gateway' ) . '" /> 
            </div>
            <script type="text/javascript">
                jQuery(".payment_buttons").hide();
            </script>';
		$html .= '</form>';
		if ( $use_redirection ) {
			wc_enqueue_js( '
			$.blockUI({
				message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to make payment.', 'cardlink-payment-gateway' ) ) . '",
				baseZ: 99999,
				overlayCSS: {
					background: "#fff",
					opacity: 0.6
				},
				css: {
					padding:        "20px",
					zindex:         "9999999",
					textAlign:      "center",
					color:          "#555",
					border:         "3px solid #aaa",
					backgroundColor:"#fff",
					cursor:         "wait",
					lineHeight:		"24px",
				}
			});
			' );
		} else {
			$html .= '<div class="' . $this->id . '_modal">';
			$html .= '<div class="' . $this->id . '_modal_wrapper">';
			$html .= '<iframe name="payment_iframe" id="payment_iframe" src="" frameBorder="0" data-order-id="' . $order_id . '"></iframe>';
			$html .= '</div>';
			$html .= '</div>';
		}
		wc_enqueue_js( "
			$('#payment_form').submit();
		" );

		return $html;
	}

	function process_payment( $order_id ) {
		return Cardlink_Payment_Gateway_Woocommerce_Helper::process_payment($order_id, $_POST, $this->id );
	}

	function receipt_page( $order ) {
		echo '<p>' . __( 'Thank you for your order. We are now redirecting you to make payment.', 'cardlink-payment-gateway' ) . '</p>';
		echo $this->generate_cardlink_form( $order );
	}

	function check_cardlink_response() {

		$post_data = $_POST;
		$enable_log = $this->enable_log;
		$shared_secret_key = $this->shared_secret_key;
		$order_note = $this->order_note;
		$id = $this->id;

		$order = Cardlink_Payment_Gateway_Woocommerce_Helper::check_response( $post_data, $enable_log, $shared_secret_key, $order_note, $id );

		if ( $this->redirect_page_id == "-1" ) {
			$redirect_url = $this->get_return_url( $order );
		} else {
			$redirect_url = ( $this->redirect_page_id == "" || $this->redirect_page_id == 0 ) ? $this->get_return_url( $order ) : get_permalink( $this->redirect_page_id );
		}
		wp_redirect( $redirect_url );

		exit;
	}

}

class Cardlink_Payment_Gateway_Woocommerce_Iris extends WC_Payment_Gateway {
	public $id;
	public $has_fields;
	public $method_title;
	public $method_description;
	public $title;
	public $description;
	public $environment;
	public $merchant_id;
	public $acquirer;
	public $shared_secret_key;
	public $iris_customer_code;
	public $popup;
	public $enable_log;
	public $api_request_url = 'Cardlink_Payment_Gateway_Woocommerce_Iris';
	public $table_name = 'cardlink_gateway_transactions';
	public function __construct() {

		$this->id                 = 'cardlink_payment_gateway_woocommerce_iris';
		$this->has_fields         = true;
		$this->method_title       = __( 'Cardlink Payment Gateway Iris', 'cardlink-payment-gateway' );
		$this->method_description = __( 'Cardlink Payment Gateway allows you to accept payment through IRIS on your Woocommerce Powered Site.', 'cardlink-payment-gateway' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings
		$this->init_settings();

		// Define User set Variables
		$this->title                  = sanitize_text_field( $this->get_option( 'title' ) );
		$this->description            = sanitize_text_field( $this->get_option( 'description' ) );
		$this->iris_customer_code     = sanitize_text_field( $this->get_option( 'iris_customer_code' ) );

		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page_iris' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );

	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'                => array(
				'title'       => __( 'Enable/Disable', 'cardlink-payment-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Cardlink Payment Gateway', 'cardlink-payment-gateway' ),
				'description' => __( 'Enable or disable the gateway.', 'cardlink-payment-gateway' ),
				'desc_tip'    => true,
				'default'     => 'yes'
			),
			'title'                  => array(
				'title'       => __( 'Title', 'cardlink-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'cardlink-payment-gateway' ),
				'desc_tip'    => true,
				'default'     => __( 'Credit card via Cardlink', 'cardlink-payment-gateway' )
			),
			'description'            => array(
				'title'       => __( 'Description', 'cardlink-payment-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'cardlink-payment-gateway' ),
				'desc_tip'    => true,
				'default'     => __( 'Pay Via Cardlink: Accepts Visa, Mastercard, Maestro, American Express, Diners, Discover.', 'cardlink-payment-gateway' )
			),
			'iris_customer_code'=> array(
				'title'       => __( 'IRIS customer code', 'cardlink-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'Enter Your IRIS customer code', 'cardlink-payment-gateway' ),
				'default'     => '',
				'desc_tip'    => true
			)
		);
	}

	public function receipt_page_iris( $order ) {
		echo '<p>' . __( 'Thank you for your order. We are now redirecting you to make payment.', 'cardlink-payment-gateway' ) . '</p>';
		echo $this->generate_cardlink_form( $order );
	}

	public function generate_cardlink_form( $order_id ) {

		global $wpdb;

		$locale = get_locale();
		if ( $locale == 'el' ) {
			$lang = 'el';
		} else {
			$lang = 'en';
		}

		$version  = 2;
		$currency = 'EUR';

		$payment_Gateway = Cardlink_Payment_Gateway_Woocommerce::instance();
		$this->environment 			= $payment_Gateway->environment;
		$this->merchant_id 			= $payment_Gateway->merchant_id;
		$this->acquirer 			= $payment_Gateway->acquirer;
		$this->shared_secret_key 	= $payment_Gateway->shared_secret_key;
		$this->enable_log 			= $payment_Gateway->enable_log;
		$this->api_request_url      = $payment_Gateway->api_request_url;

		$post_url = Cardlink_Payment_Gateway_Woocommerce_Helper::get_post_url( $this->environment, $this->acquirer );

		$order = new WC_Order( $order_id );

		$countries_obj        = new WC_Countries();
		$country              = $order->get_billing_country();
		$country_states_array = $countries_obj->get_states();
		$state_code           = $order->get_billing_state();
		$state                = $country_states_array[ $country ][ $state_code ];

		$wpdb->insert( $wpdb->prefix . $this->table_name, array(
			'trans_ticket'      => $order_id,
			'merchantreference' => $order_id,
			'timestamp'         => current_time( 'mysql', 1 )
		) );

		$_SESSION['order_id'] = $order_id;
		WC()->session->set( 'order_id', $order_id );

		if ( $country != 'GR' ) {
				$form_data_array = array(
					'version'     => $version,
					'mid'         => $this->merchant_id,
					'lang'        => $lang,
					'orderid'     => $order_id . 'at' . date( 'Ymdhisu' ),
					'orderDesc'   => $this->get_rf_code( $order_id ),
					'orderAmount' => $order->get_total(),
					'currency'    => $currency,
					'payerEmail'  => $order->get_billing_email(),
					'payerPhone'  => $order->get_billing_phone(),
					'billCountry' => $country,
					'billState'   => $state_code,
					'billZip'     => $order->get_billing_postcode(),
					'billCity'    => $order->get_billing_city(),
					'billAddress' => $order->get_billing_address_1(),
					'payMethod'   => 'IRIS',
					'confirmUrl'  => get_site_url() . "/?wc-api=" . $this->api_request_url . "&result=success",
					'cancelUrl'   => get_site_url() . "/?wc-api=" . $this->api_request_url . "&result=failure",
				);
		} else {
				$form_data_array = array(
					'version'     => $version,
					'mid'         => $this->merchant_id,
					'lang'        => $lang,
					'orderid'     => $order_id . 'at' . date( 'Ymdhisu' ),
					'orderDesc'   => $this->get_rf_code( $order_id ),
					'orderAmount' => $order->get_total(),
					'currency'    => $currency,
					'payerEmail'  => $order->get_billing_email(),
					'payerPhone'  => $order->get_billing_phone(),
					'billCountry' => $country,
					'billZip'     => $order->get_billing_postcode(),
					'billCity'    => $order->get_billing_city(),
					'billAddress' => $order->get_billing_address_1(),
					'payMethod'   => 'IRIS',
					'confirmUrl'  => get_site_url() . "/?wc-api=" . $this->api_request_url . "&result=success",
					'cancelUrl'   => get_site_url() . "/?wc-api=" . $this->api_request_url . "&result=failure",
				);
		}

		$form_secret = $this->shared_secret_key;
		$form_data   = iconv( 'utf-8', 'utf-8//IGNORE', implode( "", $form_data_array ) ) . $form_secret;
		$digest      = Cardlink_Payment_Gateway_Woocommerce_Helper::calculate_digest( $form_data );

		if ( $this->enable_log == 'yes' ) {
			error_log( '---- Cardlink Transaction digest -----' );
			error_log( 'Data: ' );
			error_log( print_r( $form_data, true ) );
			error_log( 'Digest: ' );
			error_log( print_r( $digest, true ) );
			error_log( '---- End of Cardlink Transaction digest ----' );
		}

		$form_target     = '_top';
		$html            = '<form action="' . esc_url( $post_url ) . '" method="POST" id="payment_form" target="' . $form_target . '" accept-charset="UTF-8">';

		foreach ( $form_data_array as $key => $value ) {
			$html .= '<input type="hidden" id ="' . $key . '" name ="' . $key . '" value="' . iconv( 'utf-8', 'utf-8//IGNORE', $value ) . '"/>';
		}

		$html .= '<input type="hidden" id="digest" name ="digest" value="' . esc_attr( $digest ) . '"/>';
		$html .= '<!-- Button Fallback -->
            <div class="payment_buttons">
                <input type="submit" class="button alt" id="submit_cardlink_payment_form" value="' . __( 'Pay via Cardlink', 'cardlink-payment-gateway' ) . '" /> 
            </div>
            <script type="text/javascript">
                jQuery(".payment_buttons").hide();
            </script>';
		$html .= '</form>';
		wc_enqueue_js( '
		$.blockUI({
			message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to make payment.', 'cardlink-payment-gateway' ) ) . '",
			baseZ: 99999,
			overlayCSS: {
				background: "#fff",
				opacity: 0.6
			},
			css: {
				padding:        "20px",
				zindex:         "9999999",
				textAlign:      "center",
				color:          "#555",
				border:         "3px solid #aaa",
				backgroundColor:"#fff",
				cursor:         "wait",
				lineHeight:		"24px",
			}
		});
		' );
		 wc_enqueue_js( "
			$('#payment_form').submit();
		" );

		return $html;
	}

	public function get_option( $key, $empty_value = null ) {
		$option_value = parent::get_option( $key, $empty_value );

		return $option_value;
	}

	public function get_rf_code( $order_id ) {
		$rf_payment_code = get_post_meta( $order_id, 'rf_payment_code', true );
		if ( $rf_payment_code !== '' ) {
		   return $rf_payment_code;
		}

		$order = new WC_Order( $order_id );
		$order_total = $order->get_total();
		/* calculate payment check code */
		$paymentSum = 0;
		if ( $order_total > 0 ) {
		   $ordertotal = str_replace( [ ',' ], '.', (string) $order_total );
		   $ordertotal = number_format( $ordertotal, 2, '', '' );
		   $ordertotal = strrev( $ordertotal );
		   $factor     = [ 1, 7, 3 ];
		   $idx        = 0;
		   for ( $i = 0; $i < strlen( $ordertotal ); $i ++ ) {
			  $idx        = $idx <= 2 ? $idx : 0;
			  $paymentSum += $ordertotal[ $i ] * $factor[ $idx ];
			  $idx ++;
		   }
		}
		$randomNumber 	 = $this->generateRandomString( 13, $order_id );
		$paymentCode  	 = $paymentSum ? ( $paymentSum % 8 ) : '8';
		$systemCode   	 = '12';
		$tempCode     	 = $this->iris_customer_code . $paymentCode . $systemCode . $randomNumber . '271500';
		$mod97        	 = bcmod( $tempCode, '97' );
		$cd           	 = 98 - (int) $mod97;
		$cd              = str_pad( (string) $cd, 2, '0', STR_PAD_LEFT );
		$rf_payment_code = 'RF' . $cd . $this->iris_customer_code . $paymentCode . $systemCode . $randomNumber;
		update_post_meta( $order_id, 'rf_payment_code', $rf_payment_code );
		return $rf_payment_code;
	}

	public function generateRandomString( $length = 22, $order_id = 0 ) {
		return str_pad( $order_id, $length, '0', STR_PAD_LEFT );
	}

	function process_payment( $order_id ) {
		return Cardlink_Payment_Gateway_Woocommerce_Helper::process_payment($order_id, $_POST, $this->id );
	}

}



