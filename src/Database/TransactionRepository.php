<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Database;

defined( 'ABSPATH' ) || exit;

class TransactionRepository {

    public function insert( int $order_id ): void {
        global $wpdb;

        $wpdb->insert(
            Migrator::get_table_name(),
            [
                'trans_ticket'      => (string) $order_id,
                'merchantreference' => (string) $order_id,
                'timestamp'         => current_time( 'mysql', 1 ),
            ],
            [ '%s', '%s', '%s' ]
        );
    }

    public function find_by_order_id( int $order_id ): ?object {
        global $wpdb;

        $table  = Migrator::get_table_name();
        $result = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE merchantreference = %s LIMIT 1", (string) $order_id )
        );

        return $result ?: null;
    }
}
