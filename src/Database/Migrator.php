<?php

declare( strict_types=1 );

namespace Flavor\CardlinkPaymentGateway\Database;

defined( 'ABSPATH' ) || exit;

class Migrator {

    public const TABLE_NAME = 'cardlink_gateway_transactions';

    public static function create_table(): void {
        global $wpdb;

        $table   = self::get_table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            merchantreference VARCHAR(30) NOT NULL,
            reference VARCHAR(100) NOT NULL DEFAULT '',
            trans_ticket VARCHAR(100) NOT NULL DEFAULT '',
            timestamp DATETIME DEFAULT NULL,
            PRIMARY KEY (id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function get_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }
}
