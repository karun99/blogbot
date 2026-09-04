<?php
/**
 * BlogWriter Admin.
 *
 * WordPress admin menu, form handling and view rendering.
 *
 * @package BlogWriter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BlogWriter_Admin {

	const NONCE = 'blogwriter_action';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_blogwriter_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_blogwriter_create_job', array( $this, 'create_job' ) );
		add_action( 'admin_post_blogwriter_job_action', array( $this, 'job_action' ) );
		add_action( 'admin_post_blogwriter_ocr_extract', array( $this, 'ocr_extract' ) );
	}

	/**
	 * Register admin pages.
	 */
	public function menu() {
		add_menu_page(
			__( 'BlogWriter', 'blogwriter' ),
			__( 'BlogWriter', 'blogwriter' ),
			'manage_options',
			'blogwriter',
			array( $this, 'render_dashboard' ),
			'dashicons-welcome-write-blog',
			58
		);

		add_submenu_page( 'blogwriter', __( 'New Job', 'blogwriter' ), __( 'New Job', 'blogwriter' ), 'manage_options', 'blogwriter-new-job', array( $this, 'render_new_job' ) );
		add_submenu_page( 'blogwriter', __( 'Jobs', 'blogwriter' ), __( 'Jobs', 'blogwriter' ), 'manage_options', 'blogwriter-jobs', array( $this, 'render_jobs' ) );
		add_submenu_page( 'blogwriter', __( 'OCR', 'blogwriter' ), __( 'OCR', 'blogwriter' ), 'manage_options', 'blogwriter-ocr', array( $this, 'render_ocr' ) );
		add_submenu_page( 'blogwriter', __( 'Settings', 'blogwriter' ), __( 'Settings', 'blogwriter' ), 'manage_options', 'blogwriter-settings', array( $this, 'render_settings' ) );
		add_submenu_page( 'blogwriter', __( 'Security', 'blogwriter' ), __( 'Security', 'blogwriter' ), 'manage_options', 'blogwriter-security', array( $this, 'render_security' ) );
	}

	/**
	 * Enqueue admin CSS/JS.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_assets( $hook ) {
		if ( 0 !== strpos( $hook, 'blogwriter' ) && 'toplevel_page_blogwriter' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'blogwriter-admin', BLOGWRITER_PLUGIN_URL . 'assets/css/admin-style.css', array(), BLOGWRITER_VERSION );
		wp_enqueue_script( 'blogwriter-admin', BLOGWRITER_PLUGIN_URL . 'assets/js/admin-script.js', array( 'jquery' ), BLOGWRITER_VERSION, true );

		wp_localize_script(
			'blogwriter-admin',
			'blogwriter',
			array(
				'ajaxUrl' => admin_url( 'admin-post.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
			)
		);
	}

	/**
	 * Render helpers.
	 */
	public function render_dashboard() {
		BlogWriter_Validator::require_capability();
		$jobs     = BlogWriter_Database::get_jobs();
		$audit    = BlogWriter_Logger::get_audit_trail( 10 );
		$key_ok   = (bool) BlogWriter_API::get_api_key();
		$models   = BlogWriter_API::$fallback_models;
		include BLOGWRITER_PLUGIN_DIR . 'views/dashboard.php';
	}

	public function render_new_job() {
		BlogWriter_Validator::require_capability();
		include BLOGWRITER_PLUGIN_DIR . 'views/new-job.php';
	}

	public function render_jobs() {
		BlogWriter_Validator::require_capability();
		$jobs = BlogWriter_Database::get_jobs();
		include BLOGWRITER_PLUGIN_DIR . 'views/jobs-list.php';
	}

	public function render_ocr() {
		BlogWriter_Validator::require_capability();
		$extracted = '';
		include BLOGWRITER_PLUGIN_DIR . 'views/ocr.php';
	}

	public function render_settings() {
		BlogWriter_Validator::require_capability();
		include BLOGWRITER_PLUGIN_DIR . 'views/settings.php';
	}

	public function render_security() {
		BlogWriter_Validator::require_capability();
		$audit_result = BlogWriter_Security::run_audit();
		$dependencies = BlogWriter_Security::check_dependencies();
		include BLOGWRITER_PLUGIN_DIR . 'views/security.php';
	}

	/**
	 * Persist settings.
	 */
	public function save_settings() {
		BlogWriter_Validator::require_capability();
		check_admin_referer( self::NONCE );

		$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		$model   = isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '';

		if ( $api_key && BlogWriter_Validator::validate_api_key( $api_key ) ) {
			BlogWriter_API::set_api_key( $api_key );
		}

		update_option( 'blogwriter_openrouter_model', $model ? $model : 'meta-llama/llama-3.1-8b-instruct:free' );
		update_option( 'blogwriter_ocr_endpoint', esc_url_raw( wp_unslash( $_POST['ocr_endpoint'] ) ) );
		update_option( 'blogwriter_ocr_api_key', sanitize_text_field( wp_unslash( $_POST['ocr_api_key'] ) ) );
		update_option( 'blogwriter_default_post_status', sanitize_key( wp_unslash( $_POST['default_post_status'] ) ) );
		update_option( 'blogwriter_default_word_count', absint( $_POST['default_word_count'] ) );
		update_option( 'blogwriter_max_posts_per_batch', absint( $_POST['max_posts_per_batch'] ) );
		update_option( 'blogwriter_rate_limit_requests', absint( $_POST['rate_limit_requests'] ) );
		update_option( 'blogwriter_rate_limit_window', absint( $_POST['rate_limit_window'] ) );
		update_option( 'blogwriter_encryption_enabled', isset( $_POST['encryption_enabled'] ) ? 1 : 0 );
		update_option( 'blogwriter_security_headers', isset( $_POST['security_headers'] ) ? 1 : 0 );

		BlogWriter_Logger::audit( 'save_settings' );

		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=blogwriter-settings' ) ) );
		exit;
	}

	/**
	 * Create a job from the admin form.
	 */
	public function create_job() {
		BlogWriter_Validator::require_capability();
		check_admin_referer( self::NONCE );

		$sanitized = BlogWriter_Validator::sanitize_job_input( $_POST );

		if ( empty( $sanitized['job_name'] ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'name', admin_url( 'admin.php?page=blogwriter-new-job' ) ) );
			exit;
		}

		$job_id = BlogWriter_Database::create_job( $sanitized );

		if ( ! $job_id ) {
			wp_safe_redirect( add_query_arg( 'error', 'db', admin_url( 'admin.php?page=blogwriter-new-job' ) ) );
			exit;
		}

		$auto_start = isset( $_POST['auto_start'] ) ? (bool) absint( $_POST['auto_start'] ) : false;

		if ( $auto_start ) {
			BlogWriter_Database::update_job( $job_id, array( 'status' => 'running' ) );
			BlogWriter_Cron_Manager::schedule_job( $job_id, (int) $sanitized['interval_value'], $sanitized['interval_unit'] );
		}

		BlogWriter_Logger::audit( 'create_job', 'job ' . $job_id );

		wp_safe_redirect( add_query_arg( 'created', $job_id, admin_url( 'admin.php?page=blogwriter-jobs' ) ) );
		exit;
	}

	/**
	 * Pause / resume / delete / run-now a job.
	 */
	public function job_action() {
		BlogWriter_Validator::require_capability();
		check_admin_referer( 'blogwriter_job_action', 'blogwriter_nonce' );

		$job_id = isset( $_GET['job_id'] ) ? absint( $_GET['job_id'] ) : 0;
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
		$job    = $job_id ? BlogWriter_Database::get_job( $job_id ) : null;

		if ( ! $job ) {
			wp_die( esc_html__( 'Job not found.', 'blogwriter' ) );
		}

		switch ( $action ) {
			case 'pause':
				BlogWriter_Cron_Manager::unschedule_job( $job_id );
				BlogWriter_Database::update_job( $job_id, array( 'status' => 'paused' ) );
				BlogWriter_Logger::audit( 'pause_job', 'job ' . $job_id );
				break;

			case 'resume':
				BlogWriter_Database::update_job( $job_id, array( 'status' => 'running' ) );
				BlogWriter_Cron_Manager::schedule_job( $job_id, (int) $job->interval_value, $job->interval_unit );
				BlogWriter_Logger::audit( 'resume_job', 'job ' . $job_id );
				break;

			case 'delete':
				BlogWriter_Cron_Manager::unschedule_job( $job_id );
				BlogWriter_Database::delete_job( $job_id );
				BlogWriter_Logger::audit( 'delete_job', 'job ' . $job_id );
				break;

			case 'run-now':
				$result = BlogWriter_Main::run_job( $job );
				BlogWriter_Logger::audit( 'run_now', 'job ' . $job_id . ' => ' . ( is_wp_error( $result ) ? $result->get_error_message() : count( $result ) . ' posts' ) );
				break;
		}

		wp_safe_redirect( add_query_arg( 'action', $action, admin_url( 'admin.php?page=blogwriter-jobs' ) ) );
		exit;
	}

	/**
	 * Handle OCR upload.
	 */
	public function ocr_extract() {
		BlogWriter_Validator::require_capability();
		check_admin_referer( self::NONCE );

		$result = BlogWriter_OCR::extract_text( $_FILES['document'] );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'error', rawurlencode( $result->get_error_message() ), admin_url( 'admin.php?page=blogwriter-ocr' ) ) );
			exit;
		}

		BlogWriter_Logger::audit( 'ocr_extract', strlen( $result ) . ' chars' );

		update_option( 'blogwriter_last_ocr_result', wp_json_encode( array( 'text' => $result, 'time' => current_time( 'mysql' ) ) ) );

		wp_safe_redirect( add_query_arg( 'extracted', '1', admin_url( 'admin.php?page=blogwriter-ocr' ) ) );
		exit;
	}
}
