<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway;

defined( 'ABSPATH' ) || exit;

use Flavor\CardlinkPaymentGateway\Acquirer\AcquirerRegistry;
use Flavor\CardlinkPaymentGateway\Admin\AdminAssets;
use Flavor\CardlinkPaymentGateway\Admin\OrderActions;
use Flavor\CardlinkPaymentGateway\Admin\OrderMessageHandler;
use Flavor\CardlinkPaymentGateway\Admin\OrderMetaBox;
use Flavor\CardlinkPaymentGateway\Ajax\AjaxHandler;
use Flavor\CardlinkPaymentGateway\Blocks\BlocksIntegration;
use Flavor\CardlinkPaymentGateway\Database\TransactionRepository;
use Flavor\CardlinkPaymentGateway\Frontend\FrontendAssets;
use Flavor\CardlinkPaymentGateway\Gateway\AbstractCardlinkGateway;
use Flavor\CardlinkPaymentGateway\Gateway\CardlinkGateway;
use Flavor\CardlinkPaymentGateway\Gateway\IrisGateway;
use Flavor\CardlinkPaymentGateway\Installment\InstallmentCalculator;
use Flavor\CardlinkPaymentGateway\Installment\InstallmentRenderer;
use Flavor\CardlinkPaymentGateway\Payment\DigestService;
use Flavor\CardlinkPaymentGateway\Payment\FormBuilder;
use Flavor\CardlinkPaymentGateway\Payment\FormRenderer;
use Flavor\CardlinkPaymentGateway\Payment\ResponseHandler;
use Flavor\CardlinkPaymentGateway\Rest\PaymentConfirmationEndpoint;
use Flavor\CardlinkPaymentGateway\Rest\RestController;
use Flavor\CardlinkPaymentGateway\Rest\TokenizerEndpoint;
use Flavor\CardlinkPaymentGateway\Support\LocaleHelper;
use Flavor\CardlinkPaymentGateway\Support\Logger;
use Flavor\CardlinkPaymentGateway\Support\OrderHelper;
use Flavor\CardlinkPaymentGateway\Token\TokenFormRenderer;
use Flavor\CardlinkPaymentGateway\Token\TokenManager;
use Flavor\CardlinkPaymentGateway\XmlApi\XmlApiService;

final class Plugin {

    private static ?Plugin $instance = null;

    private string $version;
    private string $plugin_file;
    private string $plugin_path;
    private string $plugin_url;

    private function __construct( string $plugin_file ) {
        $this->plugin_file = $plugin_file;
        $this->plugin_path = plugin_dir_path( $plugin_file );
        $this->plugin_url  = plugin_dir_url( $plugin_file );
        $this->version     = FLAVOR_CARDLINK_VERSION;
    }

    public static function instance( string $plugin_file = '' ): self {
        if ( null === self::$instance ) {
            self::$instance = new self( $plugin_file );
        }
        return self::$instance;
    }

    public function init(): void {
        $this->load_textdomain();
        $this->register_services();
    }

    private function load_textdomain(): void {
        load_plugin_textdomain(
            'cardlink-payment-gateway',
            false,
            dirname( plugin_basename( $this->plugin_file ) ) . '/languages'
        );
    }

    private function register_services(): void {
        // Support services.
        $logger              = new Logger();
        $order_helper        = new OrderHelper();
        $locale_helper       = new LocaleHelper();
        $acquirer_registry   = new AcquirerRegistry();
        $transaction_repo    = new TransactionRepository();
        $digest_service      = new DigestService();
        $token_manager       = new TokenManager();
        $installment_calc    = new InstallmentCalculator();
        $installment_render  = new InstallmentRenderer( $installment_calc );
        $token_form_renderer = new TokenFormRenderer( $this->plugin_url );
        $form_builder        = new FormBuilder( $locale_helper );
        $form_renderer       = new FormRenderer();

        $response_handler = new ResponseHandler(
            $digest_service,
            $token_manager,
            $order_helper,
            $logger
        );

        // XML API service for secondary operations (capture, refund, void, status).
        $xml_api_service = new XmlApiService( $logger );

        // Register gateways.
        add_filter( 'woocommerce_payment_gateways', function ( array $gateways ) use (
            $digest_service, $form_builder, $form_renderer, $response_handler,
            $acquirer_registry, $transaction_repo, $logger, $order_helper,
            $token_manager, $token_form_renderer, $installment_render,
            $xml_api_service
        ): array {
            $cardlink = CardlinkGateway::instance(
                $digest_service, $form_builder, $form_renderer, $response_handler,
                $acquirer_registry, $transaction_repo, $logger, $order_helper,
                $token_manager, $token_form_renderer, $installment_render
            );
            $cardlink->set_xml_api_service( $xml_api_service );

            $iris = IrisGateway::instance(
                $digest_service, $form_builder, $form_renderer, $response_handler,
                $acquirer_registry, $transaction_repo, $logger, $order_helper,
                $cardlink
            );
            $iris->set_xml_api_service( $xml_api_service );

            $gateways[] = $cardlink;
            $gateways[] = $iris;
            return $gateways;
        } );

        // Admin assets.
        $admin_assets = new AdminAssets( $this->plugin_url, $this->version );
        add_action( 'admin_enqueue_scripts', [ $admin_assets, 'enqueue' ] );

        // Frontend assets.
        $frontend_assets = new FrontendAssets( $this->plugin_url, $this->version );
        add_action( 'wp_enqueue_scripts', [ $frontend_assets, 'enqueue' ] );

        // Order message on thank-you page.
        $order_message = new OrderMessageHandler( $order_helper );
        add_action( 'wp', [ $order_message, 'display_on_thankyou' ] );

        // Display payment error notices on the order-pay page (after 3DS failure redirect).
        add_action( 'before_woocommerce_pay', [ AbstractCardlinkGateway::class, 'display_payment_error_notice' ] );

        // Admin order meta box and order actions.
        $order_meta_box = new OrderMetaBox( $xml_api_service, $order_helper );
        add_action( 'add_meta_boxes', [ $order_meta_box, 'register' ] );
        add_action( 'wp_ajax_cardlink_xml_action', [ $order_meta_box, 'handle_ajax' ] );

        $order_actions = new OrderActions( $xml_api_service, $order_helper );
        add_filter( 'woocommerce_order_actions', [ $order_actions, 'add_actions' ], 10, 2 );
        add_action( 'woocommerce_order_action_cardlink_capture', [ $order_actions, 'capture' ] );
        add_action( 'woocommerce_order_action_cardlink_void', [ $order_actions, 'void' ] );

        // AJAX handlers.
        $ajax = new AjaxHandler( $token_manager, $order_helper, $token_form_renderer );
        $ajax->register();

        // REST API.
        $payment_endpoint   = new PaymentConfirmationEndpoint( $response_handler );
        $tokenizer_endpoint = new TokenizerEndpoint( $digest_service, $token_manager );
        $rest_controller    = new RestController( $payment_endpoint, $tokenizer_endpoint );
        add_action( 'rest_api_init', [ $rest_controller, 'register_routes' ] );

        // WooCommerce Blocks.
        add_action( 'woocommerce_blocks_loaded', function () {
            $integration = new BlocksIntegration( $this->plugin_path, $this->plugin_url, $this->version );
            $integration->register();
        } );
    }

    public function get_version(): string {
        return $this->version;
    }

    public function get_plugin_file(): string {
        return $this->plugin_file;
    }

    public function get_plugin_path(): string {
        return $this->plugin_path;
    }

    public function get_plugin_url(): string {
        return $this->plugin_url;
    }
}
