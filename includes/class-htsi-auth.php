<?php
/**
 * Sign-in with book code + email, sessions, code generation.
 *
 * Rules:
 *  - a code belongs to one volume and never expires;
 *  - the first email used with a code owns it; no other email can use it;
 *  - the same code + email signs the reader in again on any device.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HTSI_Auth {

	const COOKIE   = 'htsi_session';
	const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O, 1/I

	private static $reader = false;

	/* ---------- codes ---------- */

	public static function normalize_code( $raw ) {
		$code = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $raw ) );
		// Codes never contain 0, O, 1 or I after the "HTS1" prefix, so nothing can be misread.
		if ( 12 === strlen( $code ) ) {
			$code = substr( $code, 0, 4 ) . '-' . substr( $code, 4, 4 ) . '-' . substr( $code, 8, 4 );
		}
		return $code;
	}

	private static function random_block() {
		$out = '';
		$max = strlen( self::ALPHABET ) - 1;
		for ( $i = 0; $i < 4; $i++ ) {
			$out .= self::ALPHABET[ random_int( 0, $max ) ];
		}
		return $out;
	}

	/** Create $count new codes for a volume. Returns the list of codes. */
	public static function generate_codes( $volume, $count, $batch = '' ) {
		global $wpdb;
		$volume = max( 1, min( 3, (int) $volume ) );
		$count  = max( 1, min( 500, (int) $count ) );
		$made   = array();
		$tries  = 0;
		while ( count( $made ) < $count && $tries < $count * 5 ) {
			$tries++;
			$code = 'HTS' . $volume . '-' . self::random_block() . '-' . self::random_block();
			$ok   = $wpdb->query(
				$wpdb->prepare(
					'INSERT IGNORE INTO ' . HTSI_DB::codes() . ' (code, volume, batch, created_at) VALUES (%s, %d, %s, %s)',
					$code,
					$volume,
					$batch,
					HTSI_DB::now()
				)
			);
			if ( $ok ) {
				$made[] = $code;
			}
		}
		return $made;
	}

	/* ---------- readers ---------- */

	public static function reader_by_email( $email ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HTSI_DB::readers() . ' WHERE email = %s', $email ) );
	}

	public static function reader_volumes( $reader_id ) {
		global $wpdb;
		$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT volume FROM ' . HTSI_DB::codes() . ' WHERE reader_id = %d ORDER BY volume', $reader_id ) );
		return array_map( 'intval', $rows );
	}

	/**
	 * Attach a code to a reader. $reader may be null: then $email is used
	 * and the reader is created when the code is valid.
	 *
	 * @return object|WP_Error reader row
	 */
	public static function claim( $raw_code, $reader, $email = '' ) {
		global $wpdb;
		$code = self::normalize_code( $raw_code );
		$row  = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HTSI_DB::codes() . ' WHERE code = %s', $code ) );
		if ( ! $row ) {
			return new WP_Error( 'htsi_bad_code', 'We do not know this code. Please check it and try again.', array( 'status' => 400 ) );
		}
		if ( ! $reader && $email ) {
			$reader = self::reader_by_email( $email );
		}
		if ( $row->reader_id ) {
			if ( $reader && (int) $reader->id === (int) $row->reader_id ) {
				return $reader;
			}
			return new WP_Error( 'htsi_code_taken', 'This code is already used with a different email address.', array( 'status' => 403 ) );
		}
		if ( ! $reader ) {
			$wpdb->insert(
				HTSI_DB::readers(),
				array(
					'email'      => $email,
					'created_at' => HTSI_DB::now(),
				)
			);
			$reader = self::reader_by_email( $email );
			if ( ! $reader ) {
				return new WP_Error( 'htsi_error', 'Something went wrong. Please try again.', array( 'status' => 500 ) );
			}
		}
		// Only one reader can win the code, even if two people send it at the same moment.
		$won = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . HTSI_DB::codes() . ' SET reader_id = %d, claimed_at = %s WHERE id = %d AND reader_id IS NULL',
				$reader->id,
				HTSI_DB::now(),
				$row->id
			)
		);
		if ( ! $won ) {
			return new WP_Error( 'htsi_code_taken', 'This code is already used with a different email address.', array( 'status' => 403 ) );
		}
		return $reader;
	}

	public static function update_reader( $reader_id, $name, $news ) {
		global $wpdb;
		$data = array( 'last_seen' => HTSI_DB::now() );
		if ( '' !== $name ) {
			$data['name'] = $name;
		}
		if ( null !== $news ) {
			$data['news_consent'] = $news ? 1 : 0;
			$data['consent_at']   = $news ? HTSI_DB::now() : null;
		}
		$wpdb->update( HTSI_DB::readers(), $data, array( 'id' => $reader_id ) );
	}

	/* ---------- sessions ---------- */

	public static function start_session( $reader_id ) {
		global $wpdb;
		$token = bin2hex( random_bytes( 32 ) );
		$wpdb->insert(
			HTSI_DB::sessions(),
			array(
				'reader_id'  => $reader_id,
				'token_hash' => hash( 'sha256', $token ),
				'created_at' => HTSI_DB::now(),
				'last_used'  => HTSI_DB::now(),
			)
		);
		self::set_cookie( $token, time() + YEAR_IN_SECONDS );
		self::$reader = false;
		$_COOKIE[ self::COOKIE ] = $token;
	}

	public static function end_session() {
		global $wpdb;
		if ( ! empty( $_COOKIE[ self::COOKIE ] ) ) {
			$wpdb->delete( HTSI_DB::sessions(), array( 'token_hash' => hash( 'sha256', (string) $_COOKIE[ self::COOKIE ] ) ) );
		}
		self::set_cookie( '', time() - DAY_IN_SECONDS );
		unset( $_COOKIE[ self::COOKIE ] );
		self::$reader = null;
	}

	private static function set_cookie( $value, $expires ) {
		if ( headers_sent() ) {
			return;
		}
		setcookie(
			self::COOKIE,
			$value,
			array(
				'expires'  => $expires,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/** The signed-in reader row, or null. */
	public static function current_reader() {
		global $wpdb;
		if ( false !== self::$reader ) {
			return self::$reader;
		}
		self::$reader = null;
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return null;
		}
		$token = (string) $_COOKIE[ self::COOKIE ];
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return null;
		}
		$reader = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT r.* FROM ' . HTSI_DB::sessions() . ' s JOIN ' . HTSI_DB::readers() . ' r ON r.id = s.reader_id WHERE s.token_hash = %s',
				hash( 'sha256', $token )
			)
		);
		if ( $reader ) {
			self::$reader = $reader;
		}
		return self::$reader;
	}

	/* ---------- rate limit ---------- */

	private static function rl_key( $bucket ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'x';
		return 'htsi_rl_' . md5( $bucket . '|' . $ip );
	}

	public static function too_many( $bucket, $limit ) {
		return (int) get_transient( self::rl_key( $bucket ) ) >= $limit;
	}

	public static function count_hit( $bucket, $window ) {
		$key = self::rl_key( $bucket );
		set_transient( $key, (int) get_transient( $key ) + 1, $window );
	}
}
