<?php
/**
 * BlogWriter jobs list view.
 *
 * @package BlogWriter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap blogwriter-wrap">
	<h1>
		<?php esc_html_e( 'Blog Jobs', 'blogwriter' ); ?>
		<a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=blogwriter-new-job' ) ); ?>"><?php esc_html_e( 'New Job', 'blogwriter' ); ?></a>
	</h1>

	<?php if ( isset( $_GET['created'] ) ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html( sprintf( __( 'Job #%d created.', 'blogwriter' ), absint( $_GET['created'] ) ) ); ?></p></div>
	<?php elseif ( isset( $_GET['action'] ) ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html( sprintf( __( 'Job %s.', 'blogwriter' ), sanitize_key( $_GET['action'] ) ) ); ?></p></div>
	<?php endif; ?>

	<?php if ( empty( $jobs ) ) : ?>
		<div class="bw-card">
			<p class="bw-muted"><?php esc_html_e( 'No jobs yet. Create your first automated blog job.', 'blogwriter' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped bw-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'blogwriter' ); ?></th>
					<th><?php esc_html_e( 'Job Name', 'blogwriter' ); ?></th>
					<th><?php esc_html_e( 'Brand', 'blogwriter' ); ?></th>
					<th><?php esc_html_e( 'Status', 'blogwriter' ); ?></th>
					<th><?php esc_html_e( 'Posts', 'blogwriter' ); ?></th>
					<th><?php esc_html_e( 'Interval', 'blogwriter' ); ?></th>
					<th><?php esc_html_e( 'Next Run', 'blogwriter' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'blogwriter' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $jobs as $job ) : ?>
					<tr>
						<td><?php echo esc_html( $job->id ); ?></td>
						<td><strong><?php echo esc_html( $job->job_name ); ?></strong></td>
						<td><?php echo esc_html( $job->brand_name ); ?></td>
						<td><span class="bw-badge bw-<?php echo esc_attr( $job->status ); ?>"><?php echo esc_html( $job->status ); ?></span></td>
						<td><?php echo esc_html( $job->generated_posts . ' / ' . $job->num_posts ); ?></td>
						<td><?php echo esc_html( $job->interval_value . ' ' . $job->interval_unit ); ?></td>
						<td><?php echo esc_html( $job->next_run ? $job->next_run : '-' ); ?></td>
						<td class="bw-row-actions">
							<?php if ( 'running' === $job->status ) : ?>
								<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=blogwriter-jobs&job_id=' . $job->id . '&action=pause' ), 'blogwriter_job_action', 'blogwriter_nonce' ) ); ?>"><?php esc_html_e( 'Pause', 'blogwriter' ); ?></a>
							<?php else : ?>
								<a class="button button-small button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=blogwriter-jobs&job_id=' . $job->id . '&action=resume' ), 'blogwriter_job_action', 'blogwriter_nonce' ) ); ?>"><?php esc_html_e( 'Resume', 'blogwriter' ); ?></a>
							<?php endif; ?>
							<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=blogwriter-jobs&job_id=' . $job->id . '&action=run-now' ), 'blogwriter_job_action', 'blogwriter_nonce' ) ); ?>"><?php esc_html_e( 'Run Now', 'blogwriter' ); ?></a>
							<a class="button button-small button-link-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=blogwriter-jobs&job_id=' . $job->id . '&action=delete' ), 'blogwriter_job_action', 'blogwriter_nonce' ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this job?', 'blogwriter' ); ?>');"><?php esc_html_e( 'Delete', 'blogwriter' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
