<?php
/**
 * REST API.
 *
 * @package Snippet_Studio
 */

namespace Snippet_Studio;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Authenticated routes used by the editor.
 */
class Rest_Controller {

	const NAMESPACE = 'snippet-studio/v1';

	/**
	 * Repository.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Repository.
	 */
	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Register routes.
	 */
	public function register() {
		register_rest_route(
			self::NAMESPACE,
			'/bootstrap',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'bootstrap' ),
				'permission_callback' => array( Capabilities::class, 'rest_read' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/snippets',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_snippets' ),
					'permission_callback' => array( Capabilities::class, 'rest_read' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_snippet' ),
					'permission_callback' => array( Capabilities::class, 'rest_write' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/snippets/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_snippet' ),
					'permission_callback' => array( Capabilities::class, 'rest_read' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_snippet' ),
					'permission_callback' => array( Capabilities::class, 'rest_write' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'trash_snippet' ),
					'permission_callback' => array( Capabilities::class, 'rest_write' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/snippets/(?P<id>\d+)/duplicate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'duplicate_snippet' ),
				'permission_callback' => array( Capabilities::class, 'rest_write' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/snippets/(?P<id>\d+)/status',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'set_status' ),
				'permission_callback' => array( Capabilities::class, 'rest_write' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/snippets/(?P<id>\d+)/check',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'check_snippet' ),
				'permission_callback' => array( Capabilities::class, 'rest_read' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/snippets/(?P<id>\d+)/revisions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_revisions' ),
				'permission_callback' => array( Capabilities::class, 'rest_read' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'search' ),
				'permission_callback' => array( Capabilities::class, 'rest_read' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'export_snippets' ),
				'permission_callback' => array( Capabilities::class, 'rest_read' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/revisions/(?P<source>studio|wordpress)/(?P<revision>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_revision' ),
				'permission_callback' => array( Capabilities::class, 'rest_read' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/revisions/(?P<source>studio|wordpress)/(?P<revision>\d+)/restore',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'restore_revision' ),
				'permission_callback' => array( Capabilities::class, 'rest_write' ),
			)
		);
	}

	/**
	 * Bootstrap.
	 */
	public function bootstrap() {
		return rest_ensure_response( $this->repository->bootstrap() );
	}

	/**
	 * List.
	 */
	public function list_snippets() {
		return $this->respond( $this->repository->list_snippets() );
	}

	/**
	 * Read one snippet.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function get_snippet( $request ) {
		return $this->respond( $this->repository->get_snippet( (int) $request['id'] ) );
	}

	/**
	 * Create.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function create_snippet( $request ) {
		$result = $this->repository->create_snippet( $request->get_json_params() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$response = rest_ensure_response( $result );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Update.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function update_snippet( $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = array();
		}
		return $this->respond( $this->repository->update_snippet( (int) $request['id'], $body ) );
	}

	/**
	 * Duplicate.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function duplicate_snippet( $request ) {
		$result = $this->repository->duplicate_snippet( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$response = rest_ensure_response( $result );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Move to trash.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function trash_snippet( $request ) {
		return $this->respond( $this->repository->trash_snippet( (int) $request['id'] ) );
	}

	/**
	 * Activation.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function set_status( $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = array();
		}
		return $this->respond( $this->repository->set_active( (int) $request['id'], $body ) );
	}

	/**
	 * Static check.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function check_snippet( $request ) {
		$body = $request->get_json_params();
		$code = is_array( $body ) && isset( $body['code'] ) ? (string) $body['code'] : '';
		return $this->respond( $this->repository->check_snippet( (int) $request['id'], $code ) );
	}

	/**
	 * Revisions.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function list_revisions( $request ) {
		return $this->respond( $this->repository->list_revisions( (int) $request['id'] ) );
	}

	/**
	 * Search.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function search( $request ) {
		return $this->respond( $this->repository->search( (string) $request->get_param( 'q' ) ) );
	}

	/**
	 * Export.
	 */
	public function export_snippets() {
		return $this->respond( $this->repository->export_snippets() );
	}

	/**
	 * Revision detail.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function get_revision( $request ) {
		return $this->respond(
			$this->repository->get_revision( (string) $request['source'], (int) $request['revision'] )
		);
	}

	/**
	 * Restore.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function restore_revision( $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = array();
		}
		return $this->respond(
			$this->repository->restore_revision( (string) $request['source'], (int) $request['revision'], $body )
		);
	}

	/**
	 * Return a REST response or the error unchanged.
	 *
	 * @param mixed $result Result.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function respond( $result ) {
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}
}
