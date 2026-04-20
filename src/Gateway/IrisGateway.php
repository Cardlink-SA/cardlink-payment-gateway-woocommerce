<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Gateway;

defined( 'ABSPATH' ) || exit;

use Flavor\CardlinkPaymentGateway\Acquirer\AcquirerRegistry;
use Flavor\CardlinkPaymentGateway\Admin\SettingsFields;
use Flavor\CardlinkPaymentGateway\Database\TransactionRepository;
use Flavor\CardlinkPaymentGateway\Payment\DigestService;
use Flavor\CardlinkPaymentGateway\Payment\FormBuilder;
use Flavor\CardlinkPaymentGateway\Payment\FormRenderer;
use Flavor\CardlinkPaymentGateway\Payment\ResponseHandler;
use Flavor\CardlinkPaymentGateway\Support\Logger;
use Flavor\CardlinkPaymentGateway\Support\OrderHelper;
use WC_Admin_Settings;

class IrisGateway extends AbstractCardlinkGateway {

    private static ?IrisGateway $_instance = null;
    private CardlinkGateway $parent_gateway;

    protected string $iris_acquirer = 'inherit';
    protected string $iris_merchant_id = '';
    protected string $iris_shared_secret_key = '';

    public static function instance(
        DigestService $digest_service,
        FormBuilder $form_builder,
        FormRenderer $form_renderer,
        ResponseHandler $response_handler,
        AcquirerRegistry $acquirer_registry,
        TransactionRepository $transaction_repo,
        Logger $logger,
        OrderHelper $order_helper,
        CardlinkGateway $parent_gateway
    ): self {
        if ( null === self::$_instance ) {
            self::$_instance = new self(
                $digest_service, $form_builder, $form_renderer, $response_handler,
                $acquirer_registry, $transaction_repo, $logger, $order_helper,
                $parent_gateway
            );
        }
        return self::$_instance;
    }

    private function __construct(
        DigestService $digest_service,
        FormBuilder $form_builder,
        FormRenderer $form_renderer,
        ResponseHandler $response_handler,
        AcquirerRegistry $acquirer_registry,
        TransactionRepository $transaction_repo,
        Logger $logger,
        OrderHelper $order_helper,
        CardlinkGateway $parent_gateway
    ) {
        $this->id                 = 'cardlink_payment_gateway_woocommerce_iris';
        $this->has_fields         = true;
        $this->api_class_name     = 'Cardlink_Payment_Gateway_Woocommerce_Iris';
        $this->method_title       = __( 'Cardlink Payment Gateway with IRIS', 'cardlink-payment-gateway' );
        $this->method_description = __( 'Cardlink Payment Gateway allows you to accept payment through IRIS on your Woocommerce Powered Site.', 'cardlink-payment-gateway' );

        $this->inject_services(
            $digest_service, $form_builder, $form_renderer, $response_handler,
            $acquirer_registry, $transaction_repo, $logger, $order_helper
        );

        $this->parent_gateway = $parent_gateway;

        $this->init_form_fields();
        $this->init_settings();
        $this->load_settings();

        // Always inherit these shared settings from the parent gateway.
        $this->environment      = $parent_gateway->get_environment();
        $this->enable_log       = $parent_gateway->get_enable_log();
        $this->css_url          = $parent_gateway->get_css_url();
        $this->popup            = $parent_gateway->get_popup();
        $this->redirect_page_id = $parent_gateway->get_redirect_page_id();

        // Conditionally override merchant_id, shared_secret_key and acquirer_index.
        if ( $this->iris_acquirer === 'inherit' ) {
            $this->merchant_id       = $parent_gateway->get_merchant_id();
            $this->shared_secret_key = $parent_gateway->get_shared_secret_key();
            $this->acquirer_index    = $parent_gateway->get_acquirer_index();
        } else {
            $this->acquirer_index    = (int) $this->iris_acquirer;
            $this->merchant_id       = ! empty( $this->iris_merchant_id )
                                        ? $this->iris_merchant_id
                                        : $parent_gateway->get_merchant_id();
            $this->shared_secret_key = ! empty( $this->iris_shared_secret_key )
                                        ? $this->iris_shared_secret_key
                                        : $parent_gateway->get_shared_secret_key();
        }

        // IRIS does not support secondary XML API transactions (capture, refund, void).
        $this->supports = [ 'products' ];

        $this->configure_logger();

        // Hooks.
        add_action( 'woocommerce_receipt_' . $this->id, [ $this, 'receipt_page' ] );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
        add_action( 'woocommerce_api_' . strtolower( $this->api_class_name ), [ $this, 'check_cardlink_response' ] );
    }

    public function process_admin_options(): bool {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by WooCommerce before calling process_admin_options.
        $posted_acquirer = isset( $_POST[ $this->get_field_key( 'iris_acquirer' ) ] )
            ? sanitize_text_field( wp_unslash( $_POST[ $this->get_field_key( 'iris_acquirer' ) ] ) )
            : 'inherit';

        if ( $posted_acquirer !== 'inherit' ) {
            // phpcs:disable WordPress.Security.NonceVerification.Missing
            $posted_mid    = isset( $_POST[ $this->get_field_key( 'iris_merchant_id' ) ] )
                ? sanitize_text_field( wp_unslash( $_POST[ $this->get_field_key( 'iris_merchant_id' ) ] ) )
                : '';
            $posted_secret = isset( $_POST[ $this->get_field_key( 'iris_shared_secret_key' ) ] )
                ? sanitize_text_field( wp_unslash( $_POST[ $this->get_field_key( 'iris_shared_secret_key' ) ] ) )
                : '';
            // phpcs:enable WordPress.Security.NonceVerification.Missing

            $errors = [];
            if ( empty( $posted_mid ) ) {
                $errors[] = __( 'IRIS Merchant ID is required when not inheriting from Cardlink Payment Gateway.', 'cardlink-payment-gateway' );
            }
            if ( empty( $posted_secret ) ) {
                $errors[] = __( 'IRIS Shared Secret Key is required when not inheriting from Cardlink Payment Gateway.', 'cardlink-payment-gateway' );
            }

            if ( ! empty( $errors ) ) {
                foreach ( $errors as $error ) {
                    WC_Admin_Settings::add_error( $error );
                }
                return false;
            }
        }

        return parent::process_admin_options();
    }

    public function init_form_fields(): void {
        $this->form_fields = SettingsFields::iris_fields( $this->acquirer_registry );
    }

    private function load_settings(): void {
        $this->title                   = $this->get_option( 'title' );
        $this->description             = $this->get_option( 'description' );
        $this->iris_acquirer           = $this->get_option( 'iris_acquirer', 'inherit' );
        $this->iris_merchant_id        = $this->get_option( 'iris_merchant_id', '' );
        $this->iris_shared_secret_key  = $this->get_option( 'iris_shared_secret_key', '' );
    }

    /**
     * IRIS has no payment fields on checkout (no tokenization, no installments).
     */
    public function payment_fields(): void {
        if ( $this->description ) {
            echo '<p>' . wp_kses_post( $this->description ) . '</p>';
        }
    }

    protected function generate_payment_form( int $order_id ): string {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return '';
        }

        $this->transaction_repo->insert( $order_id );

        if ( WC()->session ) {
            WC()->session->set( 'order_id', $order_id );
        }

        // IRIS always uses trType 1 for purchases (payMethod=IRIS is the differentiator).
        $tr_type = 1;

        $confirm_url = $this->get_confirm_url( 'success' );
        $cancel_url  = $this->get_confirm_url( 'failure' );

        $fields = $this->form_builder->build_payment_fields(
            $order,
            $this->merchant_id,
            $confirm_url,
            $cancel_url,
            $tr_type,
            $this->css_url,
            0,       // No installments.
            null,    // No token.
            null,    // No token options.
            'IRIS'   // Pay method.
        );

        $digest = $this->digest_service->compute_for_fields( $fields, $this->shared_secret_key );
        $this->logger->log_digest( implode( '', $fields ), $digest );

        $use_iframe = ( $this->popup === 'yes' );
        return $this->form_renderer->render_payment_form(
            $this->get_post_url(),
            $fields,
            $digest,
            $use_iframe,
            $this->id,
            $order_id
        );
    }

    public function check_cardlink_response(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Callback from external payment gateway, verified via digest.
        $post_data = array_map( 'sanitize_text_field', wp_unslash( $_POST ) );

        $order = $this->response_handler->handle(
            $post_data,
            $this->shared_secret_key,
            $this->id
        );

        $redirect_url = $this->get_redirect_url( $order );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    public function admin_options(): void {
        echo '<h3>' . esc_html( $this->method_title ) . '</h3>';
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '">';
        echo esc_html__( '< Back', 'cardlink-payment-gateway' );
        echo '</a></p>';
        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
    }
}
