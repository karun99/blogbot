<?php
/**
 * BlogWriter Database.
 *
 * Handles schema creation, options and audit-log persistence.
 *
 * @package BlogWriter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BlogWriter_Database {

	const AUDIT_TABLE = 'blogwriter_audit';
	const JOB_TABLE   = 'blogwriter_jobs';

	/**
	 * Install tables and default options on activation.
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$job_table = $wpdb->prefix . self::JOB_TABLE;
		$jobs_sql  = "CREATE TABLE {$job_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			job_name VARCHAR(255) NOT NULL,
			brand_name VARCHAR(255) DEFAULT '',
			url TEXT DEFAULT NULL,
			requirements LONGTEXT DEFAULT NULL,
			num_posts INT UNSIGNED NOT NULL DEFAULT 1,
			interval_value INT UNSIGNED NOT NULL DEFAULT 24,
			interval_unit VARCHAR(10) NOT NULL DEFAULT 'hours',
			post_status VARCHAR(10) NOT NULL DEFAULT 'draft',
			word_count INT UNSIGNED NOT NULL DEFAULT 1000,
			tone VARCHAR(50) DEFAULT 'professional',
			seo_keywords TEXT DEFAULT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'paused',
			total_posts INT UNSIGNED NOT NULL DEFAULT 0,
			generated_posts INT UNSIGNED NOT NULL DEFAULT 0,
			last_run DATETIME DEFAULT NULL,
			next_run DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id)
		) {$charset_collate};";

		$audit_table = $wpdb->prefix . self::AUDIT_TABLE;
		$audit_sql   = "CREATE TABLE {$audit_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			action VARCHAR(255) NOT NULL,
			detail TEXT DEFAULT NULL,
			ip_address VARCHAR(45) DEFAULT '',
			timestamp DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY action (action),
			KEY timestamp (timestamp)
		) {$charset_collate};";

		dbDelta( $jobs_sql );
		dbDelta( $audit_sql );

		$defaults = array(
			'blogwriter_api_key_encrypted' => '',
			'blogwriter_openrouter_model'   => 'meta-llama/llama-3.1-8b-instruct:free',
			'blogwriter_ocr_endpoint'       => 'https://api.ocr.space/parse/image',
			'blogwriter_ocr_api_key'        => '',
			'blogwriter_default_post_status' => 'draft',
			'blogwriter_default_word_count'  => 1000,
			'blogwriter_max_posts_per_batch' => 10,
			'blogwriter_encryption_enabled'  => 1,
			'blogwriter_rate_limit_requests' => 10,
			'blogwriter_rate_limit_window'   => 60,
			'blogwriter_security_headers'    => 1,
			'blogwriter_version'             => BLOGWRITER_VERSION,
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}

		if ( ! get_option( 'blogwriter_encryption_key' ) ) {
			add_option( 'blogwriter_encryption_key', wp_generate_password( 32, true, true ) );
		}

		BlogWriter_Encryption::init();
	}

	/**
	 * Drop tables and options on uninstall.
	 */
	public static function uninstall() {
		global $wpdb;

		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::JOB_TABLE );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::AUDIT_TABLE );

		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'options WHERE option_name LIKE "blogwriter_%"' );

		wp_clear_scheduled_hook( 'blogwriter_generate_posts' );
	}

	/**
	 * Create a job row.
	 *
	 * @param array $data Sanitized job data.
	 * @return int Inserted job id or 0.
	 */
	public static function create_job( $data ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$defaults = array(
			'job_name'        => '',
			'brand_name'      => '',
			'url'             => '',
			'requirements'    => '',
			'num_posts'       => 1,
			'interval_value'  => 24,
			'interval_unit'   => 'hours',
			'post_status'     => 'draft',
			'word_count'      => 1000,
			'tone'            => 'professional',
			'seo_keywords'    => '',
			'status'          => 'paused',
			'total_posts'     => 0,
			'generated_posts' => 0,
			'last_run'        => null,
			'next_run'        => null,
		);

		$data       = wp_parse_args( $data, $defaults );
		$data['id'] = null;

		$inserted = $wpdb->insert(
			$wpdb->prefix . self::JOB_TABLE,
			array_merge( $data, array( 'created_at' => $now, 'updated_at' => $now ) ),
			array(
				'%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s',
				'%s', '%d', '%d', '%s', '%s', '%s',
			)
		);

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Update a job row.
	 *
	 * @param int   $job_id Job id.
	 * @param array $data   Field => value map.
	 * @return bool
	 */
	public static function update_job( $job_id, $data ) {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );

		return (bool) $wpdb->update(
			$wpdb->prefix . self::JOB_TABLE,
			$data,
			array( 'id' => (int) $job_id )
		);
	}

	/**
	 * Fetch a single job.
	 *
	 * @param int $job_id Job id.
	 * @return object|null
	 */
	public static function get_job( $job_id ) {
		global $wpdb;

		$table = $wpdb->prefix . self::JOB_TABLE;

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $job_id )
		);
	}

	/**
	 * Fetch all jobs, newest first.
	 *
	 * @return array
	 */
	public static function get_jobs() {
		global $wpdb;

		$table = $wpdb->prefix . self::JOB_TABLE;

		return $wpdb->get_results(
			"SELECT * FROM {$table} ORDER BY id DESC"
		);
	}

	/**
	 * Fetch jobs due to run.
	 *
	 * @return array
	 */
	public static function get_due_jobs() {
		global $wpdb;

		$table = $wpdb->prefix . self::JOB_TABLE;
		$now   = current_time( 'mysql' );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE status = 'running'
				   AND (next_run IS NULL OR next_run <= %s)",
				$now
			)
		);
	}

	/**
	 * Delete a job.
	 *
	 * @param int $job_id Job id.
	 * @return bool
	 */
	public static function delete_job( $job_id ) {
		global $wpdb;

		$deleted = $wpdb->delete(
			$wpdb->prefix . self::JOB_TABLE,
			array( 'id' => (int) $job_id ),
			array( '%d' )
		);

		return (bool) $deleted;
	}

	/**
	 * Insert an audit entry.
	 *
	 * @param string $action Action label.
	 * @param string $detail Optional detail.
	 * @return int
	 */
	public static function insert_audit( $action, $detail = '' ) {
		global $wpdb;

		$user_id = get_current_user_id();
		$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		$inserted = $wpdb->insert(
			$wpdb->prefix . self::AUDIT_TABLE,
			array(
				'user_id'    => $user_id,
				'action'     => $action,
				'detail'     => $detail,
				'ip_address' => $ip,
				'timestamp'  => current_time( 'mysql' ),
			)
		);

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Fetch recent audit entries.
	 *
	 * @param int $limit Number of rows.
	 * @return array
	 */
	public static function get_audit_log( $limit = 100 ) {
		global $wpdb;

		$table = $wpdb->prefix . self::AUDIT_TABLE;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY timestamp DESC LIMIT %d",
				absint( $limit )
			)
		);
	}
}
