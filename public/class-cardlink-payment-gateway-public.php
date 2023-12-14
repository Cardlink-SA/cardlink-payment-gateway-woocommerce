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

		// You can remove this block if you don't use WPML
/* 		if ( function_exists( 'icl_object_id' ) ) {
			global $sitepress;

			$current_lang = $sitepress->get_current_language();
			wp_localize_script( 'main', 'i18n', array(
				'lang' => $current_lang
			) );

			$ajax_url_params['lang'] = $current_lang;
		} */

		wp_localize_script($this->plugin_name, 'urls', array(
			'home'   => home_url(),
			'theme'  => get_template_directory(),
			'plugins' => plugins_url(),
			'assets' => get_stylesheet_directory_uri() . '/assets',
			'ajax'   => add_query_arg( $ajax_url_params, admin_url( 'admin-ajax.php' ) )
		) ); 

	}

	public function woocommerce_make_phone_number_required( $address_fields ) {

		if (!array_key_exists('billing_phone', $address_fields)) {
			$address_fields['billing_phone']['required'] = true;
			$address_fields['billing_phone']['label'] = __( 'Phone', 'woocommerce' );
		} else {
			$address_fields['billing_phone']['required'] = true;
		}

		return $address_fields;
	}

}
