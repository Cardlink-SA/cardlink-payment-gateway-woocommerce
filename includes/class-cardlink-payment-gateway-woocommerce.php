<?php

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	return;
}


/**
 *  Gateway Class
 */
class Cardlink_Payment_Gateway_Woocommerce extends WC_Payment_Gateway {

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
			$this->icon = apply_filters( 'cardlink_icon', plugins_url() . '/cardlink-payment-gateway/public/img/cardlink.png' );
		} elseif ( $this->acquirer == 1 ) {
			$this->icon = apply_filters( 'cardlink_icon', plugins_url() . '/cardlink-payment-gateway/public/img/cardlink.png' );
		} elseif ( $this->acquirer == 2 ) {
			$this->icon = apply_filters( 'cardlink_icon', plugins_url() . '/cardlink-payment-gateway/public/img/cardlink.png' );
		}

	}

	/**
	 *  Admin Panel Options
	 */
	public function admin_options() {
		echo '<h2>' . esc_html( $this->get_method_title() );
		wc_back_link( __( 'Return to payments', 'woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
		echo '</h2>';
		echo '<p>' . __( 'Cardlink Payment Gateway allows you to accept payment through credit cards.', 'cardlink-payment-gateway' ) . '</p>';
		echo '<table class="form-table">';
		$this->generate_settings_html();
		echo '</table>';
	}

	function register_session() {
		if ( ! session_id() ) {
			session_start();
		}
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 * */
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
				'options'     => $this->get_acquirers(),
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
				'description' => __( '1 to 10 Installments, 1 for one time payment. You must contact Cardlink first.', 'cardlink-payment-gateway' )
			),
			'installments_variation' => array(
				'title'       => __( 'Maximum number of installments depending on the total order amount', 'cardlink-payment-gateway' ),
				'type'        => 'hidden',
				'class'       => 'installments-variation',
				'description' => __( 'Add amount and installments for each row. The limit is 10.', 'cardlink-payment-gateway' )
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

	function get_acquirers() {

		return [
			'Cardlink Checkout',
			'Nexi Checkout',
			'Worldline Greece Checkout'
		];
	}

	function get_installments() {

		$installment_list = [];

		for ( $i = 1; $i <= 10; $i ++ ) {
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

	function calculate_digest( $input ) {

		$digest = base64_encode( hash( 'sha256', ( $input ), true ) );

		return $digest;
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
		$post_url = '';
		if ( $this->environment == "yes" ) {
			switch ( $this->acquirer ) {
				case 0 :
					$post_url = "https://ecommerce-test.cardlink.gr/vpos/shophandlermpi";
					break;
				case 1 :
					$post_url = "https://alphaecommerce-test.cardlink.gr/vpos/shophandlermpi";
					break;
				case 2 :
					$post_url = "https://eurocommerce-test.cardlink.gr/vpos/shophandlermpi";
					break;
			}
		} else {
			switch ( $this->acquirer ) {
				case 0 :
					$post_url = "https://ecommerce.cardlink.gr/vpos/shophandlermpi";
					break;
				case 1 :
					$post_url = "https://www.alphaecommerce.gr/vpos/shophandlermpi";
					break;
				case 2 :
					$post_url = "https://vpos.eurocommerce.gr/vpos/shophandlermpi";
					break;
			}
		}

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
					'billCountry'          => $country,
					'billState'            => $state_code,
					'billZip'              => $order->get_billing_postcode(),
					'billCity'             => $order->get_billing_city(),
					'billAddress'          => $order->get_billing_address_1(),
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
					'billCountry' => $country,
					'billState'   => $state_code,
					'billZip'     => $order->get_billing_postcode(),
					'billCity'    => $order->get_billing_city(),
					'billAddress' => $order->get_billing_address_1(),
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
					'billCountry'          => $country,
					'billZip'              => $order->get_billing_postcode(),
					'billCity'             => $order->get_billing_city(),
					'billAddress'          => $order->get_billing_address_1(),
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
					'billCountry' => $country,
					'billZip'     => $order->get_billing_postcode(),
					'billCity'    => $order->get_billing_city(),
					'billAddress' => $order->get_billing_address_1(),
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
		$digest      = $this->calculate_digest( $form_data );

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

		$order  = new WC_Order( $order_id );
		$doseis = isset( $_POST[ esc_attr( $this->id ) . '-card-doseis' ] ) ? intval( $_POST[ esc_attr( $this->id ) . '-card-doseis' ] ) : '';
		if ( $doseis > 0 ) {
			$this->generic_add_meta( $order_id, '_doseis', $doseis );
		}

		$store_card = isset( $_POST[ esc_attr( $this->id ) . '-card-store' ] ) ? intval( $_POST[ esc_attr( $this->id ) . '-card-store' ] ) : 0;
		$this->generic_add_meta( $order_id, '_cardlink_store_card', $store_card );

		$selected_card = isset( $_POST[ esc_attr( $this->id ) . '-card' ] ) ? intval( $_POST[ esc_attr( $this->id ) . '-card' ] ) : 0;
		$this->generic_add_meta( $order_id, '_cardlink_card', $selected_card );

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

	function receipt_page( $order ) {
		echo '<p>' . __( 'Thank you for your order. We are now redirecting you to make payment.', 'cardlink-payment-gateway' ) . '</p>';
		echo $this->generate_cardlink_form( $order );
	}

	/**
	 * Verify a successful Payment!
	 * */
	function check_cardlink_response() {

		global $woocommerce;

		if ( $this->enable_log == 'yes' ) {
			error_log( '---- eCommerce Response -----' );
			error_log( print_r( $_POST, true ) );
			error_log( '---- End of eCommerce Response ----' );
		}

		$mid = filter_var( $_POST['mid'], FILTER_SANITIZE_NUMBER_INT );

		$orderid_session = WC()->session->get( 'order_id' );
		$orderid_post    = filter_var( $_POST['orderid'], FILTER_SANITIZE_STRING );

		$reg = preg_match( '/^(.*?)at/', $orderid = $orderid_post, $matches );

		if ( ! empty( $matches ) ) {
			$orderid = $matches[1];
		} else {
			$orderid = $orderid_session;
		}

		if ( $orderid == '' ) {
			$orderid = $orderid_post;
			error_log( "Cardlink: something went wrong with order id " );
			error_log( print_r( $_POST, true ) );
			error_log( print_r( $matches, true ) );
			error_log( $orderid_session );
		}

		$status         = filter_var( $_POST['status'], FILTER_SANITIZE_STRING );
		$orderAmount    = filter_var( $_POST['orderAmount'], FILTER_SANITIZE_NUMBER_FLOAT );
		$currency       = filter_var( $_POST['currency'], FILTER_SANITIZE_STRING );
		$paymentTotal   = filter_var( $_POST['paymentTotal'], FILTER_SANITIZE_NUMBER_FLOAT );
		$message        = isset( $_POST['message'] ) ? filter_var( $_POST['message'], FILTER_SANITIZE_STRING ) : '';
		$riskScore      = isset( $_POST['riskScore'] ) ? filter_var( $_POST['riskScore'], FILTER_SANITIZE_NUMBER_FLOAT ) : '';
		$payMethod      = isset( $_POST['payMethod'] ) ? filter_var( $_POST['payMethod'], FILTER_SANITIZE_STRING ) : '';
		$txId           = isset( $_POST['txId'] ) ? filter_var( $_POST['txId'], FILTER_SANITIZE_NUMBER_FLOAT ) : '';
		$paymentRef     = isset( $_POST['paymentRef'] ) ? filter_var( $_POST['paymentRef'], FILTER_SANITIZE_STRING ) : '';
		$extToken       = isset( $_POST['extToken'] ) ? filter_var( $_POST['extToken'], FILTER_SANITIZE_STRING ) : '';
		$extTokenPanEnd = isset( $_POST['extTokenPanEnd'] ) ? filter_var( $_POST['extTokenPanEnd'], FILTER_SANITIZE_STRING ) : '';
		$extTokenExp    = isset( $_POST['extTokenExp'] ) ? $_POST['extTokenExp'] : '';
		$digest         = filter_var( $_POST['digest'], FILTER_SANITIZE_STRING );

		$extTokenExpYear  = substr( $extTokenExp, 0, 4 );
		$extTokenExpMonth = substr( $extTokenExp, 4, 2 );

		$form_data = '';
		foreach ( $_POST as $k => $v ) {
			if ( ! in_array( $k, array( '_charset_', 'digest', 'submitButton' ) ) ) {
				$form_data .= filter_var( $_POST[ $k ], FILTER_SANITIZE_STRING );
			}
		}

		$form_data       .= $this->shared_secret_key;
		$computed_digest = $this->calculate_digest( $form_data );

		$order           = new WC_Order( $orderid );
		$current_user_id = $order->get_user_id();
		$message         = array( 'message' => '', 'message_type' => '' );

		if ( $digest != $computed_digest ) {
			$message      = __( 'A technical problem occured. <br />The transaction wasn\'t successful, payment wasn\'t received.', 'cardlink-payment-gateway' );
			$message_type = 'error';
			$message      = array( 'message' => $message, 'message_type' => $message_type );
			$this->generic_add_meta( $orderid, '_cardlink_message', $message );
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

				if ( $this->order_note == 'yes' ) {
					$order->add_order_note( __( 'Payment Received.<br />Your order is currently being processed.<br />We will be shipping your order to you soon.<br />Cardlink ID: ', 'cardlink-payment-gateway' ) . $paymentRef, 1 );

				}
			} else if ( $order->get_status() == 'completed' ) {
				$message = __( 'Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is now complete.', 'cardlink-payment-gateway' );
				if ( $this->order_note == 'yes' ) {
					$order->add_order_note( __( 'Payment Received.<br />Your order is now complete.<br />Cardlink Transaction ID: ', 'cardlink-payment-gateway' ) . $paymentRef, 1 );
				}
			}

			$tokens      = WC_Payment_Tokens::get_customer_tokens( $current_user_id, $this->id );
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
				$token->set_gateway_id( $this->id );
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

			$this->generic_add_meta( $orderid, '_cardlink_message', $message );

			WC()->cart->empty_cart();

		} else if ( $status == 'CANCELED' ) {
			$message = array(
				'message'      => __( 'Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment was cancelled.', 'cardlink-payment-gateway' ),
				'message_type' => 'notice'
			);
			$this->generic_add_meta( $orderid, '_cardlink_message', $message );
			$order->update_status( 'failed', 'ERROR ' . $message['message'] );

		} else if ( $status == 'REFUSED' ) {
			$client_message = __( 'Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment wasn\'t received.', 'cardlink-payment-gateway' );
			$message_type   = 'error';
			$message        = array( 'message' => $client_message, 'message_type' => $message_type );
			$this->generic_add_meta( $orderid, '_cardlink_message', $message );
			$order->update_status( 'failed', 'REFUSED ' . $message );
		} else if ( $status == 'ERROR' ) {
			$client_message = __( 'Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment wasn\'t received.', 'cardlink-payment-gateway' );
			$message_type   = 'error';
			$message        = array( 'message' => $client_message, 'message_type' => $message_type );
			$this->generic_add_meta( $orderid, '_cardlink_message', $message );
			$order->update_status( 'failed', 'ERROR ' . $message );
		} else {
			$client_message = __( 'Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment wasn\'t received.', 'cardlink-payment-gateway' );
			$message_type   = 'error';
			$message        = array( 'message' => $client_message, 'message_type' => $message_type );
			$this->generic_add_meta( $orderid, '_cardlink_message', $message );
			$order->update_status( 'failed', 'Unknown: ' . $message );
		}


		if ( $this->redirect_page_id == "-1" ) {
			$redirect_url = $this->get_return_url( $order );
		} else {
			$redirect_url = ( $this->redirect_page_id == "" || $this->redirect_page_id == 0 ) ? $this->get_return_url( $order ) : get_permalink( $this->redirect_page_id );
		}
		wp_redirect( $redirect_url );

		exit;
	}

	function generic_add_meta( $orderid, $key, $value ) {
		$order = new WC_Order( $orderid );
		if ( method_exists( $order, 'add_meta_data' ) && method_exists( $order, 'save_meta_data' ) ) {
			$order->add_meta_data( $key, $value, true );
			$order->save_meta_data();
		} else {
			update_post_meta( $orderid, $key, $value );
		}
	}
}
