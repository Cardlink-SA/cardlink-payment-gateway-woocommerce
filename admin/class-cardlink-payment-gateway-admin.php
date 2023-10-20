<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.cardlink.gr/
 * @since      1.0.0
 *
 * @package    Cardlink_Payment_Gateway
 * @subpackage Cardlink_Payment_Gateway/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Cardlink_Payment_Gateway
 * @subpackage Cardlink_Payment_Gateway/admin
 * @author     Cardlink <info@cardlink.gr>
 */
class Cardlink_Payment_Gateway_Admin {

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
	 * @param      string $plugin_name The name of this plugin.
	 * @param      string $version The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/cardlink-payment-gateway-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
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

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/cardlink-payment-gateway-admin.js', array( 'jquery' ), $this->version, false );

		wp_localize_script( $this->plugin_name, 'crlGatewayStrings', array(
			'noOfInstallments' => __( 'Number of installments', 'cardlink-payment-gateway' ),
			'totalOrderAmount' => __( 'Total order amount', 'cardlink-payment-gateway' ),
			'addVariation'     => __( 'Add variation', 'cardlink-payment-gateway' ),
		) );
	}

	public function load_payment_gateway() {

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-cardlink-payment-gateway-woocommerce.php';

	}

	public function cardlink_message() {
		$order_id = absint( get_query_var( 'order-received' ) );
		$order    = new WC_Order( $order_id );
		if ( method_exists( $order, 'get_payment_method' ) ) {
			$payment_method = $order->get_payment_method();
		} else {
			$payment_method = $order->payment_method;
		}
		if ( is_order_received_page() && ( 'cardlink_payment_gateway_woocommerce' == $payment_method || 'cardlink_payment_gateway_woocommerce_iris' == $payment_method ) ) {
			if ( method_exists( $order, 'get_meta' ) ) {
				$cardlink_message = $order->get_meta( '_cardlink_message', true );
			} else {
				$cardlink_message = get_post_meta( $order_id, '_cardlink_message' );
			}
			if ( ! empty( $cardlink_message ) ) {
				$message      = $cardlink_message['message'];
				$message_type = $cardlink_message['message_type'];
				if ( method_exists( $order, 'delete_meta_data' ) ) {
					$order->delete_meta_data( '_cardlink_message' );
					$order->save_meta_data();
				} else {
					delete_post_meta( $order_id, '_cardlink_message' );
				}
				wc_add_notice( $message, $message_type );
			}
		}
	}

	public function woocommerce_add_cardlink_gateway( $methods ) {

		$methods[] = 'Cardlink_Payment_Gateway_Woocommerce';
		$methods[] = 'Cardlink_Payment_Gateway_Woocommerce_Iris';

		// $methods[] = 'WC_cardlink_Gateway_masterpass';

		return $methods;
	}
}

