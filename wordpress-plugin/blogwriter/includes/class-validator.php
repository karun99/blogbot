<?php
/**
 * BlogWriter Validator.
 *
 * STRiX-compliant input validation and sanitisation.
 *
 * @package BlogWriter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BlogWriter_Validator {

	const INTERVAL_UNITS = array( 'minutes', 'hours', 'days', 'weeks' );
	const TONES          = array( 'professional', 'casual', 'technical', 'conversational', 'persuasive' );
	const POST_STATUSES  = array( 'draft', 'publish' );

	/**
	 * Sanitise a single text field.
	 *
	 * @param mixed  $value Raw value.
	 * @param string $type  Field type.
	 * @return mixed
	 */
	public static function sanitize_field( $value, $type ) {
		switch ( $type ) {
			case 'text':
				return sanitize_text_field( wp_unslash( $value ) );
			case 'url':
				return esc_url_raw( wp_unslash( $value ) );
			case 'textarea':
				return sanitize_textarea_field( wp_unslash( $value ) );
			case 'rich':
				return wp_kses_post( wp_unslash( $value ) );
			case 'int':
				return absint( $value );
			default:
				return sanitize_text_field( wp_unslash( $value ) );
		}
	}

	/**
	 * Sanitise incoming job input.
	 *
	 * @param array $input Raw $_POST payload.
	 * @return array
	 */
	public static function sanitize_job_input( $input ) {
		$interval_unit = isset( $input['interval_unit'] ) ? sanitize_text_field( $input['interval_unit'] ) : 'hours';
		$post_status   = isset( $input['post_status'] ) ? sanitize_text_field( $input['post_status'] ) : 'draft';
		$tone          = isset( $input['tone'] ) ? sanitize_text_field( $input['tone'] ) : 'professional';

		return array(
			'job_name'       => self::sanitize_field( isset( $input['job_name'] ) ? $input['job_name'] : '', 'text' ),
			'brand_name'     => self::sanitize_field( isset( $input['brand_name'] ) ? $input['brand_name'] : '', 'text' ),
			'url'            => self::sanitize_field( isset( $input['url'] ) ? $input['url'] : '', 'url' ),
			'requirements'   => self::sanitize_field( isset( $input['requirements'] ) ? $input['requirements'] : '', 'textarea' ),
			'num_posts'      => self::sanitize_field( isset( $input['num_posts'] ) ? $input['num_posts'] : 1, 'int' ),
			'interval_value' => self::sanitize_field( isset( $input['interval'] ) ? $input['interval'] : 24, 'int' ),
			'interval_unit'  => in_array( $interval_unit, self::INTERVAL_UNITS, true ) ? $interval_unit : 'hours',
			'post_status'    => in_array( $post_status, self::POST_STATUSES, true ) ? $post_status : 'draft',
			'word_count'     => self::sanitize_field( isset( $input['word_count'] ) ? $input['word_count'] : 1000, 'int' ),
			'tone'           => in_array( $tone, self::TONES, true ) ? $tone : 'professional',
			'seo_keywords'   => self::sanitize_field( isset( $input['seo_keywords'] ) ? $input['seo_keywords'] : '', 'text' ),
		);
	}

	/**
	 * Validate an OpenRouter API key format.
	 *
	 * @param string $key Candidate key.
	 * @return bool
	 */
	public static function validate_api_key( $key ) {
		if ( ! is_string( $key ) || '' === $key ) {
			return false;
		}

		if ( 1 === preg_match( '/^sk-or-v1-[a-zA-Z0-9_-]{50,}$/', $key ) ) {
			return true;
		}

		// Fallback: any reasonably long opaque key (future / other providers).
		return strlen( $key ) >= BLOGWRITER_MIN_OPENROUTER_KEY;
	}

	/**
	 * Validate nonce.
	 *
	 * @param string $action Nonce action.
	 * @param string $name   Nonce field name.
	 * @param string $method Request method.
	 * @return bool
	 */
	public static function verify_nonce( $action, $name = '_wpnonce', $method = 'POST' ) {
		if ( 'POST' === strtoupper( $method ) ) {
			check_admin_referer( $action, $name );
			return true;
		}

		$nonce = isset( $_REQUEST[ $name ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $name ] ) ) : '';

		return (bool) wp_verify_nonce( $nonce, $action );
	}

	/**
	 * Validate a capability gate for the current user.
	 *
	 * @param string $capability Capability required.
	 * @return bool
	 */
	public static function require_capability( $capability = 'manage_options' ) {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'Unauthorized access', 'blogwriter' ), 403 );
		}
		return true;
	}
}
