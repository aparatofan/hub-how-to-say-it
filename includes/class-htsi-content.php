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
