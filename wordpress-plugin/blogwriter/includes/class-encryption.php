<?php
/**
 * BlogWriter Encryption.
 *
 * AES-256-CBC encryption via OpenSSL for all stored sensitive data.
 *
 * @package BlogWriter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BlogWriter_Encryption {

	const CIPHER = 'AES-256-CBC';

	/**
	 * @var string
	 */
	private static $key;

	/**
	 * Initialise the encryption key.
	 */
	public static function init() {
		if ( defined( 'BLOGWRITER_ENCRYPTION_KEY' ) && BLOGWRITER_ENCRYPTION_KEY ) {
			self::$key = BLOGWRITER_ENCRYPTION_KEY;
			return;
		}

		self::$key = get_option( 'blogwriter_encryption_key' );

		if ( ! self::$key ) {
			self::$key = wp_generate_password( 32, true, true );
			update_option( 'blogwriter_encryption_key', self::$key );
		}
	}

	/**
	 * Encrypt a string.
	 *
	 * @param string $data Plain text.
	 * @return string Base64(iv . ciphertext) or empty on failure.
	 */
	public static function encrypt( $data ) {
		if ( ! get_option( 'blogwriter_encryption_enabled', 1 ) || '' === (string) $data ) {
			return $data;
		}

		self::init();

		if ( ! extension_loaded( 'openssl' ) ) {
			BlogWriter_Logger::log( 'OpenSSL extension missing, encryption skipped', 'error' );
			return $data;
		}

		$iv_length = openssl_cipher_iv_length( self::CIPHER );
		$iv        = openssl_random_pseudo_bytes( $iv_length );

		$encrypted = openssl_encrypt( (string) $data, self::CIPHER, self::$key, 0, $iv );

		if ( false === $encrypted ) {
			BlogWriter_Logger::log( 'Encryption failed: ' . openssl_error_string(), 'error' );
			return '';
		}

		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt a value produced by encrypt().
	 *
	 * @param string $data Encoded ciphertext.
	 * @return string Plain text.
	 */
	public static function decrypt( $data ) {
		if ( '' === (string) $data ) {
			return '';
		}

		self::init();

		if ( ! extension_loaded( 'openssl' ) ) {
			return $data;
		}

		$raw       = base64_decode( (string) $data, true );
		$iv_length = openssl_cipher_iv_length( self::CIPHER );

		if ( false === $raw || strlen( $raw ) <= $iv_length ) {
			BlogWriter_Logger::log( 'Decryption failed: malformed ciphertext', 'error' );
			return '';
		}

		$iv        = substr( $raw, 0, $iv_length );
		$encrypted = substr( $raw, $iv_length );

		$decrypted = openssl_decrypt( $encrypted, self::CIPHER, self::$key, 0, $iv );

		if ( false === $decrypted ) {
			BlogWriter_Logger::log( 'Decryption failed: ' . openssl_error_string(), 'error' );
			return '';
		}

		return $decrypted;
	}

	/**
	 * Detect whether a value is currently encrypted.
	 *
	 * @param string $value Stored value.
	 * @return bool
	 */
	public static function is_encrypted( $value ) {
		if ( '' === (string) $value ) {
			return false;
		}

		$raw = base64_decode( (string) $value, true );

		return false !== $raw && strlen( $raw ) > openssl_cipher_iv_length( self::CIPHER );
	}

	/**
	 * Re-encrypt all stored API keys (used after key rotation).
	 */
	public static function reencrypt_all() {
		$encrypted = get_option( 'blogwriter_api_key_encrypted' );
		$plain     = self::decrypt( $encrypted );

		if ( $plain ) {
			update_option( 'blogwriter_api_key_encrypted', self::encrypt( $plain ) );
		}

		BlogWriter_Logger::audit( 'reencrypt_all' );
	}
}
