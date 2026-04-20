<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Blocks;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentContext;
use Automattic\WooCommerce\Blocks\Payments\PaymentResult;
use Flavor\CardlinkPaymentGateway\Installment\InstallmentCalculator;

final class CardlinkBlockSupport extends AbstractPaymentMethodType {

    protected $name = 'cardlink_payment_gateway_block';

    private string $plugin_path;
    private string $plugin_url;
    private string $version;
    private array $gateway_settings = [];

    public function __construct( string $plugin_path, string $plugin_url, string $version ) {
        $this->plugin_path = $plugin_path;
        $this->plugin_url  = $plugin_url;
        $this->version     = $version;
    }

    public function initialize(): void {
        $this->gateway_settings = get_option( 'woocommerce_cardlink_payment_gateway_woocommerce_settings', [] );

        add_action(
            'woocommerce_rest_checkout_process_payment_with_context',
            [ $this, 'add_payment_request_order_meta' ],
            10,
            2
        );
    }

    public function is_active(): bool {
        return ( $this->gateway_settings['enabled'] ?? 'no' ) === 'yes';
    }

    public function get_payment_method_script_handles(): array {
        $asset_path = $this->plugin_path . 'assets/js/blocks/build/cardlink-block.min.asset.php';
        $asset      = file_exists( $asset_path ) ? require $asset_path : [
            'dependencies' => [],
            'version'      => $this->version,
        ];

        wp_register_script(
            'wc-cardlink-payments-block',
            $this->plugin_url . 'assets/js/blocks/build/cardlink-block.min.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        if ( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations( 'wc-cardlink-payments-block', 'cardlink-payment-gateway' );
        }

        return [ 'wc-cardlink-payments-block' ];
    }

    public function get_payment_method_data(): array {
        $tokenization = ( $this->gateway_settings['tokenization'] ?? 'no' ) === 'yes';
        $installments = (int) ( $this->gateway_settings['installments'] ?? 1 );
        $variation    = $this->gateway_settings['installments_variation'] ?? '';

        // Parse variation rules into a map: { amount: max_installments }.
        $calculator = new InstallmentCalculator();
        $rules      = $calculator->parse_variation_rules( $variation );
        $variations = [];
        foreach ( $rules as $rule ) {
            $variations[ (string) $rule['amount'] ] = $rule['max'];
        }

        return [
            'title'                  => $this->gateway_settings['title'] ?? '',
            'description'            => $this->gateway_settings['description'] ?? '',
            'supports'               => [ 'products' ],
            'tokenization'           => $tokenization,
            'installments'           => $installments,
            'installment_variations' => $variations,
        ];
    }

    /**
     * @param PaymentContext $context
     * @param PaymentResult $result
     */
    public function add_payment_request_order_meta( $context, &$result ): void {
        $payment_method = $context->payment_method ?? '';
        if ( $payment_method !== 'cardlink_payment_gateway_woocommerce' ) {
            return;
        }

        $data     = $context->payment_data ?? [];
        $order    = $context->order;
        $order_id = $order->get_id();

        // Map block checkout data to our expected POST keys.
        $store_card = ! empty( $data['wc-cardlink_payment_gateway_woocommerce-new-payment-method'] )
            ? 'on' : '';

        // Installments.
        $installments = (int) ( $data['installmentsvalue'] ?? 1 );

        // Token - WooCommerce Blocks passes the token database ID, resolve it to the actual token string.
        $token_id = $data['token'] ?? '';
        $token_value = '';
        if ( ! empty( $token_id ) ) {
            $token_store = new \WC_Payment_Token_Data_Store();
            $token_obj   = $token_store->get_token_by_id( (int) $token_id );
            if ( $token_obj ) {
                $token_value = $token_obj->token;
            }
        }

        if ( $installments > 0 ) {
            $order->update_meta_data( '_doseis', $installments );
        }
        if ( ! empty( $store_card ) ) {
            $order->update_meta_data( '_cardlink_store_card', $store_card );
        }
        if ( ! empty( $token_value ) ) {
            $order->update_meta_data( '_cardlink_card', $token_value );
        }

        $order->save_meta_data();
    }
}
