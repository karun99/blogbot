<?php
/**
 * BlogWriter settings view.
 *
 * @package BlogWriter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap blogwriter-wrap">
	<h1><?php esc_html_e( 'BlogWriter Settings', 'blogwriter' ); ?></h1>

	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Settings saved.', 'blogwriter' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bw-form">
		<input type="hidden" name="action" value="blogwriter_save_settings" />
		<?php wp_nonce_field( 'blogwriter_action' ); ?>

		<div class="bw-grid">
			<div class="bw-card">
				<h2><?php esc_html_e( 'OpenRouter API', 'blogwriter' ); ?></h2>

				<p>
					<label for="api_key"><?php esc_html_e( 'API Key (BYOK)', 'blogwriter' ); ?></label>
					<input type="password" id="api_key" name="api_key" class="regular-text" placeholder="sk-or-v1-..." autocomplete="off" />
					<span class="bw-hint"><?php esc_html_e( 'Stored encrypted (AES-256). Leave blank to keep current key. Get one at openrouter.ai.', 'blogwriter' ); ?></span>
				</p>

				<p>
					<label for="model"><?php esc_html_e( 'Preferred Model', 'blogwriter' ); ?></label>
					<input type="text" id="model" name="model" class="regular-text" value="<?php echo esc_attr( get_option( 'blogwriter_openrouter_model', 'meta-llama/llama-3.1-8b-instruct:free' ) ); ?>" />
					<span class="bw-hint"><?php esc_html_e( 'BlogWriter auto-falls back across 25+ free models if this fails.', 'blogwriter' ); ?></span>
				</p>
			</div>

			<div class="bw-card">
				<h2><?php esc_html_e( 'OCR Configuration', 'blogwriter' ); ?></h2>

				<p>
					<label for="ocr_endpoint"><?php esc_html_e( 'OCR Endpoint', 'blogwriter' ); ?></label>
					<input type="url" id="ocr_endpoint" name="ocr_endpoint" class="regular-text" value="<?php echo esc_attr( get_option( 'blogwriter_ocr_endpoint', 'https://api.ocr.space/parse/image' ) ); ?>" />
				</p>

				<p>
					<label for="ocr_api_key"><?php esc_html_e( 'OCR API Key', 'blogwriter' ); ?></label>
					<input type="password" id="ocr_api_key" name="ocr_api_key" class="regular-text" value="<?php echo esc_attr( get_option( 'blogwriter_ocr_api_key', '' ) ); ?>" autocomplete="off" />
				</p>
				<p class="bw-hint"><?php esc_html_e( 'Supports OCR.space, Baidu Cloud OCR (aip.baidubce.com) or self-hosted PaddleOCR.', 'blogwriter' ); ?></p>
			</div>

			<div class="bw-card">
				<h2><?php esc_html_e( 'Defaults', 'blogwriter' ); ?></h2>

				<p>
					<label for="default_post_status"><?php esc_html_e( 'Default Post Status', 'blogwriter' ); ?></label>
					<select id="default_post_status" name="default_post_status">
						<option value="draft" <?php selected( get_option( 'blogwriter_default_post_status', 'draft' ), 'draft' ); ?>><?php esc_html_e( 'Draft', 'blogwriter' ); ?></option>
						<option value="publish" <?php selected( get_option( 'blogwriter_default_post_status', 'draft' ), 'publish' ); ?>><?php esc_html_e( 'Published', 'blogwriter' ); ?></option>
					</select>
				</p>

				<p>
					<label for="default_word_count"><?php esc_html_e( 'Default Word Count', 'blogwriter' ); ?></label>
					<input type="number" id="default_word_count" name="default_word_count" min="200" max="5000" value="<?php echo esc_attr( get_option( 'blogwriter_default_word_count', 1000 ) ); ?>" />
				</p>

				<p>
					<label for="max_posts_per_batch"><?php esc_html_e( 'Max Posts Per Batch', 'blogwriter' ); ?></label>
					<input type="number" id="max_posts_per_batch" name="max_posts_per_batch" min="1" max="50" value="<?php echo esc_attr( get_option( 'blogwriter_max_posts_per_batch', 10 ) ); ?>" />
				</p>
			</div>

			<div class="bw-card">
				<h2><?php esc_html_e( 'Security & Rate Limits', 'blogwriter' ); ?></h2>

				<p>
					<label for="rate_limit_requests"><?php esc_html_e( 'Max API Calls', 'blogwriter' ); ?></label>
					<input type="number" id="rate_limit_requests" name="rate_limit_requests" min="1" value="<?php echo esc_attr( get_option( 'blogwriter_rate_limit_requests', 10 ) ); ?>" />
					<span><?php esc_html_e( 'per', 'blogwriter' ); ?></span>
					<input type="number" id="rate_limit_window" name="rate_limit_window" min="10" value="<?php echo esc_attr( get_option( 'blogwriter_rate_limit_window', 60 ) ); ?>" />
					<span><?php esc_html_e( 'seconds', 'blogwriter' ); ?></span>
				</p>

				<p>
					<label>
						<input type="checkbox" name="encryption_enabled" value="1" <?php checked( get_option( 'blogwriter_encryption_enabled', 1 ) ); ?> />
						<?php esc_html_e( 'Enable AES-256 encryption for stored data', 'blogwriter' ); ?>
					</label>
				</p>

				<p>
					<label>
						<input type="checkbox" name="security_headers" value="1" <?php checked( get_option( 'blogwriter_security_headers', 1 ) ); ?> />
						<?php esc_html_e( 'Send hardened security headers (CSP, X-Frame-Options)', 'blogwriter' ); ?>
					</label>
				</p>

				<p class="bw-submit">
					<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Save Changes', 'blogwriter' ); ?></button>
				</p>
			</div>
		</div>
	</form>
</div>
