<?php

/**
 * Define the encryption functionality
 *
 *
 * @link       https://www.cardlink.gr/
 * @since      1.0.0
 *
 * @package    Cardlink_Payment_Gateway
 * @subpackage Cardlink_Payment_Gateway/includes
 */

/**
 * Define the encryption functionality.
 *
 *
 * @since      1.0.0
 * @package    Cardlink_Payment_Gateway
 * @subpackage Cardlink_Payment_Gateway/includes
 * @author     Cardlink <info@cardlink.gr>
 */
class Cardlink_Payment_Gateway_Encryption {

	const METHOD = 'aes-128-cbc';

	/**
	 * Function for encryption of messages.
	 *
	 * @since    1.0.0
	 *
	 * @param    string $message The encryption message
	 * @param    string $key a 256-bit key
	 */
	public static function encrypt( $message, $key ) {
		if ( mb_strlen( $key, '8bit' ) !== 32 ) {
			throw new Exception( "Needs a 256-bit key! " . mb_strlen( $key, '8bit' ) );
		}
		$ivsize = openssl_cipher_iv_length( self::METHOD );
		$iv     = openssl_random_pseudo_bytes( $ivsize );

		$ciphertext = openssl_encrypt(
			$message,
			self::METHOD,
			$key,
			0,
			$iv
		);

		return $iv . $ciphertext;
	}

	/**
	 * Function for decryption of messages.
	 *
	 * @since    1.0.0
	 *
	 * @param    string $message The decryption message
	 * @param    string $key a 256-bit key
	 */
	public static function decrypt( $message, $key ) {
		if ( mb_strlen( $key, '8bit' ) !== 32 ) {
			throw new Exception( "Needs a 256-bit key! " . mb_strlen( $key, '8bit' ) );
		}
		$ivsize     = openssl_cipher_iv_length( self::METHOD );
		$iv         = mb_substr( $message, 0, $ivsize, '8bit' );
		$ciphertext = mb_substr( $message, $ivsize, null, '8bit' );

		return openssl_decrypt(
			$ciphertext,
			self::METHOD,
			$key,
			0,
			$iv
		);
	}
}
