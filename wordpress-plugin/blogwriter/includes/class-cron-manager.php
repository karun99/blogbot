<?php
/**
 * BlogWriter Cron Manager.
 *
 * WordPress cron scheduling with interval registration and job dispatch.
 *
 * @package BlogWriter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BlogWriter_Cron_Manager {

	const HOOK = 'blogwriter_generate_posts';

	/**
	 * Boot cron functionality.
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'register_interval' ) );
		add_action( self::HOOK, array( __CLASS__, 'run_scheduled_jobs' ) );
	}

	/**
	 * Register a reusable base interval used by one-off scheduling.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function register_interval( $schedules ) {
		$schedules['blogwriter_interval'] = array(
			'interval' => HOUR_IN_SECONDS,
			'display'  => __( 'BlogWriter base interval (1 hour)', 'blogwriter' ),
		);

		return $schedules;
	}

	/**
	 * Schedule a job for regular execution.
	 *
	 * @param int   $job_id         Job id.
	 * @param int   $interval_value Recurring interval magnitude.
	 * @param string $interval_unit Unit (minutes/hours/days/weeks).
	 * @return bool
	 */
	public static function schedule_job( $job_id, $interval_value, $interval_unit ) {
		self::unschedule_job( $job_id );

		$seconds = self::interval_to_seconds( $interval_value, $interval_unit );

		if ( $seconds < MINUTE_IN_SECONDS ) {
			return false;
		}

		$event = wp_schedule_event(
			time() + $seconds,
			'blogwriter_interval',
			self::HOOK,
			array( 'job_id' => (int) $job_id )
		);

		BlogWriter_Logger::audit( 'schedule_job', 'job ' . (int) $job_id . ' every ' . $seconds . 's' );

		return false !== $event;
	}

	/**
	 * Remove a job's scheduled event.
	 *
	 * @param int $job_id Job id.
	 */
	public static function unschedule_job( $job_id ) {
		wp_clear_scheduled_hook( self::HOOK, array( 'job_id' => (int) $job_id ) );
	}

	/**
	 * Convert an interval to seconds.
	 *
	 * @param int    $value Magnitude.
	 * @param string $unit  Unit.
	 * @return int
	 */
	public static function interval_to_seconds( $value, $unit ) {
		$value = max( 1, absint( $value ) );

		switch ( $unit ) {
			case 'minutes':
				return $value * MINUTE_IN_SECONDS;
			case 'days':
				return $value * DAY_IN_SECONDS;
			case 'weeks':
				return $value * WEEK_IN_SECONDS;
			case 'hours':
			default:
				return $value * HOUR_IN_SECONDS;
		}
	}

	/**
	 * Compute next run time for a job.
	 *
	 * @param int    $interval_value Interval magnitude.
	 * @param string $interval_unit  Unit.
	 * @return string MySQL datetime.
	 */
	public static function next_run_time( $interval_value, $interval_unit ) {
		return gmdate( 'Y-m-d H:i:s', time() + self::interval_to_seconds( $interval_value, $interval_unit ) );
	}

	/**
	 * Dispatch due jobs (invoked by cron or WP-CLI).
	 */
	public static function run_scheduled_jobs() {
		$jobs = BlogWriter_Database::get_due_jobs();

		foreach ( $jobs as $job ) {
			$result = BlogWriter_Main::run_job( $job );

			if ( is_wp_error( $result ) ) {
				BlogWriter_Logger::log( sprintf( 'Job %d failed: %s', $job->id, $result->get_error_message() ), 'error' );
			}
		}
	}

	/**
	 * Cleanup on plugin deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::HOOK );
	}
}
