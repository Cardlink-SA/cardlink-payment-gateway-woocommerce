<?php

/**
 * Fired during plugin activation
 *
 * @link       https://www.cardlink.gr/
 * @since      1.0.0
 *
 * @package    Cardlink_Payment_Gateway
 * @subpackage Cardlink_Payment_Gateway/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Cardlink_Payment_Gateway
 * @subpackage Cardlink_Payment_Gateway/includes
 * @author     Cardlink <info@cardlink.gr>
 */
class Cardlink_Payment_Gateway_Activator {

	/**
	 * Table name
	 *
	 * @since    1.0.0
	 */
	static $table_name = 'cardlink_gateway_transactions';

	/**
	 * Create a table for storing transaction data
	 *
	 * @since    1.0.0
	 */
	public static function activate() {

		global $wpdb;

		if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $wpdb->prefix . self::$table_name . "'" ) === $wpdb->prefix . self::$table_name ) {
			// The database table exist
		} else {
			// Table does not exist
			$query = 'CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . self::$table_name . ' (id int(11) unsigned NOT NULL AUTO_INCREMENT,merchantreference varchar(30) not null, reference varchar(100) not null, trans_ticket varchar(100) not null , timestamp datetime default null, PRIMARY KEY (id))';
			$wpdb->query( $query );
		}

	}

}
