<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Payment;

defined( 'ABSPATH' ) || exit;

use Flavor\CardlinkPaymentGateway\Support\LocaleHelper;
use WC_Order;

class FormBuilder {

    private LocaleHelper $locale_helper;

    public function __construct( LocaleHelper $locale_helper ) {
        $this->locale_helper = $locale_helper;
    }

    /**
     * Build the form data array for a payment transaction.
     */
    public function build_payment_fields(
        WC_Order $order,
        string $merchant_id,
        string $confirm_url,
        string $cancel_url,
        int $tr_type,
        string $css_url = '',
        int $installments = 0,
        ?string $ext_token = null,
        ?int $ext_token_options = null,
        ?string $pay_method = null
    ): array {
        $order_id    = $order->get_id();
        $timestamp   = gmdate( 'Ymdhisu' );
        $order_key   = $order_id . 'at' . $timestamp;
        $lang        = $this->locale_helper->get_cardlink_language();
        $bill_country = $order->get_billing_country();

        $fields = [
            'version'      => '2',
            'mid'          => $merchant_id,
            'lang'         => $lang,
            'orderid'      => $order_key,
            'orderDesc'    => 'Order #' . $order_id,
            'orderAmount'  => $order->get_total(),
            'currency'     => 'EUR',
            'payerEmail'   => $order->get_billing_email(),
            'payerPhone'   => $order->get_billing_phone(),
            'billCountry'  => $bill_country,
        ];

        // State field only for non-Greek billing countries.
        if ( strtoupper( $bill_country ) !== 'GR' ) {
            $state = $order->get_billing_state();
            $country_states = WC()->countries->get_states( $bill_country );
            if ( is_array( $country_states ) && isset( $country_states[ $state ] ) ) {
                $state = $country_states[ $state ];
            }
            $fields['billState'] = $state;
        }

        $fields['billZip']     = $order->get_billing_postcode();
        $fields['billCity']    = $order->get_billing_city();
        $fields['billAddress'] = $order->get_billing_address_1();

        if ( $pay_method === 'IRIS' ) {
            // IRIS: no shipping, no trType, no cssUrl. payMethod goes directly after billAddress.
            $fields['payMethod']  = 'IRIS';
            $fields['confirmUrl'] = $confirm_url;
            $fields['cancelUrl']  = $cancel_url;
        } else {
            // Non-IRIS: shipping fields, trType, optional installments, optional payMethod, cssUrl.
            $fields['shipCountry'] = $order->get_shipping_country() ?: $bill_country;
            $fields['shipZip']     = $order->get_shipping_postcode() ?: $order->get_billing_postcode();
            $fields['shipCity']    = $order->get_shipping_city() ?: $order->get_billing_city();
            $fields['shipAddress'] = $order->get_shipping_address_1() ?: $order->get_billing_address_1();

            $fields['trType'] = (string) $tr_type;

            if ( $installments > 1 ) {
                $fields['extInstallmentoffset'] = '0';
                $fields['extInstallmentperiod'] = (string) $installments;
            }

            if ( $pay_method !== null ) {
                $fields['payMethod'] = $pay_method;
            }

            $fields['cssUrl']      = $css_url;
            $fields['confirmUrl']  = $confirm_url;
            $fields['cancelUrl']   = $cancel_url;

            // Tokenization.
            if ( $ext_token_options !== null ) {
                $fields['extTokenOptions'] = (string) $ext_token_options;
                if ( $ext_token !== null && $ext_token_options === 110 ) {
                    $fields['extToken'] = $ext_token;
                }
            }
        }

        return $fields;
    }

    /**
     * Build the form data array for a tokenizer-only transaction (no amount).
     */
    public function build_tokenizer_fields(
        int $user_id,
        string $billing_email,
        string $billing_country,
        string $billing_postcode,
        string $billing_city,
        string $billing_address,
        string $merchant_id,
        string $confirm_url,
        string $cancel_url,
        string $css_url = ''
    ): array {
        $lang      = $this->locale_helper->get_cardlink_language();
        $timestamp = gmdate( 'Ymdhisu' );
        $order_key = $user_id . 'at' . $timestamp;

        return [
            'version'     => '2',
            'mid'         => $merchant_id,
            'lang'        => $lang,
            'orderid'     => $order_key,
            'orderDesc'   => '',
            'orderAmount' => '0',
            'currency'    => 'EUR',
            'payerEmail'  => $billing_email,
            'billCountry' => $billing_country,
            'billZip'     => $billing_postcode,
            'billCity'    => $billing_city,
            'billAddress' => $billing_address,
            'trType'      => '8',
            'cssUrl'      => $css_url,
            'confirmUrl'  => $confirm_url,
            'cancelUrl'   => $cancel_url,
        ];
    }
}
