<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Token;

defined( 'ABSPATH' ) || exit;

use WC_Payment_Token_CC;
use WC_Payment_Tokens;

class TokenManager {

    /**
     * Save a new token from a Cardlink response. Returns token ID or null if duplicate.
     */
    public function save_token_from_response(
        int $user_id,
        string $gateway_id,
        string $ext_token,
        string $pan_end,
        string $exp_year,
        string $exp_month,
        string $card_type
    ): ?int {
        if ( $this->token_exists( $user_id, $gateway_id, $card_type, $pan_end, $exp_year, $exp_month ) ) {
            return null;
        }

        $token = new WC_Payment_Token_CC();
        $token->set_token( $ext_token );
        $token->set_gateway_id( $gateway_id );
        $token->set_last4( $pan_end );
        $token->set_expiry_year( $exp_year );
        $token->set_expiry_month( $exp_month );
        $token->set_card_type( $card_type );
        $token->set_user_id( $user_id );
        $token->save();

        // Set as default if it's the first card.
        $all_tokens = $this->get_customer_tokens( $user_id, $gateway_id );
        if ( count( $all_tokens ) === 1 ) {
            WC_Payment_Tokens::set_users_default( $user_id, $token->get_id() );
        }

        return $token->get_id();
    }

    public function token_exists(
        int $user_id,
        string $gateway_id,
        string $card_type,
        string $last4,
        string $exp_year,
        string $exp_month
    ): bool {
        $tokens = $this->get_customer_tokens( $user_id, $gateway_id );

        foreach ( $tokens as $existing ) {
            if (
                $existing->get_card_type() === $card_type &&
                $existing->get_last4() === $last4 &&
                $existing->get_expiry_year() === $exp_year &&
                $existing->get_expiry_month() === $exp_month
            ) {
                return true;
            }
        }

        return false;
    }

    public function delete_token( int $token_id ): bool {
        $token = WC_Payment_Tokens::get( $token_id );
        if ( ! $token ) {
            return false;
        }
        WC_Payment_Tokens::delete( $token_id );
        return true;
    }

    /**
     * @return WC_Payment_Token_CC[]
     */
    public function get_customer_tokens( int $user_id, string $gateway_id ): array {
        return WC_Payment_Tokens::get_customer_tokens( $user_id, $gateway_id );
    }
}
