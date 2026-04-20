<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Gateway;

defined( 'ABSPATH' ) || exit;

use Flavor\CardlinkPaymentGateway\Acquirer\AcquirerRegistry;
use Flavor\CardlinkPaymentGateway\Admin\SettingsFields;
use Flavor\CardlinkPaymentGateway\Database\TransactionRepository;
use Flavor\CardlinkPaymentGateway\Installment\InstallmentRenderer;
use Flavor\CardlinkPaymentGateway\Payment\DigestService;
use Flavor\CardlinkPaymentGateway\Payment\FormBuilder;
use Flavor\CardlinkPaymentGateway\Payment\FormRenderer;
use Flavor\CardlinkPaymentGateway\Payment\ResponseHandler;
use Flavor\CardlinkPaymentGateway\Support\Logger;
use Flavor\CardlinkPaymentGateway\Support\OrderHelper;
use Flavor\CardlinkPaymentGateway\Token\TokenFormRenderer;
use Flavor\CardlinkPaymentGateway\Token\TokenManager;
use WC_Payment_Token_CC;

class CardlinkGateway extends AbstractCardlinkGateway {

    private static ?CardlinkGateway $_instance = null;

    private TokenManager $token_manager;
    private TokenFormRenderer $token_form_renderer;
    private InstallmentRenderer $installment_renderer;

    protected string $tokenization_setting = 'no';
    protected int $installments = 1;
    protected string $installments_variation = '';
    protected string $transaction_type = 'no';
    protected string $xml_api = 'no';

    public static function instance(
        DigestService $digest_service,
        FormBuilder $form_builder,
        FormRenderer $form_renderer,
        ResponseHandler $response_handler,
        AcquirerRegistry $acquirer_registry,
        TransactionRepository $transaction_repo,
        Logger $logger,
        OrderHelper $order_helper,
        TokenManager $token_manager,
        TokenFormRenderer $token_form_renderer,
        InstallmentRenderer $installment_renderer
    ): self {
        if ( null === self::$_instance ) {
            self::$_instance = new self(
                $digest_service, $form_builder, $form_renderer, $response_handler,
                $acquirer_registry, $transaction_repo, $logger, $order_helper,
                $token_manager, $token_form_renderer, $installment_renderer
            );
        }
        return self::$_instance;
    }

    public static function get_instance(): ?self {
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
        TokenManager $token_manager,
        TokenFormRenderer $token_form_renderer,
        InstallmentRenderer $installment_renderer
    ) {
        $this->id                 = 'cardlink_payment_gateway_woocommerce';
        $this->has_fields         = true;
        $this->api_class_name     = 'Cardlink_Payment_Gateway_Woocommerce';
        $this->method_title       = __( 'Cardlink Payment Gateway', 'cardlink-payment-gateway' );
        $this->method_description = __( 'Cardlink Payment Gateway allows you to accept payment through various schemes such as Visa, Mastercard, Maestro, American Express, Diners, Discover cards on your Woocommerce Powered Site.', 'cardlink-payment-gateway' );

        $this->inject_services(
            $digest_service, $form_builder, $form_renderer, $response_handler,
            $acquirer_registry, $transaction_repo, $logger, $order_helper
        );

        $this->token_manager       = $token_manager;
        $this->token_form_renderer = $token_form_renderer;
        $this->installment_renderer = $installment_renderer;

        $this->init_form_fields();
        $this->init_settings();
        $this->load_settings();
        $this->configure_logger();

        // Set supports based on XML API setting.
        $this->supports = [ 'products' ];
        if ( $this->xml_api === 'yes' ) {
            $this->supports[] = 'refunds';
        }

        // Set icon based on acquirer.
        $this->set_gateway_icon();

        // Hooks.
        add_action( 'woocommerce_receipt_' . $this->id, [ $this, 'receipt_page' ] );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
        add_action( 'woocommerce_api_' . strtolower( $this->api_class_name ), [ $this, 'check_cardlink_response' ] );

        // Tokenization support.
        if ( $this->tokenization_setting === 'yes' ) {
            add_filter( 'woocommerce_payment_gateway_supports', [ $this, 'filter_gateway_supports' ], 10, 3 );
            add_action( 'woocommerce_after_account_payment_methods', [ $this, 'add_payment_method_form_output' ] );
        }
    }

    public function init_form_fields(): void {
        $this->form_fields = SettingsFields::cardlink_fields( $this->acquirer_registry ?? new AcquirerRegistry() );
    }

    private function load_settings(): void {
        $this->title                  = $this->get_option( 'title' );
        $this->description            = $this->get_option( 'description' );
        $this->merchant_id            = $this->get_option( 'merchant_id' );
        $this->shared_secret_key      = $this->get_option( 'shared_secret_key' );
        $this->environment            = $this->get_option( 'environment', 'no' );
        $this->acquirer_index         = (int) $this->get_option( 'acquirer', '0' );
        $this->tokenization_setting   = $this->get_option( 'tokenization', 'no' );
        $this->installments           = (int) $this->get_option( 'installments', '1' );
        $this->installments_variation = $this->get_option( 'installments_variation', '' );
        $this->transaction_type       = $this->get_option( 'transaction_type', 'no' );
        $this->xml_api                = $this->get_option( 'xml_api', 'no' );
        $this->popup                  = $this->get_option( 'popup', 'no' );
        $this->css_url                = $this->get_option( 'css_url', '' );
        $this->enable_log             = $this->get_option( 'enable_log', 'no' );
        $this->redirect_page_id       = $this->get_option( 'redirect_page_id', '-1' );
        $this->order_note             = $this->get_option( 'order_note', '' );
    }

    private function set_gateway_icon(): void {
        $plugin_url = FLAVOR_CARDLINK_URL;
        switch ( $this->acquirer_index ) {
            case 1:
                $this->icon = apply_filters( 'cardlink_icon', $plugin_url . 'assets/img/cardlink.png' );
                break;
            case 2:
                $this->icon = apply_filters( 'cardlink_icon', $plugin_url . 'assets/img/cardlink.png' );
                break;
            default:
                $this->icon = apply_filters( 'cardlink_icon', $plugin_url . 'assets/img/cardlink.png' );
                break;
        }
    }

    /**
     * Display payment fields on checkout.
     */
    public function payment_fields(): void {
        if ( $this->description ) {
            echo '<p>' . wp_kses_post( $this->description ) . '</p>';
        }

        // Tokenization UI.
        if ( $this->tokenization_setting === 'yes' && is_user_logged_in() ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in TokenFormRenderer::render().
            echo $this->token_form_renderer->render( $this->id, get_current_user_id() );
        }

        // Installments UI.
        $amount = 0;
        if ( WC()->cart ) {
            $amount = (float) WC()->cart->get_total( 'edit' );
        }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in InstallmentRenderer::render().
        echo $this->installment_renderer->render(
            $this->id,
            $amount,
            $this->installments,
            $this->installments_variation
        );
    }

    /**
     * Generate the payment redirect form.
     */
    protected function generate_payment_form( int $order_id ): string {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return '';
        }

        // Log transaction in DB.
        $this->transaction_repo->insert( $order_id );

        // Store order in session.
        if ( WC()->session ) {
            WC()->session->set( 'order_id', $order_id );
        }

        // Determine transaction type.
        $tr_type = ( $this->transaction_type === 'yes' ) ? 2 : 1;

        // Determine installments.
        $doseis = (int) $this->order_helper->get_meta( $order, '_doseis' );
        if ( $doseis <= 0 ) {
            $doseis = 1;
        }

        // Determine token options.
        $ext_token_options = null;
        $ext_token         = null;

        $store_card   = $this->order_helper->get_meta( $order, '_cardlink_store_card' );
        $selected_card = $this->order_helper->get_meta( $order, '_cardlink_card' );

        if ( $this->tokenization_setting === 'yes' ) {
            if ( ! empty( $selected_card ) && $selected_card !== 'new' ) {
                $ext_token_options = 110;
                $ext_token         = $selected_card;
            } elseif ( ! empty( $store_card ) && $store_card === 'on' ) {
                $ext_token_options = 100;
            }
        }

        $confirm_url = $this->get_confirm_url( 'success' );
        $cancel_url  = $this->get_confirm_url( 'failure' );

        // Build form fields.
        $fields = $this->form_builder->build_payment_fields(
            $order,
            $this->merchant_id,
            $confirm_url,
            $cancel_url,
            $tr_type,
            $this->css_url,
            $doseis,
            $ext_token,
            $ext_token_options
        );

        // Compute digest.
        $digest = $this->digest_service->compute_for_fields( $fields, $this->shared_secret_key );
        $this->logger->log_digest( implode( '', $fields ), $digest );

        // Render form.
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

    /**
     * Handle Cardlink payment response callback.
     */
    public function check_cardlink_response(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Callback from external payment gateway, verified via digest.
        $post_data = array_map( 'sanitize_text_field', wp_unslash( $_POST ) );

        $order = $this->response_handler->handle(
            $post_data,
            $this->shared_secret_key,
            $this->id,
            ! empty( $this->order_note )
        );

        $redirect_url = $this->get_redirect_url( $order );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Add payment method from account page.
     */
    public function add_payment_method(): array {
        $customer = WC()->customer;
        $user_id  = get_current_user_id();

        $confirm_url = get_rest_url( null, 'wc-cardlink/v1/tokenizer' ) . '?result=success';
        $cancel_url  = get_rest_url( null, 'wc-cardlink/v1/tokenizer' ) . '?result=failure';

        $fields = $this->form_builder->build_tokenizer_fields(
            $user_id,
            $customer->get_billing_email(),
            $customer->get_billing_country(),
            $customer->get_billing_postcode(),
            $customer->get_billing_city(),
            $customer->get_billing_address_1(),
            $this->merchant_id,
            $confirm_url,
            $cancel_url,
            $this->css_url
        );

        // Compute digest.
        $digest = $this->digest_service->compute_for_fields( $fields, $this->shared_secret_key );
        $fields['digest'] = $digest;

        // Encode fields for URL transport.
        $encoded = base64_encode( wp_json_encode( $fields ) );
        $url     = wc_get_endpoint_url( 'payment-methods' );
        $url     = add_query_arg( [
            'url'                => $this->get_post_url(),
            'add_payment_method' => $encoded,
        ], $url );

        return [
            'result'   => '',
            'redirect' => $url,
        ];
    }

    /**
     * Render the add-payment-method form on account page.
     */
    public function add_payment_method_form_output(): void {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- URL params from payment gateway redirect.
        // Show tokenizer message if set.
        if ( isset( $_GET['tok_message'] ) ) {
            $msg = sanitize_text_field( wp_unslash( $_GET['tok_message'] ) );
            wc_add_notice( $msg, 'notice' );
        }

        // Check for pending add-payment-method redirect.
        if ( ! isset( $_GET['add_payment_method'] ) ) {
            return;
        }

        $post_url = isset( $_GET['url'] ) ? esc_url_raw( wp_unslash( $_GET['url'] ) ) : '';
        $data     = json_decode( base64_decode( sanitize_text_field( wp_unslash( $_GET['add_payment_method'] ) ) ), true );
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if ( ! is_array( $data ) || empty( $post_url ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in FormRenderer::render_tokenizer_form().
        echo $this->form_renderer->render_tokenizer_form( $post_url, $data );
    }

    /**
     * Filter to add tokenization support capabilities.
     */
    public function filter_gateway_supports( bool $supports, string $feature, $gateway ): bool {
        if ( $gateway !== $this ) {
            return $supports;
        }

        if ( in_array( $feature, [ 'tokenization', 'add_payment_method' ], true ) ) {
            return true;
        }

        return $supports;
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

    // Expose settings for IRIS gateway.
    public function get_merchant_id(): string {
        return $this->merchant_id;
    }

    public function get_environment(): string {
        return $this->environment;
    }

    public function get_acquirer_index(): int {
        return $this->acquirer_index;
    }

    public function get_enable_log(): string {
        return $this->enable_log;
    }

    public function get_css_url(): string {
        return $this->css_url;
    }

    public function get_popup(): string {
        return $this->popup;
    }

    public function get_redirect_page_id(): string {
        return $this->redirect_page_id;
    }

    public function get_order_note(): string {
        return $this->order_note;
    }

    public function get_tokenization_setting(): string {
        return $this->tokenization_setting;
    }

    public function get_installments(): int {
        return $this->installments;
    }

    public function get_installments_variation(): string {
        return $this->installments_variation;
    }

    public function get_xml_api(): string {
        return $this->xml_api;
    }

    public function is_xml_api_enabled(): bool {
        return $this->xml_api === 'yes';
    }

    /**
     * Render a static (non-editable) URL field for WooCommerce settings.
     *
     * @param string $key  Field key.
     * @param array  $data Field data.
     * @return string
     */
    public function generate_cardlink_static_url_html( string $key, array $data ): string {
        $field_key   = $this->get_field_key( $key );
        $defaults    = [ 'title' => '', 'description' => '' ];
        $data        = wp_parse_args( $data, $defaults );
        $url         = get_rest_url( null, 'wc-cardlink/v1/payment' );

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
            </th>
            <td class="forminp">
                <code style="padding:6px 10px;display:inline-block;font-size:13px;"><?php echo esc_html( $url ); ?></code>
                <?php if ( ! empty( $data['description'] ) ) : ?>
                    <p class="description"><?php echo wp_kses_post( $data['description'] ); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }
}
