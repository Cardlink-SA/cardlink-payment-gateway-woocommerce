<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.cardlink.gr/
 * @since      1.0.0
 *
 * @package    Cardlink_Payment_Gateway
 * @subpackage Cardlink_Payment_Gateway/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Cardlink_Payment_Gateway
 * @subpackage Cardlink_Payment_Gateway/public
 * @author     Cardlink <info@cardlink.gr>
 */
class Cardlink_Payment_Gateway_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $plugin_name The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $version The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 *
	 * @param      string $plugin_name The name of the plugin.
	 * @param      string $version The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'maybe_show_iris_gateway'] );

	}

	/**
	 * Conditionally display the IRIS payment gateway on checkout
	 *
	 * @since    1.0.0
	 */
	public function maybe_show_iris_gateway( $available_gateways ) {
		if ( ! is_admin() ) {
			$payment_gateway_id = 'cardlink_payment_gateway_woocommerce';
			if (array_key_exists($payment_gateway_id, $available_gateways)) {
				if ($available_gateways[$payment_gateway_id]->acquirer != 1) {
					unset($available_gateways['cardlink_payment_gateway_woocommerce_iris']);
				}
			}
		}
		return $available_gateways;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Cardlink_Payment_Gateway_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Cardlink_Payment_Gateway_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/cardlink-payment-gateway-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Cardlink_Payment_Gateway_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Cardlink_Payment_Gateway_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script($this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/cardlink-payment-gateway-public.js', array( 'jquery' ), $this->version, false );

		$this->init_ajax_scripts();

	}

	public function delete_payment_token() {

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-cardlink-payment-gateway-ajax.php';

		add_action( 'after_setup_theme', [ 'Cardlink_Payment_Gateway_Delete', 'get_instance' ] );

	}

	public function init_ajax_scripts() {

		$ajax_url_params = array();

		wp_localize_script($this->plugin_name, 'urls', array(
			'home'   => home_url(),
			'theme'  => get_template_directory(),
			'plugins' => plugins_url(),
			'assets' => get_stylesheet_directory_uri() . '/assets',
			'ajax'   => add_query_arg( $ajax_url_params, admin_url( 'admin-ajax.php' ) )
		) ); 

	}

	public function register_rest_routes() {
		register_rest_route(
			'wc-cardlink/v1',
			'tokenizer',
			array(
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_tokenized_transactions' ],
				'permission_callback' => '__return_true',
			)
		);
	}

	public function handle_tokenized_transactions(WP_REST_Request $request) {

		$post_data = $request->get_params();
		$response_message = __( 'Payment method successfully added.', 'woocommerce' );
		$redirect_url = wc_get_endpoint_url( 'payment-methods', '', wc_get_page_permalink( 'myaccount' ) );
		error_log(json_encode($post_data));

		$payment_gateway_instance	= WC()->payment_gateways->payment_gateways()['cardlink_payment_gateway_woocommerce'];
		$shared_secret_key = $payment_gateway_instance->shared_secret_key;
		$method_id = $payment_gateway_instance->id;

		$digest = $post_data['digest'];
		unset($post_data['result']);
		unset($post_data['digest']);

		$form_data = '';
		foreach ($post_data as $k => $v) {
			$form_data .= filter_var( $v, FILTER_SANITIZE_STRING );
		}
		$form_data      .= $shared_secret_key;
		$computed_digest = Cardlink_Payment_Gateway_Woocommerce_Helper::calculate_digest( $form_data );

		if ( $digest !== $computed_digest ){
			wp_redirect($redirect_url);
			exit();
		}

		$order_id = explode("at", $post_data['orderid'], 2);
		$current_user_id = $order_id[0];
		$extTokenExpYear  = substr( $post_data['extTokenExp'], 0, 4 );
		$extTokenExpMonth = substr( $post_data['extTokenExp'], 4, 2 );

		$token = new WC_Payment_Token_CC();
		$token->set_token( $post_data['extToken'] );
		$token->set_gateway_id( $method_id );
		$token->set_last4( $post_data['extTokenPanEnd'] );
		$token->set_expiry_year( $extTokenExpYear );
		$token->set_expiry_month( $extTokenExpMonth );
		$token->set_card_type( $post_data['payMethod'] );
		$token->set_user_id( $current_user_id );
		$token->save();

		wp_redirect($redirect_url);
		exit();
	}

}
