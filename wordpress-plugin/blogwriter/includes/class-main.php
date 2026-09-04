<?php
/**
 * BlogWriter Main.
 *
 * Core orchestrator: admin wiring, REST API, job execution.
 *
 * @package BlogWriter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BlogWriter_Main {

	/**
	 * @var BlogWriter_Main|null
	 */
	private static $instance;

	/**
	 * @var BlogWriter_Admin|null
	 */
	private $admin;

	/**
	 * Singleton accessor.
	 *
	 * @return BlogWriter_Main
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire everything up.
	 */
	private function __construct() {
		BlogWriter_Encryption::init();
		BlogWriter_Cron_Manager::init();
		BlogWriter_Security::init();

		add_action( 'init', array( $this, 'register_rest_routes' ) );

		if ( is_admin() ) {
			require_once BLOGWRITER_PLUGIN_DIR . 'includes/class-admin.php';
			$this->admin = new BlogWriter_Admin();
		}
	}

	/**
	 * Register REST API endpoints.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'blogwriter/v1',
			'/job',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_create_job' ),
				'permission_callback' => array( $this, 'rest_permission' ),
			)
		);

		register_rest_route(
			'blogwriter/v1',
			'/job/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'rest_get_job' ),
					'permission_callback' => array( $this, 'rest_permission' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'rest_delete_job' ),
					'permission_callback' => array( $this, 'rest_permission' ),
				),
			)
		);

		register_rest_route(
			'blogwriter/v1',
			'/job/(?P<id>\d+)/(?P<action>pause|resume|run-now)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_job_action' ),
				'permission_callback' => array( $this, 'rest_permission' ),
			)
		);
	}

	/**
	 * REST permission check.
	 *
	 * @return bool|WP_Error
	 */
	public function rest_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'forbidden', __( 'Insufficient permissions.', 'blogwriter' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * POST /blogwriter/v1/job
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_create_job( $request ) {
		$params = $request->get_json_params();

		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$sanitized = BlogWriter_Validator::sanitize_job_input( $params );

		if ( empty( $sanitized['job_name'] ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Job name is required.', 'blogwriter' ) ), 400 );
		}

		$job_id = BlogWriter_Database::create_job( $sanitized );

		if ( ! $job_id ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Failed to create job.', 'blogwriter' ) ), 500 );
		}

		BlogWriter_Logger::audit( 'rest_create_job', 'job ' . $job_id );

		return new WP_REST_Response(
			array(
				'success'  => true,
				'job_id'   => $job_id,
				'message'  => __( 'Job created successfully.', 'blogwriter' ),
				'next_run' => BlogWriter_Cron_Manager::next_run_time( $sanitized['interval_value'], $sanitized['interval_unit'] ),
			),
			201
		);
	}

	/**
	 * GET /blogwriter/v1/job/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_get_job( $request ) {
		$job = BlogWriter_Database::get_job( (int) $request['id'] );

		if ( ! $job ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Job not found.', 'blogwriter' ) ), 404 );
		}

		return new WP_REST_Response(
			array(
				'id'              => (int) $job->id,
				'job_name'        => $job->job_name,
				'status'          => $job->status,
				'total_posts'     => (int) $job->total_posts,
				'generated_posts' => (int) $job->generated_posts,
				'created_at'      => $job->created_at,
				'next_run'        => $job->next_run,
			)
		);
	}

	/**
	 * DELETE /blogwriter/v1/job/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_delete_job( $request ) {
		$job_id = (int) $request['id'];

		BlogWriter_Cron_Manager::unschedule_job( $job_id );
		$deleted = BlogWriter_Database::delete_job( $job_id );

		if ( ! $deleted ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Job not found.', 'blogwriter' ) ), 404 );
		}

		BlogWriter_Logger::audit( 'rest_delete_job', 'job ' . $job_id );

		return new WP_REST_Response( array( 'success' => true, 'message' => __( 'Job deleted.', 'blogwriter' ) ) );
	}

	/**
	 * POST /blogwriter/v1/job/{id}/{pause|resume|run-now}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_job_action( $request ) {
		$job_id = (int) $request['id'];
		$action = sanitize_key( $request['action'] );
		$job    = BlogWriter_Database::get_job( $job_id );

		if ( ! $job ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Job not found.', 'blogwriter' ) ), 404 );
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

			case 'run-now':
				$result = self::run_job( $job );
				if ( is_wp_error( $result ) ) {
					return new WP_REST_Response( array( 'success' => false, 'message' => $result->get_error_message() ), 500 );
				}
				return new WP_REST_Response(
					array(
						'success' => true,
						'message' => sprintf( __( 'Generated %d post(s).', 'blogwriter' ), count( $result ) ),
					)
				);

			default:
				return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Unknown action.', 'blogwriter' ) ), 400 );
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'status'  => 'paused' === $action ? 'paused' : 'running',
			)
		);
	}

	/**
	 * Execute a job: generate posts and insert as WordPress posts.
	 *
	 * @param object $job Job row.
	 * @return array|WP_Error
	 */
	public static function run_job( $job ) {
		$job_array = (array) $job;
		$count     = (int) $job->num_posts;
		$status    = 'publish' === $job->post_status ? 'publish' : 'draft';

		$created = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$result = BlogWriter_API::generate_post( $job_array );

			if ( is_wp_error( $result ) ) {
				BlogWriter_Logger::log( sprintf( 'Job %d post %d failed: %s', $job->id, $i + 1, $result->get_error_message() ), 'error' );
				break;
			}

			$post_id = wp_insert_post(
				array(
					'post_title'   => self::make_title( $result['content'], $job->job_name ),
					'post_content' => $result['content'],
					'post_status'  => $status,
					'post_type'    => 'post',
				)
			);

			if ( ! $post_id || is_wp_error( $post_id ) ) {
				continue;
			}

			if ( $job->seo_keywords ) {
				wp_set_post_terms( $post_id, array_map( 'trim', explode( ',', $job->seo_keywords ) ), 'post_tag', true );
			}

			add_post_meta( $post_id, '_blogwriter_job_id', (int) $job->id );
			add_post_meta( $post_id, '_blogwriter_model', $result['model'] );

			$created[] = $post_id;
		}

		$generated = (int) $job->generated_posts + count( $created );

		BlogWriter_Database::update_job(
			(int) $job->id,
			array(
				'generated_posts' => $generated,
				'last_run'        => current_time( 'mysql' ),
				'next_run'        => BlogWriter_Cron_Manager::next_run_time( (int) $job->interval_value, $job->interval_unit ),
			)
		);

		BlogWriter_Logger::audit( 'job_executed', sprintf( 'job %d created %d posts', (int) $job->id, count( $created ) ) );

		return $created;
	}

	/**
	 * Derive a post title from generated content.
	 *
	 * @param string $content  Generated HTML.
	 * @param string $fallback Fallback name.
	 * @return string
	 */
	private static function make_title( $content, $fallback ) {
		if ( preg_match( '/<h1[^>]*>(.*?)<\/h1>/is', $content, $m ) ) {
			$title = trim( wp_strip_all_tags( $m[1] ) );
			if ( $title ) {
				return $title;
			}
		}

		return trim( $fallback ) . ' #' . wp_rand( 1000, 9999 );
	}
}
