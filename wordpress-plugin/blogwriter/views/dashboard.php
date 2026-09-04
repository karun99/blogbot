<?php
/**
 * BlogWriter dashboard view.
 *
 * @package BlogWriter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$total_jobs = count( $jobs );
$running    = 0;
$generated  = 0;

foreach ( $jobs as $job ) {
	if ( 'running' === $job->status ) {
		$running++;
	}
	$generated += (int) $job->generated_posts;
}
?>
<div class="wrap blogwriter-wrap">
	<h1>BlogWriter <span class="bw-version">v<?php echo esc_html( BLOGWRITER_VERSION ); ?></span></h1>

	<div class="bw-grid bw-stats">
		<div class="bw-card">
			<span class="bw-stat-value"><?php echo esc_html( $total_jobs ); ?></span>
			<span class="bw-stat-label"><?php esc_html_e( 'Total Jobs', 'blogwriter' ); ?></span>
		</div>
		<div class="bw-card">
			<span class="bw-stat-value"><?php echo esc_html( $running ); ?></span>
			<span class="bw-stat-label"><?php esc_html_e( 'Running Jobs', 'blogwriter' ); ?></span>
		</div>
		<div class="bw-card">
			<span class="bw-stat-value"><?php echo esc_html( $generated ); ?></span>
			<span class="bw-stat-label"><?php esc_html_e( 'Posts Generated', 'blogwriter' ); ?></span>
		</div>
		<div class="bw-card <?php echo $key_ok ? 'bw-ok' : 'bw-warn'; ?>">
			<span class="bw-stat-value"><?php echo $key_ok ? 'OK' : 'MISSING'; ?></span>
			<span class="bw-stat-label"><?php esc_html_e( 'API Key', 'blogwriter' ); ?></span>
		</div>
	</div>

	<div class="bw-grid">
		<div class="bw-card bw-card-wide">
			<h2><?php esc_html_e( 'Quick Actions', 'blogwriter' ); ?></h2>
			<div class="bw-actions">
				<a class="button button-primary button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=blogwriter-new-job' ) ); ?>">+ <?php esc_html_e( 'Create New Job', 'blogwriter' ); ?></a>
				<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=blogwriter-settings' ) ); ?>"><?php esc_html_e( 'Settings', 'blogwriter' ); ?></a>
				<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=blogwriter-ocr' ) ); ?>"><?php esc_html_e( 'OCR Extract', 'blogwriter' ); ?></a>
			</div>

			<h2><?php esc_html_e( 'Available Free Models', 'blogwriter' ); ?></h2>
			<ul class="bw-model-list">
				<?php foreach ( array_slice( $models, 0, 6 ) as $model ) : ?>
					<li><code><?php echo esc_html( $model ); ?></code></li>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="bw-card">
			<h2><?php esc_html_e( 'Recent Activity', 'blogwriter' ); ?></h2>
			<?php if ( empty( $audit ) ) : ?>
				<p class="bw-muted"><?php esc_html_e( 'No activity yet.', 'blogwriter' ); ?></p>
			<?php else : ?>
				<ul class="bw-audit-list">
					<?php foreach ( $audit as $entry ) : ?>
						<li>
							<code><?php echo esc_html( $entry->action ); ?></code>
							<span class="bw-muted"><?php echo esc_html( $entry->timestamp ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
</div>
