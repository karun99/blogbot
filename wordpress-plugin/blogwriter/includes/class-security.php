<?php
/**
 * BlogWriter Security.
 *
 * Security headers, dependency checks and STRiX audit.
 *
 * @package BlogWriter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BlogWriter_Security {

	/**
	 * Register security headers.
	 */
	public static function init() {
		if ( get_option( 'blogwriter_security_headers', 1 ) ) {
			add_filter( 'wp_headers', array( __CLASS__, 'headers' ) );
		}
	}

	/**
	 * Emit hardened HTTP headers.
	 *
	 * @param array $headers Existing headers.
	 * @return array
	 */
	public static function headers( $headers ) {
		$headers['X-Frame-Options']           = 'DENY';
		$headers['X-Content-Type-Options']    = 'nosniff';
		$headers['X-XSS-Protection']          = '1; mode=block';
		$headers['Referrer-Policy']           = 'strict-origin-when-cross-origin';
		$headers['Content-Security-Policy']   =
			"default-src 'self'; " .
			"script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com; " .
			"style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
			"font-src 'self' https://fonts.gstatic.com; " .
			"img-src 'self' data:;";

		return $headers;
	}

	/**
	 * Run the STRiX compliance / security audit.
	 *
	 * @return array List of check => pass/fail.
	 */
	public static function run_audit() {
		$checks = array();

		$checks['SSL in use']              = (bool) is_ssl() || ( defined( 'FORCE_SSL_ADMIN' ) && FORCE_SSL_ADMIN );
		$checks['OpenSSL extension']       = extension_loaded( 'openssl' );
		$checks['curl extension']          = function_exists( 'curl_init' );
		$checks['fileinfo extension']      = function_exists( 'finfo_open' );
		$checks['Encryption key set']      = (bool) get_option( 'blogwriter_encryption_key' );
		$checks['API key encrypted']       = BlogWriter_Encryption::is_encrypted( get_option( 'blogwriter_api_key_encrypted' ) );
		$checks['Security headers']        = (bool) get_option( 'blogwriter_security_headers', 1 );
		$checks['DB tables present']       = self::tables_present();
		$checks['Plugin directory writable'] = (bool) wp_is_writable( BLOGWRITER_PLUGIN_DIR );

		return $checks;
	}

	/**
	 * Confirm both plugin tables exist.
	 *
	 * @return bool
	 */
	private static function tables_present() {
		global $wpdb;

		$job   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . BlogWriter_Database::JOB_TABLE ) );
		$audit = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . BlogWriter_Database::AUDIT_TABLE ) );

		return (bool) $job && (bool) $audit;
	}

	/**
	 * Verify dependency versions / vulnerabilities.
	 *
	 * @return array
	 */
	public static function check_dependencies() {
		return array(
			'openssl_version' => OPENSSL_VERSION_TEXT,
			'curl_supports_tls' => (bool) function_exists( 'curl_version' ) && ( curl_version()['features'] & CURL_VERSION_SSL ),
			'php_version'     => PHP_VERSION,
		);
	}

	/**
	 * PHP-level devtools / tamper guards (best-effort).
	 */
	public static function anti_tamper() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_guards' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_guards' ) );
	}

	/**
	 * Enqueue minimal anti-tamper JavaScript guard.
	 */
	public static function enqueue_guards() {
		wp_enqueue_script(
			'blogwriter-guards',
			BLOGWRITER_PLUGIN_URL . 'assets/js/guards.js',
			array(),
			BLOGWRITER_VERSION,
			true
		);
	}
}
