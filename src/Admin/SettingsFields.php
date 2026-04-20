<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Admin;

defined( 'ABSPATH' ) || exit;

use Flavor\CardlinkPaymentGateway\Acquirer\AcquirerRegistry;

class SettingsFields {

    /**
     * Return form_fields for the main Cardlink gateway.
     */
    public static function cardlink_fields( AcquirerRegistry $registry ): array {
        return [
            'enabled'                => [
                'title'       => __( 'Enable/Disable', 'cardlink-payment-gateway' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable Cardlink Payment Gateway', 'cardlink-payment-gateway' ),
                'description' => __( 'Enable or disable the gateway.', 'cardlink-payment-gateway' ),
                'desc_tip'    => true,
                'default'     => 'yes',
            ],
            'environment'            => [
                'title'   => __( 'Test Environment', 'cardlink-payment-gateway' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Cardlink Test Environment', 'cardlink-payment-gateway' ),
                'default' => 'no',
            ],
            'acquirer'               => [
                'title'       => __( 'Select Acquirer', 'cardlink-payment-gateway' ),
                'type'        => 'select',
                'options'     => $registry->get_names(),
                'description' => __( 'Select your acquirer bank', 'cardlink-payment-gateway' ),
            ],
            'title'                  => [
                'title'       => __( 'Title', 'cardlink-payment-gateway' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'cardlink-payment-gateway' ),
                'desc_tip'    => true,
                'default'     => __( 'Credit card via Cardlink', 'cardlink-payment-gateway' ),
            ],
            'description'            => [
                'title'       => __( 'Description', 'cardlink-payment-gateway' ),
                'type'        => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.', 'cardlink-payment-gateway' ),
                'desc_tip'    => true,
                'default'     => __( 'Pay Via Cardlink: Accepts Visa, Mastercard, Maestro, American Express, Diners, Discover.', 'cardlink-payment-gateway' ),
            ],
            'merchant_id'            => [
                'title'       => __( 'Merchant ID', 'cardlink-payment-gateway' ),
                'type'        => 'text',
                'description' => __( 'Enter Your Cardlink Merchant ID', 'cardlink-payment-gateway' ),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'shared_secret_key'      => [
                'title'       => __( 'Shared Secret key', 'cardlink-payment-gateway' ),
                'type'        => 'password',
                'description' => __( 'Enter your Shared Secret key', 'cardlink-payment-gateway' ),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'installments'           => [
                'title'       => __( 'Maximum number of installments regardless of the total order amount', 'cardlink-payment-gateway' ),
                'type'        => 'select',
                'options'     => self::get_installment_options(),
                'description' => __( '1 to 60 Installments, 1 for one time payment. You must contact Cardlink first.', 'cardlink-payment-gateway' ),
            ],
            'installments_variation' => [
                'title'       => __( 'Maximum number of installments depending on the total order amount', 'cardlink-payment-gateway' ),
                'type'        => 'hidden',
                'class'       => 'installments-variation',
                'description' => __( 'Add amount and installments for each row. The limit is 60.', 'cardlink-payment-gateway' ),
            ],
            'transaction_type'       => [
                'title'       => __( 'Pre-Authorize', 'cardlink-payment-gateway' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable to capture preauthorized payments', 'cardlink-payment-gateway' ),
                'description' => __( 'Default payment method is Purchase, enable for Pre-Authorized payments. You will then need to accept them from Cardlink eCommerce Tool.', 'cardlink-payment-gateway' ),
                'default'     => 'no',
            ],
            'xml_api'                => [
                'title'       => __( 'XML API Operations', 'cardlink-payment-gateway' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable XML API (Capture, Refund, Void, Status)', 'cardlink-payment-gateway' ),
                'description' => __( 'Enables secondary operations directly from WooCommerce admin: capture pre-authorized payments, refund, void, and check transaction status. The XML API channel must be enabled by Cardlink for your merchant account.', 'cardlink-payment-gateway' ),
                'default'     => 'no',
            ],
            'tokenization'           => [
                'title'       => __( 'Store card details', 'cardlink-payment-gateway' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable Tokenization', 'cardlink-payment-gateway' ),
                'description' => __( 'If checked the user will have the ability to store credit card details for future purchases. You must contact Cardlink first.', 'cardlink-payment-gateway' ),
                'default'     => 'no',
            ],
            'redirect_page_id'       => [
                'title'       => __( 'Return page URL <br />(Successful or Failed Transactions)', 'cardlink-payment-gateway' ),
                'type'        => 'select',
                'options'     => self::get_page_options(),
                'description' => __( 'We recommend you to select the default "Thank You Page", in order to automatically serve both successful and failed transactions, with the latter also offering the option to try the payment again.<br /> If you select a different page, you will have to handle failed payments yourself by adding custom code.', 'cardlink-payment-gateway' ),
                'default'     => '-1',
            ],
            'popup'                  => [
                'title'       => __( 'Pay in website', 'cardlink-payment-gateway' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable payment iframe', 'cardlink-payment-gateway' ),
                'description' => __( 'Customers will stay in website to complete payments without redirecting to Cardlink\'s eCommerce payment page.<br />You must have a valid SSL certificate installed on your domain.', 'cardlink-payment-gateway' ),
                'default'     => 'no',
            ],
            'css_url'                => [
                'title'       => __( 'Css url path', 'cardlink-payment-gateway' ),
                'type'        => 'text',
                'description' => __( 'Url of custom CSS stylesheet, to be used to display payment page styles.', 'cardlink-payment-gateway' ),
                'default'     => '',
            ],
            'enable_log'             => [
                'title'       => __( 'Enable Debug mode', 'cardlink-payment-gateway' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enabling this will log certain information', 'cardlink-payment-gateway' ),
                'default'     => 'no',
                'description' => __( 'Enabling this (and the debug mode from your wp-config file) will log information, e.g. responses, which will help in debugging issues.', 'cardlink-payment-gateway' ),
            ],
            'background_confirm'     => [
                'title'       => __( 'Background confirmation url', 'cardlink-payment-gateway' ),
                'type'        => 'cardlink_static_url',
                'description' => __( 'Send this field value to Cardlink to enable the Background confirmation check for payments.', 'cardlink-payment-gateway' ),
            ],
        ];
    }

    /**
     * Return form_fields for the IRIS gateway.
     */
    public static function iris_fields( AcquirerRegistry $registry ): array {
        return [
            'enabled'                 => [
                'title'       => __( 'Enable/Disable', 'cardlink-payment-gateway' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable IRIS Payment Gateway', 'cardlink-payment-gateway' ),
                'description' => __( 'Enable or disable the gateway.', 'cardlink-payment-gateway' ),
                'desc_tip'    => true,
                'default'     => 'yes',
            ],
            'title'                   => [
                'title'       => __( 'Title', 'cardlink-payment-gateway' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'cardlink-payment-gateway' ),
                'desc_tip'    => true,
                'default'     => __( 'IRIS', 'cardlink-payment-gateway' ),
            ],
            'description'             => [
                'title'       => __( 'Description', 'cardlink-payment-gateway' ),
                'type'        => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.', 'cardlink-payment-gateway' ),
                'desc_tip'    => true,
                'default'     => '',
            ],
            'iris_acquirer'           => [
                'title'       => __( 'Select Acquirer', 'cardlink-payment-gateway' ),
                'type'        => 'select',
                'options'     => array_merge(
                    [ 'inherit' => __( 'Inherit from Cardlink Payment Gateway', 'cardlink-payment-gateway' ) ],
                    $registry->get_names()
                ),
                'default'     => 'inherit',
                'description' => __( 'Select your acquirer bank or inherit settings from the main Cardlink gateway.', 'cardlink-payment-gateway' ),
            ],
            'iris_merchant_id'        => [
                'title'       => __( 'Merchant ID', 'cardlink-payment-gateway' ),
                'type'        => 'text',
                'description' => __( 'Enter the IRIS Merchant ID. Required when not inheriting from Cardlink Payment Gateway.', 'cardlink-payment-gateway' ),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'iris_shared_secret_key'  => [
                'title'       => __( 'Shared Secret Key', 'cardlink-payment-gateway' ),
                'type'        => 'password',
                'description' => __( 'Enter the IRIS Shared Secret Key. Required when not inheriting from Cardlink Payment Gateway.', 'cardlink-payment-gateway' ),
                'default'     => '',
                'desc_tip'    => true,
            ],
        ];
    }

    /**
     * @return array<int, int>
     */
    private static function get_installment_options(): array {
        $options = [];
        for ( $i = 1; $i <= 60; $i++ ) {
            $options[ $i ] = $i;
        }
        return $options;
    }

    /**
     * @return array<int|string, string>
     */
    private static function get_page_options(): array {
        $wp_pages  = get_pages( 'sort_column=menu_order' );
        $page_list = [];
        $page_list[] = __( 'Select Page', 'cardlink-payment-gateway' );

        if ( $wp_pages ) {
            foreach ( $wp_pages as $page ) {
                $prefix     = '';
                $has_parent = $page->post_parent;
                while ( $has_parent ) {
                    $prefix    .= ' - ';
                    $next_page  = get_post( $has_parent );
                    $has_parent = $next_page->post_parent;
                }
                $page_list[ $page->ID ] = $prefix . $page->post_title;
            }
        }

        $page_list[ -1 ] = __( 'Thank you page', 'cardlink-payment-gateway' );

        return $page_list;
    }
}
