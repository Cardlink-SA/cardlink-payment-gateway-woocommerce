<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\XmlApi;

defined( 'ABSPATH' ) || exit;

use Cardlink_Checkout\CardlinkXmlApi;
use Cardlink_Checkout\CardlinkXmlResponse;
use Flavor\CardlinkPaymentGateway\Support\Logger;

class XmlApiService {

    private Logger $logger;

    public function __construct( Logger $logger ) {
        $this->logger = $logger;
    }

    /**
     * Create a CardlinkXmlApi client using the given gateway settings.
     */
    private function create_client( string $merchant_id, string $shared_secret, int $acquirer_index, string $environment ): CardlinkXmlApi {
        $client = new CardlinkXmlApi(
            $merchant_id,
            $shared_secret,
            $this->map_business_partner( $acquirer_index ),
            $this->map_environment( $environment )
        );

        if ( $this->logger->is_enabled() ) {
            $client->setDebug( true, function ( string $message ) {
                $this->logger->log( $message );
            } );
        }

        return $client;
    }

    /**
     * Capture a pre-authorized transaction.
     */
    public function capture( string $order_id, float $amount, string $merchant_id, string $shared_secret, int $acquirer_index, string $environment, string $currency = 'EUR' ): CardlinkXmlResponse {
        $client = $this->create_client( $merchant_id, $shared_secret, $acquirer_index, $environment );
        return $client->capture( $order_id, $amount, $currency );
    }

    /**
     * Refund a captured transaction.
     */
    public function refund( string $order_id, float $amount, string $merchant_id, string $shared_secret, int $acquirer_index, string $environment, string $currency = 'EUR' ): CardlinkXmlResponse {
        $client = $this->create_client( $merchant_id, $shared_secret, $acquirer_index, $environment );
        return $client->refund( $order_id, $amount, $currency );
    }

    /**
     * Cancel/Void a pre-authorized transaction.
     */
    public function cancel( string $order_id, float $amount, string $merchant_id, string $shared_secret, int $acquirer_index, string $environment, string $currency = 'EUR' ): CardlinkXmlResponse {
        $client = $this->create_client( $merchant_id, $shared_secret, $acquirer_index, $environment );
        return $client->cancel( $order_id, $amount, $currency );
    }

    /**
     * Get the status of a transaction.
     */
    public function status( string $order_id, string $merchant_id, string $shared_secret, int $acquirer_index, string $environment ): CardlinkXmlResponse {
        $client = $this->create_client( $merchant_id, $shared_secret, $acquirer_index, $environment );
        return $client->status( $order_id );
    }

    /**
     * Map acquirer index to CardlinkXmlApi partner constant.
     */
    private function map_business_partner( int $acquirer_index ): string {
        switch ( $acquirer_index ) {
            case 1:
                return CardlinkXmlApi::PARTNER_NEXI;
            case 2:
                return CardlinkXmlApi::PARTNER_WORLDLINE;
            default:
                return CardlinkXmlApi::PARTNER_CARDLINK;
        }
    }

    /**
     * Map environment setting to CardlinkXmlApi constant.
     * In plugin settings: 'yes' = test mode, 'no' = production.
     */
    private function map_environment( string $env_setting ): string {
        return $env_setting === 'yes'
            ? CardlinkXmlApi::ENV_SANDBOX
            : CardlinkXmlApi::ENV_PRODUCTION;
    }
}
