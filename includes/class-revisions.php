<?php
/**
 * Independent snippet revision store.
 *
 * @package Snippet_Studio
 */

namespace Snippet_Studio;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores code and metadata snapshots in a dedicated table.
 */
class Revisions {

	const KEEP = 100;

	/**
	 * Adapter.
	 *
	 * @var Woody_Adapter
	 */
	private $woody;

	/**
	 * Constructor.
	 *
	 * @param Woody_Adapter $woody Adapter.
	 */
	public function __construct( Woody_Adapter $woody ) {
		$this->woody = $woody;
	}

	/**
	 * Record the state before the first Studio edit, then the saved state.
	 *
	 * @param int                  $snippet_id Snippet ID.
	 * @param array<string,mixed>  $before State before the write.
	 * @param array<string,mixed>  $after State after the write.
	 * @param string               $note Note.
	 */
	public function record_successful_save( $snippet_id, array $before, array $after, $note = '' ) {
		if ( ! $this->has_baseline( $snippet_id ) ) {
			$this->insert( $snippet_id, $before, 'baseline', __( 'Original version before the first Snippet Studio edit.', 'snippet-studio' ) );
		}

		$this->insert( $snippet_id, $after, 'save', $note );
		$this->prune( $snippet_id );
	}

	/**
	 * Snapshot the current version before it is replaced by a restore.
	 *
	 * @param int                 $snippet_id Snippet ID.
	 * @param array<string,mixed> $current Current state.
	 */
	/**
	 * Record the first stored copy of a snippet created in Snippet Studio.
	 *
	 * @param int                 $snippet_id Snippet ID.
	 * @param array<string,mixed> $state State.
	 * @param string              $note Note.
	 */
	public function record_created( $snippet_id, array $state, $note ) {
		$this->insert( $snippet_id, $state, 'save', $note );
	}

	/**
	 * Snapshot the current version before it is replaced by a restore.
	 *
	 * @param int                 $snippet_id Snippet ID.
	 * @param array<string,mixed> $current Current state.
	 */
	public function record_before_restore( $snippet_id, array $current ) {
		$this->insert(
			$snippet_id,
			$current,
			'pre-restore',
			__( 'Saved automatically before a revision was restored.', 'snippet-studio' )
		);
	}

	/**
	 * Whether this snippet already has a Studio revision.
	 *
	 * @param int $snippet_id Snippet ID.
	 */
	public function has_baseline( $snippet_id ) {
		global $wpdb;

		$found = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . Activator::table_name() . ' WHERE snippet_id = %d LIMIT 1',
				(int) $snippet_id
			)
		);

		return null !== $found;
	}

	/**
	 * List revision summaries. Code stays out of the list.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function list_for_snippet( $snippet_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, snippet_id, user_id, created_gmt, title, note, kind FROM ' . Activator::table_name() . ' WHERE snippet_id = %d ORDER BY id DESC',
				(int) $snippet_id
			)
		);

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = $this->summarize( $row, 'studio' );
		}

		foreach ( $this->wordpress_revisions( $snippet_id ) as $revision ) {
			$items[] = $revision;
		}

		usort(
			$items,
			static function ( $a, $b ) {
				return strcmp( (string) $b['created_gmt'], (string) $a['created_gmt'] );
			}
		);

		return $items;
	}

	/**
	 * Load one Studio revision, including code.
	 *
	 * @param int $revision_id Revision ID.
	 * @return array<string,mixed>|null
	 */
	public function get( $revision_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Activator::table_name() . ' WHERE id = %d',
				(int) $revision_id
			)
		);

		if ( ! $row ) {
			return null;
		}

		$meta = json_decode( (string) $row->meta_json, true );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}

		$summary         = $this->summarize( $row, 'studio' );
		$summary['code'] = (string) $row->code;
		$summary['meta'] = $meta;
		return $summary;
	}

	/**
	 * Load a real WordPress revision of a Woody snippet.
	 *
	 * @param int $revision_id Revision post ID.
	 * @return array<string,mixed>|null
	 */
	public function get_wordpress_revision( $revision_id ) {
		$revision = wp_get_post_revision( (int) $revision_id );
		if ( ! $revision ) {
			return null;
		}

		$parent = get_post( (int) $revision->post_parent );
		if ( ! $parent || Capabilities::post_type() !== $parent->post_type ) {
			return null;
		}

		return array(
			'id'          => (int) $revision->ID,
			'snippet_id'  => (int) $parent->ID,
			'source'      => 'wordpress',
			'kind'        => 'wordpress',
			'created_gmt' => (string) $revision->post_modified_gmt,
			'title'       => (string) $revision->post_title,
			'note'        => '',
			'author'      => $this->user_name( (int) $revision->post_author ),
			'user_id'     => (int) $revision->post_author,
			'code'        => (string) $revision->post_content,
			'meta'        => null,
		);
	}

	/**
	 * Insert a snapshot.
	 *
	 * @param int                  $snippet_id Snippet ID.
	 * @param array<string,mixed>  $state Title, code, and meta.
	 * @param string               $kind Kind.
	 * @param string               $note Note.
	 */
	private function insert( $snippet_id, array $state, $kind, $note ) {
		global $wpdb;

		$meta = isset( $state['meta'] ) && is_array( $state['meta'] ) ? $state['meta'] : array();

		$wpdb->insert(
			Activator::table_name(),
			array(
				'snippet_id'  => (int) $snippet_id,
				'user_id'     => get_current_user_id(),
				'created_gmt' => current_time( 'mysql', true ),
				'title'       => isset( $state['title'] ) ? (string) $state['title'] : '',
				'code'        => isset( $state['code'] ) ? (string) $state['code'] : '',
				'meta_json'   => (string) wp_json_encode( $meta ),
				'note'        => (string) $note,
				'kind'        => (string) $kind,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Keep the newest snapshots and the oldest baseline.
	 *
	 * @param int $snippet_id Snippet ID.
	 */
	private function prune( $snippet_id ) {
		global $wpdb;

		$table = Activator::table_name();
		$ids   = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE snippet_id = %d ORDER BY id DESC",
				(int) $snippet_id
			)
		);

		if ( count( $ids ) <= self::KEEP ) {
			return;
		}

		$baseline = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE snippet_id = %d AND kind = %s ORDER BY id ASC LIMIT 1",
				(int) $snippet_id,
				'baseline'
			)
		);

		$remove = array_slice( array_map( 'intval', $ids ), self::KEEP );
		if ( $baseline ) {
			$remove = array_values( array_diff( $remove, array( (int) $baseline ) ) );
		}

		foreach ( $remove as $id ) {
			$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
		}
	}

	/**
	 * WordPress revisions that already exist for the snippet.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return array<int,array<string,mixed>>
	 */
	private function wordpress_revisions( $snippet_id ) {
		$posts = wp_get_post_revisions(
			(int) $snippet_id,
			array(
				'posts_per_page' => 50,
			)
		);

		$items = array();
		foreach ( $posts as $revision ) {
			if ( wp_is_post_autosave( $revision ) ) {
				continue;
			}
			$items[] = array(
				'id'          => (int) $revision->ID,
				'snippet_id'  => (int) $snippet_id,
				'source'      => 'wordpress',
				'kind'        => 'wordpress',
				'created_gmt' => (string) $revision->post_modified_gmt,
				'title'       => (string) $revision->post_title,
				'note'        => '',
				'author'      => $this->user_name( (int) $revision->post_author ),
				'user_id'     => (int) $revision->post_author,
				'has_meta'    => false,
			);
		}

		return $items;
	}

	/**
	 * Summary row.
	 *
	 * @param object $row Database row.
	 * @param string $source Source name.
	 * @return array<string,mixed>
	 */
	private function summarize( $row, $source ) {
		return array(
			'id'          => (int) $row->id,
			'snippet_id'  => (int) $row->snippet_id,
			'source'      => $source,
			'kind'        => (string) $row->kind,
			'created_gmt' => (string) $row->created_gmt,
			'title'       => (string) $row->title,
			'note'        => (string) $row->note,
			'author'      => $this->user_name( (int) $row->user_id ),
			'user_id'     => (int) $row->user_id,
			'has_meta'    => true,
		);
	}

	/**
	 * Display name for a user ID.
	 *
	 * @param int $user_id User ID.
	 */
	private function user_name( $user_id ) {
		$user = $user_id ? get_userdata( $user_id ) : false;
		if ( $user ) {
			return $user->display_name;
		}
		return __( 'Unknown', 'snippet-studio' );
	}
}
