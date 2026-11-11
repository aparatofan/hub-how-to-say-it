<?php
/**
 * Chapter content: mind-map link, Fill games per branch, exercises.
 * Saved content lives in the "htsi_content" option and is laid over the
 * seed in data/seed.php, so a fresh install already shows Volume 1.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HTSI_Content {

	private static $cache = null;

	public static function volume_names() {
		return array(
			1 => 'Everyday Interaction',
			2 => 'Social Situations',
			3 => 'Expressing Yourself',
		);
	}

	/** Full content tree: [volume][chapter] => chapter array. */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$seed  = require HTSI_DIR . 'data/seed.php';
		$saved = get_option( 'htsi_content', array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		$out = array();
		for ( $v = 1; $v <= 3; $v++ ) {
			for ( $c = 1; $c <= 10; $c++ ) {
				$base = isset( $seed[ $v ][ $c ] ) ? $seed[ $v ][ $c ] : array();
				$mine = isset( $saved[ $v ][ $c ] ) && is_array( $saved[ $v ][ $c ] ) ? $saved[ $v ][ $c ] : array();
				$ch   = array_merge(
					array(
						'title'     => '',
						'map_url'   => '',
						'branches'  => array(),
						'exercises' => array(),
					),
					$base,
					$mine
				);
				$out[ $v ][ $c ] = $ch;
			}
		}
		self::$cache = $out;
		return $out;
	}

	public static function chapter( $v, $c ) {
		$all = self::all();
		return isset( $all[ $v ][ $c ] ) ? $all[ $v ][ $c ] : null;
	}

	public static function save_chapter( $v, $c, array $data ) {
		$saved = get_option( 'htsi_content', array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		$saved[ $v ][ $c ] = $data;
		update_option( 'htsi_content', $saved, false );
		self::$cache = null;
	}

	/** Public outline: titles and what is ready. No links, no exercises. */
	public static function outline() {
		$all   = self::all();
		$names = self::volume_names();
		$out   = array();
		foreach ( $all as $v => $chapters ) {
			$list = array();
			foreach ( $chapters as $c => $ch ) {
				$games = 0;
				foreach ( $ch['branches'] as $b ) {
					if ( ! empty( $b['fill_url'] ) ) {
						$games++;
					}
				}
				$list[] = array(
					'n'         => $c,
					'title'     => $ch['title'],
					'map'       => '' !== $ch['map_url'],
					'games'     => $games,
					'exercises' => ! empty( $ch['exercises'] ),
				);
			}
			$out[] = array(
				'n'        => $v,
				'name'     => $names[ $v ],
				'chapters' => $list,
			);
		}
		return $out;
	}

	/*
	 * ----- Chapter illustrations (docs/chapter-illustrations.md) -----
	 * Pictures live in the Media Library and are found by file name:
	 * v{volume}ch{chapter}b{branch}.jpeg/.jpg/.png/.webp. Only the
	 * branch => attachment ID map is cached; captions and alt text are read
	 * fresh, so editing a branch title or an Alt Text shows at once.
	 */

	const PICS_PATTERN = '#(^|/)v(\d+)ch(\d+)b(\d+)(-\d+)?(-scaled)?\.(jpe?g|png|webp)$#i';

	/** Branch number => attachment ID for one chapter, in branch order. */
	public static function picture_ids( $v, $c ) {
		$v   = (int) $v;
		$c   = (int) $c;
		$key = 'htsi_pics_' . $v . '_' . $c;
		$ids = get_transient( $key );
		if ( is_array( $ids ) ) {
			return $ids;
		}

		global $wpdb;
		// Newest upload first, so a re-uploaded (corrected) picture wins.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, pm.meta_value AS file
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attached_file'
				WHERE p.post_type = 'attachment' AND p.post_status <> 'trash' AND pm.meta_value LIKE %s
				ORDER BY p.post_date DESC, p.ID DESC",
				'%' . $wpdb->esc_like( 'v' . $v . 'ch' . $c . 'b' ) . '%'
			)
		);

		$ids = array();
		foreach ( (array) $rows as $row ) {
			if ( ! preg_match( self::PICS_PATTERN, (string) $row->file, $m ) ) {
				continue;
			}
			$n = (int) $m[4];
			if ( (int) $m[2] !== $v || (int) $m[3] !== $c || $n < 1 || isset( $ids[ $n ] ) ) {
				continue;
			}
			$ids[ $n ] = (int) $row->ID;
		}
		ksort( $ids );
		set_transient( $key, $ids, 12 * HOUR_IN_SECONDS );
		return $ids;
	}

	/** Picture list for the /chapter response. Branches without a picture are left out. */
	public static function pictures( $v, $c ) {
		$ch       = self::chapter( $v, $c );
		$branches = $ch ? array_values( $ch['branches'] ) : array();
		$out      = array();
		foreach ( self::picture_ids( $v, $c ) as $n => $id ) {
			$src = wp_get_attachment_image_src( $id, 'large' );
			if ( ! $src ) {
				continue;
			}
			$title = isset( $branches[ $n - 1 ]['title'] ) ? (string) $branches[ $n - 1 ]['title'] : '';
			$alt   = trim( wp_strip_all_tags( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) );
			if ( '' === $alt ) {
				$alt = '' !== $title ? 'Illustration: ' . $title : 'Illustration';
			}
			$srcset = wp_get_attachment_image_srcset( $id, 'large' );
			$out[]  = array(
				'n'       => (int) $n,
				'src'     => $src[0],
				'srcset'  => $srcset ? $srcset : '',
				'width'   => (int) $src[1],
				'height'  => (int) $src[2],
				'alt'     => $alt,
				'caption' => $title,
			);
		}
		return $out;
	}

	/**
	 * Forget the cached picture lists when the Media Library changes.
	 * Deleted one by one (30 chapters) so it also works with an object cache.
	 */
	public static function flush_pictures() {
		foreach ( array_keys( self::volume_names() ) as $v ) {
			for ( $c = 1; $c <= 10; $c++ ) {
				delete_transient( 'htsi_pics_' . $v . '_' . $c );
			}
		}
	}

	public static function settings() {
		$s = get_option( 'htsi_settings', array() );
		return wp_parse_args(
			is_array( $s ) ? $s : array(),
			array(
				'page_id'    => 0,
				'fullscreen' => 1,
				'shop_1'     => '',
				'shop_2'     => '',
				'shop_3'     => '',
			)
		);
	}

	/** Address of the HUB page, or '' when no page is chosen yet. */
	public static function hub_url() {
		$s = self::settings();
		if ( $s['page_id'] ) {
			$url = get_permalink( (int) $s['page_id'] );
			if ( $url ) {
				return $url;
			}
		}
		return '';
	}
}
