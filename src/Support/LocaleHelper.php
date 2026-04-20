<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Support;

defined( 'ABSPATH' ) || exit;

class LocaleHelper {

    public function get_cardlink_language(): string {
        $locale = get_locale();
        return ( strpos( $locale, 'el' ) === 0 ) ? 'el' : 'en';
    }
}
