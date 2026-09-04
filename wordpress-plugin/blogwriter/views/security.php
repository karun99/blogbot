<?php
/**
 * BlogWriter security view.
 *
 * @package BlogWriter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap blogwriter-wrap">
	<h1><?php esc_html_e( 'BlogWriter Security', 'blogwriter' ); ?></h1>

	<div class="bw-grid">
		<div class="bw-card bw-card-wide">
			<h2><?php esc_html_e( 'STRiX Compliance Audit', 'blogwriter' ); ?></h2>
			<table class="wp-list-table widefat fixed striped bw-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Check', 'blogwriter' ); ?></th>
						<th><?php esc_html_e( 'Status', 'blogwriter' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $audit_result as $check => $pass ) : ?>
						<tr>
							<td><?php echo esc_html( $check ); ?></td>
							<td>
								<span class="bw-badge <?php echo $pass ? 'bw-ok' : 'bw-fail'; ?>">
									<?php echo $pass ? esc_html__( 'PASS', 'blogwriter' ) : esc_html__( 'FAIL', 'blogwriter' ); ?>
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="bw-card">
			<h2><?php esc_html_e( 'Dependencies', 'blogwriter' ); ?></h2>
			<ul class="bw-detail-list">
				<li><strong>PHP:</strong> <?php echo esc_html( $dependencies['php_version'] ); ?></li>
				<li><strong>OpenSSL:</strong> <?php echo esc_html( $dependencies['openssl_version'] ); ?></li>
				<li><strong>cURL/TLS:</strong> <?php echo $dependencies['curl_supports_tls'] ? esc_html__( 'Available', 'blogwriter' ) : esc_html__( 'Missing', 'blogwriter' ); ?></li>
			</ul>
		</div>
	</div>

	<div class="bw-card">
		<h2><?php esc_html_e( 'WP-CLI Commands', 'blogwriter' ); ?></h2>
		<pre class="bw-code"># Run security audit
wp eval "BlogWriter_Security::run_audit();"

# Check API key
wp eval "var_dump(BlogWriter_API::get_api_key());"

# Run due jobs manually
wp eval "BlogWriter_Cron_Manager::run_scheduled_jobs();"

# Re-encrypt all data
wp eval "BlogWriter_Encryption::reencrypt_all();"</pre>
	</div>
</div>
