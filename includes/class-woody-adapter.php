<?php
/**
 * Woody Code Snippets compatibility boundary.
 *
 * All Woody-specific meta keys, asset files, and save hooks live here.
 *
 * @package Snippet_Studio
 */

namespace Snippet_Studio;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes Woody snippet records without changing Woody's files.
 */
class Woody_Adapter {

	const META_TYPE        = 'wbcr_inp_snippet_type';
	const META_ACTIVATE    = 'wbcr_inp_snippet_activate';
	const META_SCOPE       = 'wbcr_inp_snippet_scope';
	const META_PRIORITY    = 'wbcr_inp_snippet_priority';
	const META_LOCATION    = 'wbcr_inp_snippet_location';
	const META_NUMBER      = 'wbcr_inp_snippet_p_number';
	const META_LINKING     = 'wbcr_inp_snippet_linking';
	const META_FILTERS     = 'wbcr_inp_snippet_filters';
	const META_DESCRIPTION = 'wbcr_inp_snippet_description';
	const META_TAGS        = 'wbcr_inp_snippet_tags';
	const META_FILETYPE    = 'wbcr_inp_filetype';
	const META_SLUG        = 'wbcr_inp_css_js_slug';
	const META_EXIST_SLUG  = 'wbcr_inp_css_js_exist_slug';
	const META_VERSION     = 'wbcr_inp_css_js_version';

	/**
	 * Meta keys the editor is allowed to change. Everything else is left alone.
	 *
	 * @return array<string,string>
	 */
	public function writable_meta() {
		return array(
			'scope'           => self::META_SCOPE,
			'location'        => self::META_LOCATION,
			'location_number' => self::META_NUMBER,
			'priority'        => self::META_PRIORITY,
			'description'     => self::META_DESCRIPTION,
			'tags'            => self::META_TAGS,
			'linking'         => self::META_LINKING,
			'active'          => self::META_ACTIVATE,
		);
	}

	/**
	 * Known scope values this Woody version stores.
	 *
	 * @return string[]
	 */
	public function known_scopes() {
		return array( 'evrywhere', 'shortcode', 'auto' );
	}

	/**
	 * Scope labels for the properties panel.
	 *
	 * @return array<int,array{value:string,label:string}>
	 */
	public function scope_options() {
		return array(
			array(
				'value' => 'evrywhere',
				'label' => __( 'Run everywhere', 'snippet-studio' ),
			),
			array(
				'value' => 'auto',
				'label' => __( 'Automatic insertion', 'snippet-studio' ),
			),
			array(
				'value' => 'shortcode',
				'label' => __( 'Where there is a shortcode', 'snippet-studio' ),
			),
		);
	}

	/**
	 * Snippet types this Woody build registers.
	 *
	 * @return array<int,array{value:string,label:string}>
	 */
	public function type_options() {
		return array(
			array(
				'value' => 'php',
				'label' => 'PHP',
			),
			array(
				'value' => 'js',
				'label' => 'JavaScript',
			),
			array(
				'value' => 'css',
				'label' => 'CSS',
			),
			array(
				'value' => 'html',
				'label' => 'HTML',
			),
			array(
				'value' => 'universal',
				'label' => 'Universal',
			),
			array(
				'value' => 'text',
				'label' => 'Text',
			),
			array(
				'value' => 'advert',
				'label' => 'Advert',
			),
		);
	}

	/**
	 * Insertion locations exposed by the installed Woody version.
	 *
	 * @return array<int,array{value:string,label:string,description:string,requires_number:bool}>
	 */
	public function locations() {
		$locations = array();

		if ( class_exists( '\WINP_Insertion_Locations' ) ) {
			$provider = new \WINP_Insertion_Locations();
			$list     = $provider->getList();
			foreach ( $list as $key => $item ) {
				$locations[] = array(
					'value'           => (string) $key,
					'label'           => isset( $item[0] ) ? wp_strip_all_tags( (string) $item[0] ) : (string) $key,
					'description'     => isset( $item[1] ) ? wp_strip_all_tags( (string) $item[1] ) : '',
					'requires_number' => ! empty( $item[2]['requiresLocationNumber'] ),
				);
			}
			return $locations;
		}

		return $locations;
	}

	/**
	 * Meta keys copied into a revision snapshot.
	 *
	 * @param int $post_id Snippet ID.
	 * @return array<string,string>
	 */
	public function snapshot_meta( $post_id ) {
		$keys = array(
			self::META_TYPE,
			self::META_ACTIVATE,
			self::META_SCOPE,
			self::META_PRIORITY,
			self::META_LOCATION,
			self::META_NUMBER,
			self::META_LINKING,
			self::META_DESCRIPTION,
			self::META_TAGS,
			self::META_FILTERS,
			self::META_FILETYPE,
		);

		$snapshot = array();
		foreach ( $keys as $key ) {
			$snapshot[ $key ] = $this->raw_meta( $post_id, $key );
		}
		return $snapshot;
	}

	/**
	 * Token covering the writable meta values.
	 *
	 * @param int $post_id Snippet ID.
	 */
	public function meta_token( $post_id ) {
		$values = array();
		foreach ( $this->writable_meta() as $name => $key ) {
			$values[ $name ] = $this->raw_meta( $post_id, $key );
		}
		return hash( 'sha256', (string) wp_json_encode( $values ) );
	}

	/**
	 * Hash of the stored code.
	 *
	 * @param string $code Code.
	 */
	public function code_hash( $code ) {
		return hash( 'sha256', (string) $code );
	}

	/**
	 * Read one meta value exactly as stored.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key Meta key.
	 */
	public function raw_meta( $post_id, $key ) {
		$value = get_post_meta( (int) $post_id, $key, true );
		if ( is_array( $value ) || is_object( $value ) ) {
			$encoded = wp_json_encode( $value );
			return is_string( $encoded ) ? $encoded : '';
		}
		if ( null === $value || false === $value ) {
			return '';
		}
		return (string) $value;
	}

	/**
	 * Whether Woody treats the snippet as active.
	 *
	 * The execution query matches the meta value "1".
	 *
	 * @param int $post_id Post ID.
	 */
	public function is_active( $post_id ) {
		return '1' === $this->raw_meta( $post_id, self::META_ACTIVATE );
	}

	/**
	 * Shortcode Woody would print for this snippet.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $type Snippet type.
	 */
	public function shortcode( $post_id, $type ) {
		$prefix    = ( 'universal' === $type ) ? '' : $type . '_';
		$shortcode = '[wbcr_' . $prefix . 'snippet id="' . (int) $post_id . '"]';

		if ( has_filter( 'wbcr/inp/viewtable/where_use' ) ) {
			$filtered = apply_filters( 'wbcr/inp/viewtable/where_use', $shortcode, (int) $post_id );
			if ( is_string( $filtered ) && '' !== $filtered ) {
				return html_entity_decode( $filtered, ENT_QUOTES, 'UTF-8' );
			}
		}

		return $shortcode;
	}

	/**
	 * Next priority, using Woody's helper when it is loaded.
	 */
	public function next_priority() {
		if ( class_exists( '\WINP_Helper' ) && method_exists( '\WINP_Helper', 'get_next_snippet_priority' ) ) {
			return (string) \WINP_Helper::get_next_snippet_priority();
		}

		global $wpdb;
		$max = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::META_PRIORITY
			)
		);

		return (string) ( ( null === $max ? 0 : (int) $max ) + 10 );
	}

	/**
	 * Link to the original Woody editor.
	 *
	 * @param int $post_id Snippet ID.
	 */
	public function edit_url( $post_id ) {
		return admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' );
	}

	/**
	 * Woody list screen.
	 */
	public function list_url() {
		return admin_url( 'edit.php?post_type=' . Capabilities::post_type() );
	}

	/**
	 * Run a write while Woody's save hooks cannot change activation or skip content filters.
	 *
	 * Asset files are regenerated afterwards by this adapter.
	 *
	 * @param callable $callback Write callback.
	 * @return mixed
	 */
	public function with_safe_write( callable $callback ) {
		$removed_hooks = $this->suspend_woody_hooks();
		$revision_hook = has_action( 'post_updated', 'wp_save_post_revision' );
		if ( false !== $revision_hook ) {
			remove_action( 'post_updated', 'wp_save_post_revision', 10 );
		}

		try {
			return $callback();
		} finally {
			if ( false !== $revision_hook ) {
				add_action( 'post_updated', 'wp_save_post_revision', 10, 1 );
			}
			$this->resume_hooks( $removed_hooks );
		}
	}

	/**
	 * Update a snippet post without kses rewriting the code.
	 *
	 * @param int                  $post_id Post ID. 0 creates a post.
	 * @param array<string,mixed>  $postarr Post fields.
	 * @param string               $type Snippet type.
	 * @return int|\WP_Error
	 */
	public function save_post( $post_id, array $postarr, $type ) {
		$postarr['post_type'] = Capabilities::post_type();
		if ( $post_id > 0 ) {
			$postarr['ID'] = $post_id;
		} else {
			$postarr['post_status'] = 'publish';
		}

		$is_code = ! in_array( $type, array( 'text', 'advert' ), true );

		$result = $this->with_safe_write(
			function () use ( $postarr, $is_code, $post_id ) {
				if ( ! $is_code ) {
					return $post_id > 0
						? wp_update_post( wp_slash( $postarr ), true )
						: wp_insert_post( wp_slash( $postarr ), true );
				}

				return $this->without_content_save_filters(
					function () use ( $postarr, $post_id ) {
						return $post_id > 0
							? wp_update_post( wp_slash( $postarr ), true )
							: wp_insert_post( wp_slash( $postarr ), true );
					}
				);
			}
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$saved_id = (int) $result;
		update_post_meta( $saved_id, '_edit_last', get_current_user_id() );
		clean_post_cache( $saved_id );

		return $saved_id;
	}

	/**
	 * Write one allowed meta value without touching anything else.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $field Writable field name.
	 * @param string $value Stored value.
	 */
	public function update_field( $post_id, $field, $value ) {
		$map = $this->writable_meta();
		if ( ! isset( $map[ $field ] ) ) {
			return;
		}
		update_post_meta( (int) $post_id, $map[ $field ], wp_slash( (string) $value ) );
	}

	/**
	 * Bump the post modified time after a meta-only change.
	 *
	 * @param int $post_id Post ID.
	 */
	public function touch( $post_id ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', true ),
			),
			array( 'ID' => (int) $post_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		clean_post_cache( (int) $post_id );
	}

	/**
	 * Rebuild the JS or CSS file Woody serves, using the same wrapping rules as its editor.
	 *
	 * @param int $post_id Snippet ID.
	 */
	public function regenerate_assets( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post || Capabilities::post_type() !== $post->post_type ) {
			return;
		}

		$this->ensure_upload_constants();

		$type     = $this->raw_meta( $post_id, self::META_TYPE );
		$filetype = $this->raw_meta( $post_id, self::META_FILETYPE );
		if ( '' === $filetype ) {
			$filetype = $type;
		}

		if ( ! in_array( $filetype, array( 'css', 'js' ), true ) ) {
			return;
		}

		if ( ! defined( 'WINP_UPLOAD_DIR' ) ) {
			return;
		}

		$dir = WINP_UPLOAD_DIR;
		if ( ! wp_mkdir_p( $dir ) ) {
			return;
		}

		$linking = $this->raw_meta( $post_id, self::META_LINKING );
		if ( '' === $linking ) {
			$linking = 'external';
		}

		$content = (string) $post->post_content;
		$before  = '';
		$after   = '';
		$label   = 'Woody Code Snippets';

		if ( 'inline' === $linking ) {
			$before = '<!-- start ' . $label . ' CSS and JS -->' . PHP_EOL;
			$after  = '<!-- end ' . $label . ' CSS and JS -->' . PHP_EOL;

			if ( 'css' === $filetype ) {
				$before .= '<style type="text/css">' . PHP_EOL;
				$after   = '</style>' . PHP_EOL . $after;
			}

			if ( 'js' === $filetype ) {
				if ( ! preg_match( '/<script\b[^>]*>([\s\S]*?)<\/script>/im', $content ) ) {
					$before .= '<script type="text/javascript">' . PHP_EOL;
					$after   = PHP_EOL . '</script>' . PHP_EOL . $after;
				} else {
					$stripped = preg_replace( '@/\*[\s\S]*?\*/@', '', $content );
					if ( is_string( $stripped ) ) {
						$content = $stripped;
					}
				}
			}
		}

		if ( 'external' === $linking ) {
			$before = '/******* Do not edit this file *******' . PHP_EOL
				. $label . ' CSS and JS' . PHP_EOL
				. 'Saved: ' . gmdate( 'M d Y | H:i:s' ) . ' */' . PHP_EOL;
			update_post_meta( (int) $post_id, self::META_VERSION, time() );
		}

		$slug = $this->raw_meta( $post_id, self::META_SLUG );
		if ( '' !== $slug ) {
			$file_name = $slug . '.' . $filetype;
			$file_slug = $slug;
		} else {
			$file_name = $post_id . '.' . $filetype;
			$file_slug = (string) $post_id;
		}

		$old_slug = $this->raw_meta( $post_id, self::META_EXIST_SLUG );
		if ( '' !== $old_slug && $old_slug !== $file_slug ) {
			$old_path = $dir . '/' . $old_slug . '.' . $filetype;
			if ( file_exists( $old_path ) ) {
				wp_delete_file( $old_path );
			}
		}

		update_post_meta( (int) $post_id, self::META_EXIST_SLUG, wp_slash( $file_slug ) );

		$written = file_put_contents( $dir . '/' . $file_name, $before . $content . $after );
		if ( false === $written ) {
			return;
		}

		if ( class_exists( '\WINP_Helper' ) && method_exists( '\WINP_Helper', 'flush_page_cache' ) ) {
			\WINP_Helper::flush_page_cache();
		}
	}

	/**
	 * Define Woody's upload path constants the same way its executor does.
	 */
	private function ensure_upload_constants() {
		$uploads = wp_upload_dir();
		if ( ! defined( 'WINP_UPLOAD_DIR' ) ) {
			define( 'WINP_UPLOAD_DIR', $uploads['basedir'] . '/winp-css-js' );
		}
		if ( ! defined( 'WINP_UPLOAD_URL' ) ) {
			define( 'WINP_UPLOAD_URL', $uploads['baseurl'] . '/winp-css-js' );
		}
	}

	/**
	 * Remove content_save_pre callbacks so code is stored as typed.
	 *
	 * @param callable $callback Save callback.
	 * @return mixed
	 */
	private function without_content_save_filters( callable $callback ) {
		global $wp_filter;

		$backup = isset( $wp_filter['content_save_pre'] ) ? $wp_filter['content_save_pre'] : null;
		if ( null !== $backup ) {
			unset( $wp_filter['content_save_pre'] );
		}

		try {
			return $callback();
		} finally {
			if ( null !== $backup ) {
				$wp_filter['content_save_pre'] = $backup;
			}
		}
	}

	/**
	 * Detach Woody callbacks that would deactivate a snippet or require its editor nonce.
	 *
	 * @return array<int,array{0:string,1:mixed,2:int,3:int}>
	 */
	private function suspend_woody_hooks() {
		global $wp_filter;

		$hooks   = array( 'save_post', 'save_post_' . Capabilities::post_type(), 'wp_insert_post' );
		$removed = array();

		foreach ( $hooks as $hook ) {
			if ( ! isset( $wp_filter[ $hook ] ) ) {
				continue;
			}

			foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					$function = $callback['function'];
					if ( ! $this->is_woody_callback( $function ) ) {
						continue;
					}
					$args = isset( $callback['accepted_args'] ) ? (int) $callback['accepted_args'] : 1;
					remove_filter( $hook, $function, (int) $priority );
					$removed[] = array( $hook, $function, (int) $priority, $args );
				}
			}
		}

		return $removed;
	}

	/**
	 * Restore hooks removed for a write.
	 *
	 * @param array<int,array{0:string,1:mixed,2:int,3:int}> $removed Removed callbacks.
	 */
	private function resume_hooks( array $removed ) {
		foreach ( $removed as $item ) {
			add_filter( $item[0], $item[1], $item[2], $item[3] );
		}
	}

	/**
	 * Whether a callback belongs to Woody.
	 *
	 * @param mixed $function Callback.
	 */
	private function is_woody_callback( $function ) {
		if ( is_array( $function ) && isset( $function[0] ) ) {
			$class = is_object( $function[0] ) ? get_class( $function[0] ) : (string) $function[0];
			return 0 === strpos( $class, 'WINP_' );
		}

		if ( is_string( $function ) ) {
			return 0 === strpos( $function, 'wbcr_inp_' ) || 0 === strpos( $function, 'winp_' );
		}

		return false;
	}
}
