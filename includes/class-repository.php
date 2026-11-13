<?php
/**
 * Snippet read and write operations.
 *
 * @package Snippet_Studio
 */

namespace Snippet_Studio;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads and updates Woody snippet posts.
 */
class Repository {

	/**
	 * Woody boundary.
	 *
	 * @var Woody_Adapter
	 */
	private $woody;

	/**
	 * Revisions.
	 *
	 * @var Revisions
	 */
	private $revisions;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->woody     = new Woody_Adapter();
		$this->revisions = new Revisions( $this->woody );
	}

	/**
	 * Woody adapter.
	 */
	public function woody() {
		return $this->woody;
	}

	/**
	 * Revision store.
	 */
	public function revisions() {
		return $this->revisions;
	}

	/**
	 * Explorer rows, without snippet code.
	 *
	 * @return array<int,array<string,mixed>>|\WP_Error
	 */
	public function list_snippets() {
		$posts = $this->post_rows( false );
		if ( is_wp_error( $posts ) ) {
			return $posts;
		}

		$ids  = array_map(
			static function ( $post ) {
				return (int) $post->ID;
			},
			$posts
		);
		$meta = $this->meta_map(
			$ids,
			array_merge(
				array_values( $this->woody->writable_meta() ),
				array( Woody_Adapter::META_TYPE, '_edit_last' )
			)
		);

		$users = $this->user_names( $this->collect_user_ids( $posts, $meta ) );
		$rows  = array();
		foreach ( $posts as $post ) {
			$rows[] = $this->summary( $post, $meta[ (int) $post->ID ] ?? array(), $users );
		}

		return $rows;
	}

	/**
	 * One snippet, including code.
	 *
	 * @param int $post_id Snippet ID.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function get_snippet( $post_id ) {
		$post = $this->require_snippet( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		return $this->present( $post );
	}

	/**
	 * Create a snippet.
	 *
	 * @param array<string,mixed> $input Request body.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function create_snippet( array $input ) {
		$title = isset( $input['title'] ) ? $this->clean_text( (string) $input['title'], 200 ) : '';
		if ( '' === $title ) {
			return new \WP_Error(
				'snippet_studio_title',
				__( 'A snippet title is required.', 'snippet-studio' ),
				array( 'status' => 400 )
			);
		}

		$type = isset( $input['type'] ) ? sanitize_key( (string) $input['type'] ) : 'php';
		$allowed_types = array_column( $this->woody->type_options(), 'value' );
		if ( ! in_array( $type, $allowed_types, true ) ) {
			return new \WP_Error(
				'snippet_studio_type',
				__( 'That snippet type is not supported by the installed Woody version.', 'snippet-studio' ),
				array( 'status' => 400 )
			);
		}

		$code  = isset( $input['code'] ) ? $this->clean_code( (string) $input['code'] ) : '';
		$scope = isset( $input['scope'] ) ? (string) $input['scope'] : 'shortcode';
		if ( ! in_array( $scope, $this->woody->known_scopes(), true ) ) {
			$scope = 'shortcode';
		}

		$syntax = $this->syntax_gate( $code, $type, false, ! empty( $input['ignore_syntax'] ) );
		if ( is_wp_error( $syntax ) ) {
			return $syntax;
		}

		$saved = $this->woody->save_post(
			0,
			array(
				'post_title'   => $title,
				'post_content' => $code,
			),
			$type
		);
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$post_id = (int) $saved;
		update_post_meta( $post_id, Woody_Adapter::META_TYPE, $type );
		update_post_meta( $post_id, Woody_Adapter::META_SCOPE, $scope );
		update_post_meta( $post_id, Woody_Adapter::META_ACTIVATE, '0' );
		update_post_meta( $post_id, Woody_Adapter::META_PRIORITY, $this->woody->next_priority() );
		update_post_meta( $post_id, Woody_Adapter::META_DESCRIPTION, '' );
		update_post_meta( $post_id, Woody_Adapter::META_TAGS, '' );

		if ( in_array( $type, array( 'js', 'css' ), true ) ) {
			update_post_meta( $post_id, Woody_Adapter::META_LINKING, 'external' );
			update_post_meta( $post_id, Woody_Adapter::META_LOCATION, 'header' );
			$this->woody->regenerate_assets( $post_id );
		}

		$post  = get_post( $post_id );
		$this->revisions->record_created( $post_id, $this->capture( $post ), __( 'Created in Snippet Studio.', 'snippet-studio' ) );

		$presented           = $this->present( $post );
		$presented['syntax'] = $syntax;
		return $presented;
	}

	/**
	 * Update code and only the properties included in the request.
	 *
	 * @param int                 $post_id Snippet ID.
	 * @param array<string,mixed> $input Request body.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function update_snippet( $post_id, array $input ) {
		$post = $this->require_snippet( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$before     = $this->capture( $post );
		$properties = $this->sanitize_properties( $post_id, isset( $input['properties'] ) && is_array( $input['properties'] ) ? $input['properties'] : array() );
		if ( is_wp_error( $properties ) ) {
			return $properties;
		}

		$postarr = array();
		if ( array_key_exists( 'title', $input ) ) {
			$title = $this->clean_text( (string) $input['title'], 200 );
			if ( $title !== $post->post_title ) {
				$postarr['post_title'] = $title;
			}
		}
		if ( array_key_exists( 'code', $input ) ) {
			$code = $this->clean_code( (string) $input['code'] );
			if ( $code !== (string) $post->post_content ) {
				$postarr['post_content'] = $code;
			}
		}

		$changed_meta = $this->changed_properties( $post_id, $properties );
		if ( empty( $postarr ) && empty( $changed_meta ) ) {
			$presented           = $this->present( $post );
			$presented['syntax'] = array(
				'valid'   => true,
				'checked' => false,
				'message' => '',
			);
			return $presented;
		}

		$guard = $this->guard_version(
			$post,
			$input,
			isset( $postarr['post_content'] ),
			! empty( $changed_meta )
		);
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$type   = $this->woody->raw_meta( $post_id, Woody_Adapter::META_TYPE );
		$code   = array_key_exists( 'post_content', $postarr ) ? (string) $postarr['post_content'] : (string) $post->post_content;
		$active = array_key_exists( 'active', $changed_meta ) ? ( '1' === $changed_meta['active'] ) : $this->woody->is_active( $post_id );
		$syntax = $this->syntax_gate( $code, $type, $active, ! empty( $input['ignore_syntax'] ) );
		if ( is_wp_error( $syntax ) ) {
			return $syntax;
		}

		if ( ! empty( $postarr ) ) {
			$saved = $this->woody->save_post( $post_id, $postarr, $type );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}

		foreach ( $changed_meta as $field => $value ) {
			$this->woody->update_field( $post_id, $field, $value );
		}

		if ( empty( $postarr ) ) {
			$this->woody->touch( $post_id );
			update_post_meta( $post_id, '_edit_last', get_current_user_id() );
		}

		if ( isset( $postarr['post_content'] ) || isset( $changed_meta['linking'] ) ) {
			$this->woody->regenerate_assets( $post_id );
		} elseif ( class_exists( '\WINP_Helper' ) && method_exists( '\WINP_Helper', 'flush_page_cache' ) ) {
			\WINP_Helper::flush_page_cache();
		}

		$fresh = get_post( $post_id );
		$this->revisions->record_successful_save(
			$post_id,
			$before,
			$this->capture( $fresh ),
			__( 'Saved in Snippet Studio.', 'snippet-studio' )
		);

		$presented           = $this->present( $fresh );
		$presented['syntax'] = $syntax;
		return $presented;
	}

	/**
	 * Duplicate a snippet. The copy is inactive so it cannot run the same hooks twice.
	 *
	 * @param int $post_id Source snippet.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function duplicate_snippet( $post_id ) {
		$post = $this->require_snippet( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$type  = $this->woody->raw_meta( $post_id, Woody_Adapter::META_TYPE );
		$title = trim( (string) $post->post_title );
		$title = '' === $title ? __( 'Untitled copy', 'snippet-studio' ) : $title . ' copy';

		$saved = $this->woody->save_post(
			0,
			array(
				'post_title'   => $title,
				'post_content' => (string) $post->post_content,
			),
			'' === $type ? 'php' : $type
		);
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$new_id = (int) $saved;
		$this->copy_meta( $post_id, $new_id );
		update_post_meta( $new_id, Woody_Adapter::META_ACTIVATE, '0' );
		delete_post_meta( $new_id, Woody_Adapter::META_SLUG );
		delete_post_meta( $new_id, Woody_Adapter::META_EXIST_SLUG );

		if ( in_array( $type, array( 'js', 'css' ), true ) ) {
			$this->woody->regenerate_assets( $new_id );
		}

		$created = get_post( $new_id );
		$this->revisions->record_created( $new_id, $this->capture( $created ), __( 'Duplicated in Snippet Studio.', 'snippet-studio' ) );

		return $this->present( $created );
	}

	/**
	 * Move a snippet to the trash. Woody deactivates trashed snippets, and the
	 * save hooks that would execute code are suspended for this write.
	 *
	 * @param int $post_id Snippet ID.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function trash_snippet( $post_id ) {
		$post = $this->require_snippet( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			return new \WP_Error(
				'snippet_studio_forbidden',
				__( 'You do not have permission to delete this snippet.', 'snippet-studio' ),
				array( 'status' => 403 )
			);
		}

		$trashed = $this->woody->with_safe_write(
			static function () use ( $post_id ) {
				return wp_trash_post( $post_id );
			}
		);
		if ( ! $trashed ) {
			return new \WP_Error(
				'snippet_studio_trash',
				__( 'The snippet could not be moved to the trash.', 'snippet-studio' ),
				array( 'status' => 500 )
			);
		}

		update_post_meta( $post_id, Woody_Adapter::META_ACTIVATE, '0' );
		if ( class_exists( '\WINP_Helper' ) && method_exists( '\WINP_Helper', 'flush_page_cache' ) ) {
			\WINP_Helper::flush_page_cache();
		}

		return array(
			'id'      => (int) $post_id,
			'trashed' => true,
		);
	}

	/**
	 * Activate or deactivate without rewriting code.
	 *
	 * @param int                 $post_id Snippet ID.
	 * @param array<string,mixed> $input Request body.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function set_active( $post_id, array $input ) {
		$post = $this->require_snippet( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$active = ! empty( $input['active'] );
		if ( $active ) {
			$type   = $this->woody->raw_meta( $post_id, Woody_Adapter::META_TYPE );
			$syntax = $this->syntax_gate( (string) $post->post_content, $type, true, false );
			if ( is_wp_error( $syntax ) ) {
				return $syntax;
			}
		}

		$current = $this->woody->is_active( $post_id );
		if ( $current === $active ) {
			return $this->present( $post );
		}

		$guard = $this->guard_version( $post, $input, false, true );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$before = $this->capture( $post );
		$this->woody->update_field( $post_id, 'active', $active ? '1' : '0' );
		$this->woody->touch( $post_id );
		update_post_meta( $post_id, '_edit_last', get_current_user_id() );

		if ( class_exists( '\WINP_Helper' ) && method_exists( '\WINP_Helper', 'flush_page_cache' ) ) {
			\WINP_Helper::flush_page_cache();
		}

		$fresh = get_post( $post_id );
		$this->revisions->record_successful_save(
			$post_id,
			$before,
			$this->capture( $fresh ),
			$active ? __( 'Activated in Snippet Studio.', 'snippet-studio' ) : __( 'Deactivated in Snippet Studio.', 'snippet-studio' )
		);

		return $this->present( $fresh );
	}

	/**
	 * Search titles, IDs, and code on the server.
	 *
	 * @param string $query Query.
	 * @return array<int,array<string,mixed>>|\WP_Error
	 */
	public function search( $query ) {
		global $wpdb;

		$query = trim( (string) $query );
		if ( strlen( $query ) < 2 ) {
			return array();
		}

		if ( ! Capabilities::woody_ready() ) {
			return $this->woody_missing();
		}

		$like = '%' . $wpdb->esc_like( $query ) . '%';
		$id   = ctype_digit( $query ) ? (int) $query : 0;

		$sql = $wpdb->prepare(
			"SELECT ID, post_title, post_content, post_modified_gmt
			FROM {$wpdb->posts}
			WHERE post_type = %s
			AND post_status NOT IN ('trash','auto-draft')
			AND (post_title LIKE %s OR post_content LIKE %s OR ID = %d)
			ORDER BY post_modified_gmt DESC
			LIMIT 40",
			Capabilities::post_type(),
			$like,
			$like,
			$id
		);

		$rows = $wpdb->get_results( $sql );
		$ids  = array_map(
			static function ( $row ) {
				return (int) $row->ID;
			},
			(array) $rows
		);
		$meta = $this->meta_map( $ids, array( Woody_Adapter::META_TYPE, Woody_Adapter::META_ACTIVATE ) );

		$results = array();
		foreach ( (array) $rows as $row ) {
			$meta_row = $meta[ (int) $row->ID ] ?? array();
			$results[] = array(
				'id'           => (int) $row->ID,
				'title'        => (string) $row->post_title,
				'type'         => isset( $meta_row[ Woody_Adapter::META_TYPE ] ) ? (string) $meta_row[ Woody_Adapter::META_TYPE ] : 'php',
				'active'       => isset( $meta_row[ Woody_Adapter::META_ACTIVATE ] ) && '1' === (string) $meta_row[ Woody_Adapter::META_ACTIVATE ],
				'modified_gmt' => (string) $row->post_modified_gmt,
				'title_match'  => false !== stripos( (string) $row->post_title, $query ) || (int) $row->ID === $id,
				'matches'      => $this->excerpts( (string) $row->post_content, $query ),
			);
		}

		return $results;
	}

	/**
	 * Backup payload.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public function export_snippets() {
		$posts = $this->post_rows( true );
		if ( is_wp_error( $posts ) ) {
			return $posts;
		}

		$ids  = array_map(
			static function ( $post ) {
				return (int) $post->ID;
			},
			$posts
		);
		$meta = $this->meta_map(
			$ids,
			array(
				Woody_Adapter::META_TYPE,
				Woody_Adapter::META_ACTIVATE,
				Woody_Adapter::META_SCOPE,
				Woody_Adapter::META_PRIORITY,
				Woody_Adapter::META_LOCATION,
				Woody_Adapter::META_NUMBER,
				Woody_Adapter::META_LINKING,
				Woody_Adapter::META_DESCRIPTION,
				Woody_Adapter::META_TAGS,
			)
		);

		$snippets = array();
		foreach ( $posts as $post ) {
			$row        = $meta[ (int) $post->ID ] ?? array();
			$snippets[] = array(
				'id'              => (int) $post->ID,
				'title'           => (string) $post->post_title,
				'code'            => (string) $post->post_content,
				'status'          => (string) $post->post_status,
				'modified_gmt'    => (string) $post->post_modified_gmt,
				'type'            => isset( $row[ Woody_Adapter::META_TYPE ] ) ? (string) $row[ Woody_Adapter::META_TYPE ] : '',
				'active'          => isset( $row[ Woody_Adapter::META_ACTIVATE ] ) && '1' === (string) $row[ Woody_Adapter::META_ACTIVATE ],
				'scope'           => isset( $row[ Woody_Adapter::META_SCOPE ] ) ? (string) $row[ Woody_Adapter::META_SCOPE ] : '',
				'priority'        => isset( $row[ Woody_Adapter::META_PRIORITY ] ) ? (string) $row[ Woody_Adapter::META_PRIORITY ] : '',
				'location'        => isset( $row[ Woody_Adapter::META_LOCATION ] ) ? (string) $row[ Woody_Adapter::META_LOCATION ] : '',
				'location_number' => isset( $row[ Woody_Adapter::META_NUMBER ] ) ? (string) $row[ Woody_Adapter::META_NUMBER ] : '',
				'linking'         => isset( $row[ Woody_Adapter::META_LINKING ] ) ? (string) $row[ Woody_Adapter::META_LINKING ] : '',
				'description'     => isset( $row[ Woody_Adapter::META_DESCRIPTION ] ) ? (string) $row[ Woody_Adapter::META_DESCRIPTION ] : '',
				'tags'            => isset( $row[ Woody_Adapter::META_TAGS ] ) ? (string) $row[ Woody_Adapter::META_TAGS ] : '',
			);
		}

		return array(
			'exported_gmt' => gmdate( 'c' ),
			'generator'    => 'snippet-studio',
			'snippets'     => $snippets,
		);
	}

	/**
	 * Check code without saving it.
	 *
	 * @param int    $post_id Snippet ID.
	 * @param string $code Code from the editor.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function check_snippet( $post_id, $code ) {
		$post = $this->require_snippet( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$type = $this->woody->raw_meta( $post_id, Woody_Adapter::META_TYPE );
		return Validator::check( $this->clean_code( $code ), $type );
	}

	/**
	 * Revision list for a snippet.
	 *
	 * @param int $post_id Snippet ID.
	 * @return array<int,array<string,mixed>>|\WP_Error
	 */
	public function list_revisions( $post_id ) {
		$post = $this->require_snippet( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		return $this->revisions->list_for_snippet( $post_id );
	}

	/**
	 * One revision with code.
	 *
	 * @param string $source studio|wordpress.
	 * @param int    $revision_id Revision ID.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function get_revision( $source, $revision_id ) {
		if ( 'wordpress' === $source ) {
			$row = $this->revisions->get_wordpress_revision( $revision_id );
		} else {
			$row = $this->revisions->get( $revision_id );
		}

		if ( ! $row ) {
			return new \WP_Error(
				'snippet_studio_revision',
				__( 'That revision could not be found.', 'snippet-studio' ),
				array( 'status' => 404 )
			);
		}

		$post = $this->require_snippet( (int) $row['snippet_id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return $row;
	}

	/**
	 * Restore a revision. The current version is snapshotted first.
	 *
	 * @param string              $source studio|wordpress.
	 * @param int                 $revision_id Revision ID.
	 * @param array<string,mixed> $input Conflict tokens.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function restore_revision( $source, $revision_id, array $input ) {
		$revision = $this->get_revision( $source, $revision_id );
		if ( is_wp_error( $revision ) ) {
			return $revision;
		}

		$post_id = (int) $revision['snippet_id'];
		$post    = $this->require_snippet( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$guard = $this->guard_version( $post, $input, true, true );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$type   = $this->woody->raw_meta( $post_id, Woody_Adapter::META_TYPE );
		$active = $this->woody->is_active( $post_id );
		if ( is_array( $revision['meta'] ) && isset( $revision['meta'][ Woody_Adapter::META_ACTIVATE ] ) ) {
			$active = '1' === (string) $revision['meta'][ Woody_Adapter::META_ACTIVATE ];
		}
		$syntax = $this->syntax_gate( (string) $revision['code'], '' === $type ? 'php' : $type, $active, true );
		if ( is_wp_error( $syntax ) ) {
			return $syntax;
		}

		$before = $this->capture( $post );
		$this->revisions->record_before_restore( $post_id, $before );
		$saved = $this->woody->save_post(
			$post_id,
			array(
				'post_title'   => (string) $revision['title'],
				'post_content' => (string) $revision['code'],
			),
			$type
		);
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		if ( ! empty( $revision['meta'] ) && is_array( $revision['meta'] ) ) {
			foreach ( $revision['meta'] as $key => $value ) {
				if ( ! is_string( $key ) || 0 !== strpos( $key, 'wbcr_inp_' ) ) {
					continue;
				}
				update_post_meta( $post_id, $key, wp_slash( $value ) );
			}
		}

		$this->woody->regenerate_assets( $post_id );

		$fresh = get_post( $post_id );
		$this->revisions->record_successful_save(
			$post_id,
			$before,
			$this->capture( $fresh ),
			__( 'Restored in Snippet Studio.', 'snippet-studio' )
		);

		return $this->present( $fresh );
	}

	/**
	 * Editor bootstrap data.
	 *
	 * @return array<string,mixed>
	 */
	public function bootstrap() {
		$user = wp_get_current_user();
		return array(
			'version'          => SNIPPET_STUDIO_VERSION,
			'woody_active'     => Capabilities::woody_ready(),
			'woody_version'    => defined( 'WINP_PLUGIN_VERSION' ) ? WINP_PLUGIN_VERSION : '',
			'post_type'        => Capabilities::post_type(),
			'can_write'        => Capabilities::can_write(),
			'file_edit_locked' => Capabilities::file_edit_disabled(),
			'user'             => array(
				'id'   => (int) $user->ID,
				'name' => $user->display_name,
			),
			'scopes'           => $this->woody->scope_options(),
			'types'            => $this->woody->type_options(),
			'locations'        => $this->woody->locations(),
			'classic_url'      => $this->woody->list_url(),
		);
	}

	/**
	 * Full snippet payload.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<string,mixed>
	 */
	private function present( $post ) {
		$meta      = $this->meta_map(
			array( (int) $post->ID ),
			array_merge(
				array_values( $this->woody->writable_meta() ),
				array( Woody_Adapter::META_TYPE, '_edit_last' )
			)
		);
		$row       = $meta[ (int) $post->ID ] ?? array();
		$type      = isset( $row[ Woody_Adapter::META_TYPE ] ) ? (string) $row[ Woody_Adapter::META_TYPE ] : 'php';
		$editor_id = isset( $row['_edit_last'] ) ? (int) $row['_edit_last'] : 0;
		$users     = $this->user_names( array( (int) $post->post_author, $editor_id ) );
		$code      = (string) $post->post_content;

		return array(
			'id'              => (int) $post->ID,
			'title'           => (string) $post->post_title,
			'code'            => $code,
			'code_hash'       => $this->woody->code_hash( $code ),
			'meta_token'      => $this->woody->meta_token( (int) $post->ID ),
			'status'          => (string) $post->post_status,
			'type'            => $type,
			'active'          => isset( $row[ Woody_Adapter::META_ACTIVATE ] ) && '1' === (string) $row[ Woody_Adapter::META_ACTIVATE ],
			'scope'           => isset( $row[ Woody_Adapter::META_SCOPE ] ) ? (string) $row[ Woody_Adapter::META_SCOPE ] : '',
			'priority'        => isset( $row[ Woody_Adapter::META_PRIORITY ] ) ? (string) $row[ Woody_Adapter::META_PRIORITY ] : '',
			'location'        => isset( $row[ Woody_Adapter::META_LOCATION ] ) ? (string) $row[ Woody_Adapter::META_LOCATION ] : '',
			'location_number' => isset( $row[ Woody_Adapter::META_NUMBER ] ) ? (string) $row[ Woody_Adapter::META_NUMBER ] : '',
			'linking'         => isset( $row[ Woody_Adapter::META_LINKING ] ) ? (string) $row[ Woody_Adapter::META_LINKING ] : '',
			'description'     => isset( $row[ Woody_Adapter::META_DESCRIPTION ] ) ? (string) $row[ Woody_Adapter::META_DESCRIPTION ] : '',
			'tags'            => isset( $row[ Woody_Adapter::META_TAGS ] ) ? (string) $row[ Woody_Adapter::META_TAGS ] : '',
			'shortcode'       => $this->woody->shortcode( (int) $post->ID, $type ),
			'modified_gmt'    => (string) $post->post_modified_gmt,
			'date_gmt'        => (string) $post->post_date_gmt,
			'author'          => $users[ (int) $post->post_author ] ?? '',
			'editor'          => $editor_id && isset( $users[ $editor_id ] ) ? $users[ $editor_id ] : ( $users[ (int) $post->post_author ] ?? '' ),
			'woody_url'       => $this->woody->edit_url( (int) $post->ID ),
		);
	}

	/**
	 * Explorer row.
	 *
	 * @param object               $post Post row.
	 * @param array<string,string> $meta Meta values.
	 * @param array<int,string>    $users User names.
	 * @return array<string,mixed>
	 */
	private function summary( $post, array $meta, array $users ) {
		$editor_id = isset( $meta['_edit_last'] ) ? (int) $meta['_edit_last'] : 0;
		return array(
			'id'           => (int) $post->ID,
			'title'        => (string) $post->post_title,
			'status'       => (string) $post->post_status,
			'type'         => isset( $meta[ Woody_Adapter::META_TYPE ] ) ? (string) $meta[ Woody_Adapter::META_TYPE ] : 'php',
			'active'       => isset( $meta[ Woody_Adapter::META_ACTIVATE ] ) && '1' === (string) $meta[ Woody_Adapter::META_ACTIVATE ],
			'scope'        => isset( $meta[ Woody_Adapter::META_SCOPE ] ) ? (string) $meta[ Woody_Adapter::META_SCOPE ] : '',
			'priority'     => isset( $meta[ Woody_Adapter::META_PRIORITY ] ) ? (string) $meta[ Woody_Adapter::META_PRIORITY ] : '0',
			'location'     => isset( $meta[ Woody_Adapter::META_LOCATION ] ) ? (string) $meta[ Woody_Adapter::META_LOCATION ] : '',
			'modified_gmt' => (string) $post->post_modified_gmt,
			'date_gmt'     => (string) $post->post_date_gmt,
			'meta_token'   => $this->token_from( $meta ),
			'author'       => $users[ (int) $post->post_author ] ?? '',
			'editor'       => $editor_id && isset( $users[ $editor_id ] ) ? $users[ $editor_id ] : ( $users[ (int) $post->post_author ] ?? '' ),
		);
	}

	/**
	 * Capture title, code, and meta for a revision.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<string,mixed>
	 */
	/**
	 * Hash writable meta from an already loaded row.
	 *
	 * @param array<string,string> $meta Meta values keyed by Woody meta key.
	 */
	private function token_from( array $meta ) {
		$values = array();
		foreach ( $this->woody->writable_meta() as $name => $key ) {
			$values[ $name ] = isset( $meta[ $key ] ) ? (string) $meta[ $key ] : '';
		}
		return hash( 'sha256', (string) wp_json_encode( $values ) );
	}

	/**
	 * Capture title, code, and meta for a revision.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<string,mixed>
	 */
	private function capture( $post ) {
		return array(
			'title' => (string) $post->post_title,
			'code'  => (string) $post->post_content,
			'meta'  => $this->woody->snapshot_meta( (int) $post->ID ),
		);
	}

	/**
	 * Load snippet posts.
	 *
	 * @param bool $with_content Whether to select post_content.
	 * @return array<int,object>|\WP_Error
	 */
	private function post_rows( $with_content ) {
		global $wpdb;

		if ( ! Capabilities::woody_ready() ) {
			return $this->woody_missing();
		}

		$columns = 'ID, post_title, post_status, post_author, post_date_gmt, post_modified_gmt';
		if ( $with_content ) {
			$columns .= ', post_content';
		}

		$sql = $wpdb->prepare(
			"SELECT {$columns} FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('trash','auto-draft') ORDER BY post_modified_gmt DESC",
			Capabilities::post_type()
		);

		return (array) $wpdb->get_results( $sql );
	}

	/**
	 * Require a Woody snippet the user can read.
	 *
	 * @param int $post_id Post ID.
	 * @return \WP_Post|\WP_Error
	 */
	private function require_snippet( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post || Capabilities::post_type() !== $post->post_type || 'trash' === $post->post_status ) {
			return new \WP_Error(
				'snippet_studio_not_found',
				__( 'That snippet could not be found.', 'snippet-studio' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'snippet_studio_forbidden',
				__( 'You do not have permission to open this snippet.', 'snippet-studio' ),
				array( 'status' => 403 )
			);
		}

		return $post;
	}

	/**
	 * Reject a write when the snippet changed since it was opened.
	 *
	 * @param \WP_Post            $post Current post.
	 * @param array<string,mixed> $input Request body.
	 * @param bool                $writes_code Whether code is being written.
	 * @param bool                $writes_meta Whether meta is being written.
	 * @return true|\WP_Error
	 */
	private function guard_version( $post, array $input, $writes_code, $writes_meta ) {
		if ( ! empty( $input['force'] ) ) {
			return true;
		}

		$modified = isset( $input['base_modified'] ) ? (string) $input['base_modified'] : '';
		$code     = isset( $input['base_code_hash'] ) ? (string) $input['base_code_hash'] : '';
		$token    = isset( $input['base_meta_token'] ) ? (string) $input['base_meta_token'] : '';

		$stale = $modified !== (string) $post->post_modified_gmt;
		if ( $writes_code && ! hash_equals( $this->woody->code_hash( (string) $post->post_content ), $code ) ) {
			$stale = true;
		}
		if ( $writes_meta && ! hash_equals( $this->woody->meta_token( (int) $post->ID ), $token ) ) {
			$stale = true;
		}

		if ( ! $stale ) {
			return true;
		}

		return new \WP_Error(
			'snippet_studio_conflict',
			__( 'This snippet was updated somewhere else. Review the difference before overwriting it.', 'snippet-studio' ),
			array(
				'status' => 409,
				'server' => $this->present( $post ),
			)
		);
	}

	/**
	 * Validate and normalize property edits. Unknown keys are ignored.
	 *
	 * @param int                 $post_id Snippet ID.
	 * @param array<string,mixed> $properties Properties.
	 * @return array<string,string>|\WP_Error
	 */
	private function sanitize_properties( $post_id, array $properties ) {
		$clean = array();

		if ( array_key_exists( 'scope', $properties ) ) {
			$scope   = (string) $properties['scope'];
			$current = $this->woody->raw_meta( $post_id, Woody_Adapter::META_SCOPE );
			if ( ! in_array( $scope, $this->woody->known_scopes(), true ) && $scope !== $current ) {
				return new \WP_Error(
					'snippet_studio_scope',
					__( 'That execution scope is not supported. The current value was left unchanged.', 'snippet-studio' ),
					array( 'status' => 400 )
				);
			}
			$clean['scope'] = $scope;
		}

		if ( array_key_exists( 'location', $properties ) ) {
			$location = (string) $properties['location'];
			$current  = $this->woody->raw_meta( $post_id, Woody_Adapter::META_LOCATION );
			$known    = array_column( $this->woody->locations(), 'value' );
			if ( '' !== $location && ! in_array( $location, $known, true ) && $location !== $current ) {
				return new \WP_Error(
					'snippet_studio_location',
					__( 'That insertion location is not supported by the installed Woody version.', 'snippet-studio' ),
					array( 'status' => 400 )
				);
			}
			$clean['location'] = $location;
		}

		if ( array_key_exists( 'location_number', $properties ) ) {
			$number = (string) $properties['location_number'];
			if ( '' !== $number && ! preg_match( '/^-?\d+$/', $number ) ) {
				return new \WP_Error(
					'snippet_studio_location_number',
					__( 'The location number has to be an integer.', 'snippet-studio' ),
					array( 'status' => 400 )
				);
			}
			$clean['location_number'] = $number;
		}

		if ( array_key_exists( 'priority', $properties ) ) {
			$priority = trim( (string) $properties['priority'] );
			if ( ! preg_match( '/^-?\d+$/', $priority ) ) {
				return new \WP_Error(
					'snippet_studio_priority',
					__( 'Priority has to be an integer.', 'snippet-studio' ),
					array( 'status' => 400 )
				);
			}
			$clean['priority'] = (string) (int) $priority;
		}

		if ( array_key_exists( 'description', $properties ) ) {
			$clean['description'] = $this->clean_text( (string) $properties['description'], 20000 );
		}

		if ( array_key_exists( 'tags', $properties ) ) {
			$clean['tags'] = $this->clean_text( (string) $properties['tags'], 5000 );
		}

		if ( array_key_exists( 'linking', $properties ) ) {
			$linking = (string) $properties['linking'];
			$current = $this->woody->raw_meta( $post_id, Woody_Adapter::META_LINKING );
			if ( ! in_array( $linking, array( 'external', 'inline' ), true ) && $linking !== $current ) {
				return new \WP_Error(
					'snippet_studio_linking',
					__( 'Linking must be external or inline.', 'snippet-studio' ),
					array( 'status' => 400 )
				);
			}
			$clean['linking'] = $linking;
		}

		if ( array_key_exists( 'active', $properties ) ) {
			$clean['active'] = ! empty( $properties['active'] ) ? '1' : '0';
		}

		return $clean;
	}

	/**
	 * Property values that differ from what is stored.
	 *
	 * @param int                  $post_id Snippet ID.
	 * @param array<string,string> $properties Sanitized properties.
	 * @return array<string,string>
	 */
	private function changed_properties( $post_id, array $properties ) {
		$map     = $this->woody->writable_meta();
		$changed = array();
		foreach ( $properties as $field => $value ) {
			if ( ! isset( $map[ $field ] ) ) {
				continue;
			}
			$current = $this->woody->raw_meta( $post_id, $map[ $field ] );
			if ( 'active' === $field ) {
				$current = ( '1' === $current ) ? '1' : '0';
			}
			if ( (string) $value !== $current ) {
				$changed[ $field ] = (string) $value;
			}
		}
		return $changed;
	}

	/**
	 * Block invalid PHP while the snippet would be active.
	 *
	 * @param string $code Code.
	 * @param string $type Type.
	 * @param bool   $active Whether the saved snippet would run.
	 * @param bool   $ignore Whether an inactive snippet may be saved anyway.
	 * @return array{valid:bool,checked:bool,message:string}|\WP_Error
	 */
	private function syntax_gate( $code, $type, $active, $ignore ) {
		$check = Validator::check( $code, $type );
		if ( empty( $check['checked'] ) || ! empty( $check['valid'] ) ) {
			return $check;
		}

		if ( $ignore && ! $active ) {
			return $check;
		}

		return new \WP_Error(
			'snippet_studio_syntax',
			$check['message'],
			array(
				'status'       => 422,
				'syntax'       => true,
				'can_override' => ! $active,
			)
		);
	}

	/**
	 * Copy Woody meta onto a duplicate, skipping file slugs.
	 *
	 * @param int $from Source ID.
	 * @param int $to Destination ID.
	 */
	private function copy_meta( $from, $to ) {
		$all = get_post_meta( $from );
		if ( ! is_array( $all ) ) {
			return;
		}

		$skip = array(
			Woody_Adapter::META_SLUG       => true,
			Woody_Adapter::META_EXIST_SLUG => true,
			Woody_Adapter::META_ACTIVATE   => true,
			Woody_Adapter::META_VERSION    => true,
		);

		foreach ( $all as $key => $values ) {
			if ( 0 !== strpos( (string) $key, 'wbcr_inp_' ) || isset( $skip[ $key ] ) ) {
				continue;
			}
			$value = isset( $values[0] ) ? maybe_unserialize( $values[0] ) : '';
			update_post_meta( $to, $key, wp_slash( $value ) );
		}
	}

	/**
	 * Meta values for many posts, keyed by post ID and meta key.
	 *
	 * @param int[]    $ids Post IDs.
	 * @param string[] $keys Meta keys.
	 * @return array<int,array<string,string>>
	 */
	private function meta_map( array $ids, array $keys ) {
		global $wpdb;

		$ids  = array_values( array_filter( array_map( 'intval', $ids ) ) );
		$keys = array_values( array_filter( array_map( 'strval', $keys ) ) );
		$map  = array();
		if ( empty( $ids ) || empty( $keys ) ) {
			return $map;
		}

		$id_sql  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$key_sql = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$sql     = $wpdb->prepare(
			"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ({$id_sql}) AND meta_key IN ({$key_sql})",
			array_merge( $ids, $keys )
		);

		foreach ( (array) $wpdb->get_results( $sql ) as $row ) {
			$value = maybe_unserialize( $row->meta_value );
			if ( is_array( $value ) || is_object( $value ) ) {
				$encoded = wp_json_encode( $value );
				$value   = is_string( $encoded ) ? $encoded : '';
			}
			$map[ (int) $row->post_id ][ (string) $row->meta_key ] = (string) $value;
		}

		return $map;
	}

	/**
	 * User IDs referenced by a page of snippets.
	 *
	 * @param array<int,object>              $posts Posts.
	 * @param array<int,array<string,string>> $meta Meta map.
	 * @return int[]
	 */
	private function collect_user_ids( array $posts, array $meta ) {
		$ids = array();
		foreach ( $posts as $post ) {
			$ids[] = (int) $post->post_author;
			if ( isset( $meta[ (int) $post->ID ]['_edit_last'] ) ) {
				$ids[] = (int) $meta[ (int) $post->ID ]['_edit_last'];
			}
		}
		return $ids;
	}

	/**
	 * Display names for user IDs.
	 *
	 * @param int[] $ids User IDs.
	 * @return array<int,string>
	 */
	private function user_names( array $ids ) {
		$names = array();
		foreach ( array_unique( array_filter( array_map( 'intval', $ids ) ) ) as $id ) {
			$user = get_userdata( $id );
			if ( $user ) {
				$names[ $id ] = $user->display_name;
			}
		}
		return $names;
	}

	/**
	 * Matching lines for a code search.
	 *
	 * @param string $content Code.
	 * @param string $query Query.
	 * @return array<int,array{line:int,text:string}>
	 */
	private function excerpts( $content, $query ) {
		$lines   = preg_split( "/\r\n|\n|\r/", $content );
		$matches = array();
		if ( ! is_array( $lines ) ) {
			return $matches;
		}

		foreach ( $lines as $index => $line ) {
			if ( false === stripos( $line, $query ) ) {
				continue;
			}
			$matches[] = array(
				'line' => $index + 1,
				'text' => function_exists( 'mb_substr' ) ? mb_substr( $line, 0, 180 ) : substr( $line, 0, 180 ),
			);
			if ( count( $matches ) >= 3 ) {
				break;
			}
		}

		return $matches;
	}

	/**
	 * Strip null bytes and limit length without rewriting the rest of the text.
	 *
	 * @param string $value Value.
	 * @param int    $limit Maximum length.
	 */
	private function clean_text( $value, $limit ) {
		$value = str_replace( "\0", '', $value );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $limit );
		}
		return substr( $value, 0, $limit );
	}

	/**
	 * Remove null bytes from code and nothing else.
	 *
	 * @param string $code Code.
	 */
	private function clean_code( $code ) {
		return str_replace( "\0", '', $code );
	}

	/**
	 * Error used when Woody is not available.
	 */
	private function woody_missing() {
		return new \WP_Error(
			'snippet_studio_woody',
			__( 'Woody Code Snippets is not active, so there are no snippets to edit.', 'snippet-studio' ),
			array( 'status' => 409 )
		);
	}
}
