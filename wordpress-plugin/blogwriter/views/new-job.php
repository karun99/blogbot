<?php
/**
 * BlogWriter new-job view.
 *
 * @package BlogWriter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap blogwriter-wrap">
	<h1><?php esc_html_e( 'Create New Blog Job', 'blogwriter' ); ?></h1>

	<?php if ( isset( $_GET['error'] ) ) : ?>
		<div class="notice notice-error"><p><?php esc_html_e( 'Could not create the job. Job name is required.', 'blogwriter' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bw-form">
		<input type="hidden" name="action" value="blogwriter_create_job" />
		<?php wp_nonce_field( 'blogwriter_action' ); ?>

		<div class="bw-grid">
			<div class="bw-card">
				<h2><?php esc_html_e( 'Job Details', 'blogwriter' ); ?></h2>

				<p>
					<label for="job_name"><?php esc_html_e( 'Job Name *', 'blogwriter' ); ?></label>
					<input type="text" id="job_name" name="job_name" class="regular-text" required />
				</p>

				<p>
					<label for="brand_name"><?php esc_html_e( 'Brand Name', 'blogwriter' ); ?></label>
					<input type="text" id="brand_name" name="brand_name" class="regular-text" placeholder="MAS Technology" />
				</p>

				<p>
					<label for="url"><?php esc_html_e( 'Reference URL', 'blogwriter' ); ?></label>
					<input type="url" id="url" name="url" class="regular-text" placeholder="https://example.com/tech-trends" />
					<span class="bw-hint"><?php esc_html_e( 'BlogWriter extracts reference content from this URL.', 'blogwriter' ); ?></span>
				</p>

				<p>
					<label for="requirements"><?php esc_html_e( 'Content Requirements', 'blogwriter' ); ?></label>
					<textarea id="requirements" name="requirements" class="large-text" rows="4" placeholder="Write about AI in 2026, include statistics..."></textarea>
				</p>
			</div>

			<div class="bw-card">
				<h2><?php esc_html_e( 'Generation Settings', 'blogwriter' ); ?></h2>

				<p>
					<label for="num_posts"><?php esc_html_e( 'Number of Posts', 'blogwriter' ); ?></label>
					<input type="number" id="num_posts" name="num_posts" min="1" max="<?php echo esc_attr( get_option( 'blogwriter_max_posts_per_batch', 10 ) ); ?>" value="1" />
				</p>

				<p>
					<label for="word_count"><?php esc_html_e( 'Word Count (approx)', 'blogwriter' ); ?></label>
					<input type="number" id="word_count" name="word_count" min="200" max="5000" value="<?php echo esc_attr( get_option( 'blogwriter_default_word_count', 1000 ) ); ?>" />
				</p>

				<p>
					<label for="tone"><?php esc_html_e( 'Tone', 'blogwriter' ); ?></label>
					<select id="tone" name="tone">
						<option value="professional"><?php esc_html_e( 'Professional', 'blogwriter' ); ?></option>
						<option value="casual"><?php esc_html_e( 'Casual', 'blogwriter' ); ?></option>
						<option value="technical"><?php esc_html_e( 'Technical', 'blogwriter' ); ?></option>
						<option value="conversational"><?php esc_html_e( 'Conversational', 'blogwriter' ); ?></option>
						<option value="persuasive"><?php esc_html_e( 'Persuasive', 'blogwriter' ); ?></option>
					</select>
				</p>

				<p>
					<label for="seo_keywords"><?php esc_html_e( 'SEO Keywords', 'blogwriter' ); ?></label>
					<input type="text" id="seo_keywords" name="seo_keywords" class="regular-text" placeholder="AI, Enterprise Tech, Cloud" />
				</p>
			</div>

			<div class="bw-card">
				<h2><?php esc_html_e( 'Scheduling', 'blogwriter' ); ?></h2>

				<p>
					<label for="interval"><?php esc_html_e( 'Interval', 'blogwriter' ); ?></label>
					<input type="number" id="interval" name="interval" min="1" value="24" />
					<select id="interval_unit" name="interval_unit">
						<option value="minutes"><?php esc_html_e( 'Minutes', 'blogwriter' ); ?></option>
						<option value="hours" selected><?php esc_html_e( 'Hours', 'blogwriter' ); ?></option>
						<option value="days"><?php esc_html_e( 'Days', 'blogwriter' ); ?></option>
						<option value="weeks"><?php esc_html_e( 'Weeks', 'blogwriter' ); ?></option>
					</select>
				</p>

				<p>
					<label for="post_status"><?php esc_html_e( 'Post Status', 'blogwriter' ); ?></label>
					<select id="post_status" name="post_status">
						<option value="draft" selected><?php esc_html_e( 'Draft', 'blogwriter' ); ?></option>
						<option value="publish"><?php esc_html_e( 'Published', 'blogwriter' ); ?></option>
					</select>
				</p>

				<p>
					<label>
						<input type="checkbox" name="auto_start" value="1" checked />
						<?php esc_html_e( 'Start automatically after creation', 'blogwriter' ); ?>
					</label>
				</p>

				<p class="bw-submit">
					<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Start Job', 'blogwriter' ); ?></button>
				</p>
			</div>
		</div>
	</form>
</div>
