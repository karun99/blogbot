<?php
/**
 * BlogWriter Logger.
 *
 * WP_DEBUG logging plus database audit trail.
 *
 * @package BlogWriter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BlogWriter_Logger {

	const LEVELS = array( 'info', 'warning', 'error', 'audit' );

	/**
	 * Write a log entry when WP_DEBUG is enabled.
	 *
	 * @param string $message Log message.
	 * @param string $level   Severity level.
	 */
	public static function log( $message, $level = 'info' ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$level = in_array( $level, self::LEVELS, true ) ? $level : 'info';

		error_log(
			sprintf(
				'[%s] [%s] [BlogWriter] %s',
				current_time( 'Y-m-d H:i:s' ),
				strtoupper( $level ),
				$message
			)
		);
	}

	/**
	 * Record an auditable action in the database and error log.
	 *
	 * @param string $action Action label.
	 * @param string $detail Optional detail.
	 * @return int Audit row id.
	 */
	public static function audit( $action, $detail = '' ) {
		self::log( sprintf( '[AUDIT] %s %s', $action, $detail ? "($detail)" : '' ), 'audit' );

		return BlogWriter_Database::insert_audit( $action, $detail );
	}

	/**
	 * Fetch the audit trail.
	 *
	 * @param int $limit Row limit.
	 * @return array
	 */
	public static function get_audit_trail( $limit = 100 ) {
		return BlogWriter_Database::get_audit_log( $limit );
	}

	/**
	 * Detect suspicious activity from the audit trail.
	 *
	 * @param int $window_minutes Window to scan.
	 * @return array
	 */
	public static function check_suspicious_activity( $window_minutes = 60 ) {
		global $wpdb;

		$table = $wpdb->prefix . BlogWriter_Database::AUDIT_TABLE;

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - absint( $window_minutes ) * MINUTE_IN_SECONDS );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, COUNT(*) AS attempts
				 FROM {$table}
				 WHERE timestamp >= %s
				   AND action LIKE 'failed_%%'
				 GROUP BY user_id
				 HAVING attempts >= 5",
				$cutoff
			)
		);

		return $rows ? $rows : array();
	}
}
