<?php
/**
 * BlogWriter API Handler.
 *
 * OpenRouter BYOK integration with multi-model auto-fallback.
 *
 * @package BlogWriter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BlogWriter_API {

	const OPENROUTER_URL = 'https://openrouter.ai/api/v1/chat/completions';
	const MODELS_URL     = 'https://openrouter.ai/api/v1/models';

	/**
	 * Default free models, tried in order until one succeeds.
	 *
	 * @var array
	 */
	public static $fallback_models = array(
		'meta-llama/llama-3.1-8b-instruct:free',
		'meta-llama/llama-3.2-3b-instruct:free',
		'mistralai/mistral-7b-instruct:free',
		'google/gemma-2-9b-it:free',
		'openai/gpt-4o-mini:free',
		'deepseek/deepseek-chat:free',
		'qwen/qwen-2.5-7b-instruct:free',
		'microsoft/phi-3-mini-128k-instruct:free',
		'anthropic/claude-3-5-haiku:free',
	);

	/**
	 * Retrieve the decrypted OpenRouter key.
	 *
	 * @return string|null
	 */
	public static function get_api_key() {
		$encrypted = get_option( 'blogwriter_api_key_encrypted' );

		if ( ! $encrypted ) {
			return null;
		}

		return BlogWriter_Encryption::decrypt( $encrypted );
	}

	/**
	 * Persist an API key (encrypted).
	 *
	 * @param string $key Plain key.
	 * @return bool
	 */
	public static function set_api_key( $key ) {
		if ( ! BlogWriter_Validator::validate_api_key( $key ) ) {
			BlogWriter_Logger::audit( 'failed_set_api_key' );
			return false;
		}

		$saved = update_option( 'blogwriter_api_key_encrypted', BlogWriter_Encryption::encrypt( $key ) );
		BlogWriter_Logger::audit( 'set_api_key', $saved ? 'encrypted' : 'failed' );

		return $saved;
	}

	/**
	 * Transient-based rate limiter.
	 *
	 * @return bool True when within the allowed window.
	 */
	public static function check_rate_limit() {
		$limit  = (int) get_option( 'blogwriter_rate_limit_requests', 10 );
		$window = (int) get_option( 'blogwriter_rate_limit_window', 60 );

		$calls = get_transient( 'blogwriter_api_calls' );

		if ( false === $calls ) {
			set_transient( 'blogwriter_api_calls', 1, $window );
			return true;
		}

		if ( (int) $calls >= $limit ) {
			BlogWriter_Logger::log( 'API rate limit reached', 'warning' );
			return false;
		}

		set_transient( 'blogwriter_api_calls', (int) $calls + 1, $window );
		return true;
	}

	/**
	 * Generate a blog post from a job and prompt.
	 *
	 * @param array $job Job data.
	 * @return array{content:string, model:string}|WP_Error
	 */
	public static function generate_post( $job ) {
		if ( ! self::check_rate_limit() ) {
			return new WP_Error( 'rate_limited', __( 'API rate limit exceeded. Try again later.', 'blogwriter' ) );
		}

		$api_key = self::get_api_key();

		if ( ! $api_key ) {
			return new WP_Error( 'no_api_key', __( 'OpenRouter API key is not configured.', 'blogwriter' ) );
		}

		$models = self::active_models();
		$errors = array();

		foreach ( $models as $model ) {
			$result = self::request_completion( $api_key, $model, self::build_prompt( $job ) );

			if ( ! is_wp_error( $result ) ) {
				return array(
					'content' => $result,
					'model'   => $model,
				);
			}

			$errors[] = $model . ': ' . $result->get_error_message();
			BlogWriter_Logger::log( sprintf( 'Model %s failed: %s', $model, $result->get_error_message() ), 'warning' );
		}

		return new WP_Error(
			'all_models_failed',
			__( 'All AI models failed. ', 'blogwriter' ) . implode( ' | ', $errors )
		);
	}

	/**
	 * Build the model list to try.
	 *
	 * @return array
	 */
	private static function active_models() {
		$configured = get_option( 'blogwriter_openrouter_model', '' );

		$models = array_filter( array( $configured ) );
		$models = array_merge( $models, self::$fallback_models );

		return array_values( array_unique( $models ) );
	}

	/**
	 * Build the AI prompt from a job.
	 *
	 * @param array $job Job data.
	 * @return string
	 */
	public static function build_prompt( $job ) {
		$parts = array();

		$parts[] = 'You are BlogWriter, an expert SEO blog writer.';
		$parts[] = 'Produce one complete, ready-to-publish WordPress blog post in clean HTML (h2/h3, <p>, <ul>).';

		if ( ! empty( $job['brand_name'] ) ) {
			$parts[] = 'Brand / Company: ' . $job['brand_name'];
		}
		if ( ! empty( $job['tone'] ) ) {
			$parts[] = 'Tone: ' . $job['tone'];
		}
		if ( ! empty( $job['word_count'] ) ) {
			$parts[] = 'Target length: approximately ' . absint( $job['word_count'] ) . ' words.';
		}
		if ( ! empty( $job['seo_keywords'] ) ) {
			$parts[] = 'SEO keywords to include naturally: ' . $job['seo_keywords'];
		}
		if ( ! empty( $job['url'] ) ) {
			$extracted = self::extract_url_content( $job['url'] );
			if ( $extracted ) {
				$parts[] = 'Reference content from this URL (use as inspiration, do not copy):' . "\n" . wp_trim_words( $extracted, 800 );
			}
		}
		if ( ! empty( $job['requirements'] ) ) {
			$parts[] = 'Content requirements: ' . $job['requirements'];
		}

		return implode( "\n\n", $parts );
	}

	/**
	 * Extract readable content from a reference URL.
	 *
	 * @param string $url Target URL.
	 * @return string
	 */
	public static function extract_url_content( $url ) {
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return '';
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 20,
				'user-agent' => 'BlogWriter/1.0 (+https://guidingkey.com)',
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		$html = wp_remote_retrieve_body( $response );

		return self::extract_text_from_html( $html );
	}

	/**
	 * Naive HTML-to-text extraction.
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	private static function extract_text_from_html( $html ) {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) );
		libxml_clear_errors();

		foreach ( array( 'script', 'style', 'noscript', 'nav', 'footer' ) as $tag ) {
			foreach ( $dom->getElementsByTagName( $tag ) as $node ) {
				$node->parentNode->removeChild( $node );
			}
		}

		$text = $dom->textContent;
		$text = preg_replace( '/\s+/u', ' ', $text );

		return trim( (string) $text );
	}

	/**
	 * Perform the OpenRouter chat completion request.
	 *
	 * @param string $api_key API key.
	 * @param string $model   Model id.
	 * @param string $prompt  Prompt text.
	 * @return string|WP_Error
	 */
	private static function request_completion( $api_key, $model, $prompt ) {
		$response = wp_remote_post(
			self::OPENROUTER_URL,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'HTTP-Referer'  => home_url(),
					'X-Title'       => 'BlogWriter',
				),
				'timeout' => 300,
				'body'    => wp_json_encode(
					array(
						'model'    => $model,
						'messages' => array(
							array( 'role' => 'user', 'content' => $prompt ),
						),
						'temperature' => 0.7,
						'max_tokens'  => 4096,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || empty( $body['choices'][0]['message']['content'] ) ) {
			$reason = isset( $body['error']['message'] ) ? $body['error']['message'] : 'HTTP ' . $code;
			return new WP_Error( 'api_error', $reason );
		}

		return $body['choices'][0]['message']['content'];
	}

	/**
	 * Validate the configured API key against OpenRouter.
	 *
	 * @return bool
	 */
	public static function validate_remote() {
		$api_key = self::get_api_key();

		if ( ! $api_key ) {
			return false;
		}

		$response = wp_remote_get(
			self::MODELS_URL,
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $api_key ),
				'timeout' => 20,
			)
		);

		return ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response );
	}
}
