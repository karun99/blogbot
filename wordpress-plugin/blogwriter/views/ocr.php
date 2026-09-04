<?php
/**
 * BlogWriter OCR view.
 *
 * @package BlogWriter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$stored = get_option( 'blogwriter_last_ocr_result' );
$result = $stored ? json_decode( $stored, true ) : null;
?>
<div class="wrap blogwriter-wrap">
	<h1><?php esc_html_e( 'OCR Text Extraction', 'blogwriter' ); ?></h1>

	<?php if ( isset( $_GET['error'] ) ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( urldecode( $_GET['error'] ) ); ?></p></div>
	<?php endif; ?>

	<?php if ( isset( $_GET['extracted'] ) && $result ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html( sprintf( __( 'Extracted %d characters at %s.', 'blogwriter' ), strlen( $result['text'] ), $result['time'] ) ); ?></p></div>
	<?php endif; ?>

	<div class="bw-grid">
		<div class="bw-card">
			<h2><?php esc_html_e( 'Upload Document', 'blogwriter' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="blogwriter_ocr_extract" />
				<?php wp_nonce_field( 'blogwriter_action' ); ?>

				<p>
					<input type="file" name="document" accept=".pdf,.docx,.txt,.jpg,.png" required />
					<span class="bw-hint"><?php esc_html_e( 'Accepted: PDF, DOCX, TXT, JPG, PNG. Max 5 MB.', 'blogwriter' ); ?></span>
				</p>

				<p class="bw-submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Extract Text', 'blogwriter' ); ?></button>
				</p>
			</form>
		</div>

		<div class="bw-card bw-card-wide">
			<h2><?php esc_html_e( 'Extracted Text', 'blogwriter' ); ?></h2>
			<?php if ( $result && ! empty( $result['text'] ) ) : ?>
				<textarea class="large-text" rows="14" readonly><?php echo esc_textarea( $result['text'] ); ?></textarea>
				<p class="bw-hint"><?php esc_html_e( 'Copy this text into the Content Requirements field of a job to use it as source material.', 'blogwriter' ); ?></p>
			<?php else : ?>
				<p class="bw-muted"><?php esc_html_e( 'No extraction yet. Upload a document to begin.', 'blogwriter' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</div>
