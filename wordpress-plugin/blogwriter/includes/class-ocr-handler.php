<?php
/**
 * BlogWriter OCR Handler.
 *
 * Document text extraction via OCR.space, Baidu Cloud OCR or a
 * self-hosted OCR endpoint. Supports PDF, DOCX, TXT, JPG, PNG.
 *
 * @package BlogWriter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BlogWriter_OCR {

	const ALLOWED_TYPES = array(
		'pdf'  => 'application/pdf',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'txt'  => 'text/plain',
		'jpg'  => 'image/jpeg',
		'png'  => 'image/png',
	);

	const MAX_SIZE = 5 * 1024 * 1024; // 5 MB.

	/**
	 * Validate an uploaded file for type, size and malicious content.
	 *
	 * @param array $file $_FILES entry.
	 * @return bool
	 */
	public static function validate_file( $file ) {
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return false;
		}

		if ( (int) $file['size'] > self::MAX_SIZE ) {
			BlogWriter_Logger::log( 'OCR upload rejected: file too large', 'warning' );
			return false;
		}

		if ( ! extension_loaded( 'fileinfo' ) ) {
			return false;
		}

		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$mime  = finfo_file( $finfo, $file['tmp_name'] );
		finfo_close( $finfo );

		$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		if ( ! isset( self::ALLOWED_TYPES[ $extension ] ) || self::ALLOWED_TYPES[ $extension ] !== $mime ) {
			BlogWriter_Logger::log( 'OCR upload rejected: MIME mismatch', 'warning' );
			return false;
		}

		$content = file_get_contents( $file['tmp_name'] );

		if ( is_string( $content ) && preg_match( '/<\?php|eval\s*\(|base64_decode\s*\(/i', $content ) ) {
			BlogWriter_Logger::log( 'OCR upload rejected: suspicious content', 'warning' );
			return false;
		}

		return true;
	}

	/**
	 * Extract text from an uploaded file.
	 *
	 * @param array $file $_FILES entry.
	 * @return string|WP_Error
	 */
	public static function extract_text( $file ) {
		if ( ! self::validate_file( $file ) ) {
			return new WP_Error( 'invalid_file', __( 'File validation failed.', 'blogwriter' ) );
		}

		$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		if ( 'txt' === $extension ) {
			return file_get_contents( $file['tmp_name'] );
		}

		if ( 'docx' === $extension ) {
			return self::extract_docx( $file['tmp_name'] );
		}

		// PDF, JPG, PNG -> remote OCR.
		$endpoint = get_option( 'blogwriter_ocr_endpoint', 'https://api.ocr.space/parse/image' );
		$api_key  = get_option( 'blogwriter_ocr_api_key', '' );

		if ( 0 === strpos( $endpoint, 'http://localhost' ) || 0 === strpos( $endpoint, 'http://127.0.0.1' ) ) {
			return self::extract_self_hosted( $endpoint, $file['tmp_name'], $extension );
		}

		if ( false !== strpos( $endpoint, 'aip.baidubce.com' ) ) {
			return self::extract_baidu( $endpoint, $file['tmp_name'], $api_key );
		}

		return self::extract_ocr_space( $endpoint, $file['tmp_name'], $api_key, $extension );
	}

	/**
	 * Extract text from a DOCX (ZIP of XML).
	 *
	 * @param string $path File path.
	 * @return string|WP_Error
	 */
	private static function extract_docx( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'no_zip', __( 'ZipArchive extension required for DOCX.', 'blogwriter' ) );
		}

		$zip = new ZipArchive();

		if ( true !== $zip->open( $path ) ) {
			return new WP_Error( 'unzip_failed', __( 'Could not open DOCX.', 'blogwriter' ) );
		}

		$xml = $zip->getFromName( 'word/document.xml' );
		$zip->close();

		if ( false === $xml ) {
			return new WP_Error( 'docx_missing', __( 'Malformed DOCX.', 'blogwriter' ) );
		}

		$xml = preg_replace( '/<\/w:p>/i', "\n", $xml );
		$xml = preg_replace( '/<\/w:tab>/i', "\t", $xml );
		$xml = preg_replace( '/<[^>]+>/', '', $xml );

		return trim( html_entity_decode( $xml, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * OCR.space API call.
	 *
	 * @param string $endpoint  Endpoint URL.
	 * @param string $file_path Uploaded file path.
	 * @param string $api_key   API key.
	 * @param string $extension File extension.
	 * @return string|WP_Error
	 */
	private static function extract_ocr_space( $endpoint, $file_path, $api_key, $extension ) {
		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 60,
				'body'    => array(
					'apikey'            => $api_key,
					'language'          => 'eng',
					'isOverlayRequired' => 'false',
					'file'              => new CURLFile( $file_path, self::ALLOWED_TYPES[ $extension ], 'document.' . $extension ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['ParsedResults'] ) ) {
			return new WP_Error( 'ocr_failed', isset( $body['ErrorMessage'] ) ? $body['ErrorMessage'] : __( 'OCR returned no text.', 'blogwriter' ) );
		}

		$text = '';
		foreach ( $body['ParsedResults'] as $result ) {
			$text .= $result['ParsedText'] . "\n";
		}

		return trim( $text );
	}

	/**
	 * Self-hosted OCR endpoint (e.g. PaddleOCR).
	 *
	 * @param string $endpoint  Endpoint URL.
	 * @param string $file_path File path.
	 * @param string $extension Extension.
	 * @return string|WP_Error
	 */
	private static function extract_self_hosted( $endpoint, $file_path, $extension ) {
		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 60,
				'body'    => array(
					'file' => new CURLFile( $file_path, self::ALLOWED_TYPES[ $extension ], 'document.' . $extension ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( is_array( $data ) && isset( $data['text'] ) ) {
			return $data['text'];
		}

		return $body;
	}

	/**
	 * Baidu Cloud OCR call.
	 *
	 * @param string $endpoint Endpoint URL.
	 * @param string $file_path File path.
	 * @param string $api_key  Client API key.
	 * @return string|WP_Error
	 */
	private static function extract_baidu( $endpoint, $file_path, $api_key ) {
		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 60,
				'body'    => array(
					'apikey'    => $api_key,
					'image'     => base64_encode( file_get_contents( $file_path ) ),
					'detect_direction' => 'true',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['words_result'] ) ) {
			return new WP_Error( 'baidu_failed', isset( $body['error_msg'] ) ? $body['error_msg'] : __( 'Baidu OCR returned no text.', 'blogwriter' ) );
		}

		$text = '';
		foreach ( $body['words_result'] as $row ) {
			$text .= $row['words'] . "\n";
		}

		return trim( $text );
	}
}
