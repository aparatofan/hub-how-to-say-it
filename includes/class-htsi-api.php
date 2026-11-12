<?php
/**
 * REST API used by the HUB front end (namespace htsi/v1).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HTSI_Api {

	const NS = 'htsi/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'no_cache' ), 10, 3 );
	}

	public static function routes() {
		$post = array( __CLASS__, 'check_post' );
		register_rest_route( self::NS, '/state', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'state' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NS, '/chapter', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'chapter' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NS, '/snake', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'snake' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NS, '/login', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'login' ), 'permission_callback' => $post ) );
		register_rest_route( self::NS, '/unlock', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'unlock' ), 'permission_callback' => $post ) );
		register_rest_route( self::NS, '/logout', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'logout' ), 'permission_callback' => $post ) );
		register_rest_route( self::NS, '/news', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'news' ), 'permission_callback' => $post ) );
		register_rest_route( self::NS, '/send-map', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'send_map' ), 'permission_callback' => $post ) );
	}

	/**
	 * Every write must carry the X-HTSI header. A browser cannot add a custom
	 * header to a cross-site form post, so this blocks forged requests.
	 */
	public static function check_post( WP_REST_Request $req ) {
		if ( '1' !== $req->get_header( 'x_htsi' ) ) {
			return new WP_Error( 'htsi_forbidden', 'Request not allowed.', array( 'status' => 403 ) );
		}
		return true;
	}

	public static function no_cache( $response, $server, $request ) {
		if ( 0 === strpos( $request->get_route(), '/' . self::NS ) && $response instanceof WP_REST_Response ) {
			$response->header( 'Cache-Control', 'no-store, private' );
		}
		return $response;
	}

	private static function reader_payload( $reader ) {
		if ( ! $reader ) {
			return null;
		}
		return array(
			'name'    => $reader->name,
			'email'   => $reader->email,
			'news'    => (bool) $reader->news_consent,
			'volumes' => HTSI_Auth::reader_volumes( $reader->id ),
		);
	}

	private static function full_state() {
		return array(
			'reader'  => self::reader_payload( HTSI_Auth::current_reader() ),
			'volumes' => HTSI_Content::outline(),
		);
	}

	public static function state() {
		return rest_ensure_response( self::full_state() );
	}

	public static function login( WP_REST_Request $req ) {
		if ( HTSI_Auth::too_many( 'login', 10 ) ) {
			return new WP_Error( 'htsi_slow_down', 'Too many tries. Please wait 15 minutes and try again.', array( 'status' => 429 ) );
		}
		$email = sanitize_email( (string) $req->get_param( 'email' ) );
		$code  = (string) $req->get_param( 'code' );
		$name  = mb_substr( sanitize_text_field( (string) $req->get_param( 'name' ) ), 0, 60 );
		$news  = (bool) $req->get_param( 'news' );

		if ( '' === trim( $code ) ) {
			return new WP_Error( 'htsi_no_code', 'Please type the code from your book.', array( 'status' => 400 ) );
		}
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'htsi_bad_email', 'Please check your email address.', array( 'status' => 400 ) );
		}
		$email  = strtolower( $email );
		$reader = HTSI_Auth::claim( $code, null, $email );
		if ( is_wp_error( $reader ) ) {
			HTSI_Auth::count_hit( 'login', 15 * MINUTE_IN_SECONDS );
			return $reader;
		}
		// The news box can switch news on here. Switching it off happens inside the HUB.
		HTSI_Auth::update_reader( $reader->id, $name, $news ? true : null );
		HTSI_Auth::start_session( $reader->id );
		return rest_ensure_response( self::full_state() );
	}

	public static function unlock( WP_REST_Request $req ) {
		$reader = HTSI_Auth::current_reader();
		if ( ! $reader ) {
			return new WP_Error( 'htsi_signed_out', 'Please sign in again.', array( 'status' => 401 ) );
		}
		if ( HTSI_Auth::too_many( 'login', 10 ) ) {
			return new WP_Error( 'htsi_slow_down', 'Too many tries. Please wait 15 minutes and try again.', array( 'status' => 429 ) );
		}
		$result = HTSI_Auth::claim( (string) $req->get_param( 'code' ), $reader );
		if ( is_wp_error( $result ) ) {
			HTSI_Auth::count_hit( 'login', 15 * MINUTE_IN_SECONDS );
			return $result;
		}
		return rest_ensure_response( self::full_state() );
	}

	public static function logout() {
		HTSI_Auth::end_session();
		return rest_ensure_response( self::full_state() );
	}

	public static function news( WP_REST_Request $req ) {
		$reader = HTSI_Auth::current_reader();
		if ( ! $reader ) {
			return new WP_Error( 'htsi_signed_out', 'Please sign in again.', array( 'status' => 401 ) );
		}
		HTSI_Auth::update_reader( $reader->id, '', (bool) $req->get_param( 'on' ) );
		$fresh = HTSI_Auth::reader_by_email( $reader->email );
		return rest_ensure_response( array( 'reader' => self::reader_payload( $fresh ) ) );
	}

	/** Check the reader may open this chapter. Returns [reader, chapter] or WP_Error. */
	private static function open_chapter( WP_REST_Request $req ) {
		$reader = HTSI_Auth::current_reader();
		if ( ! $reader ) {
			return new WP_Error( 'htsi_signed_out', 'Please sign in again.', array( 'status' => 401 ) );
		}
		$v  = (int) $req->get_param( 'v' );
		$c  = (int) $req->get_param( 'c' );
		$ch = HTSI_Content::chapter( $v, $c );
		if ( ! $ch ) {
			return new WP_Error( 'htsi_not_found', 'We cannot find this chapter.', array( 'status' => 404 ) );
		}
		if ( ! in_array( $v, HTSI_Auth::reader_volumes( $reader->id ), true ) ) {
			return new WP_Error( 'htsi_locked', 'This volume is locked. Enter the code from your book to open it.', array( 'status' => 403 ) );
		}
		return array( $reader, $ch, $v, $c );
	}

	public static function chapter( WP_REST_Request $req ) {
		$open = self::open_chapter( $req );
		if ( is_wp_error( $open ) ) {
			return $open;
		}
		list( , $ch, $v, $c ) = $open;
		return rest_ensure_response(
			array(
				'v'         => $v,
				'c'         => $c,
				'title'     => $ch['title'],
				'map_url'   => $ch['map_url'],
				'branches'  => array_values( $ch['branches'] ),
				'exercises' => array_values( $ch['exercises'] ),
				'pictures'  => HTSI_Content::pictures( $v, $c ),
			)
		);
	}

	/** Revision game uses the same reader and volume checks as chapter content. */
	public static function snake( WP_REST_Request $req ) {
		$open = self::open_chapter( $req );
		if ( is_wp_error( $open ) ) {
			return $open;
		}
		list( , , $v, $c ) = $open;
		if ( 1 !== $v || ! in_array( $c, array( 3, 6 ), true ) ) {
			return new WP_Error( 'htsi_not_found', 'We cannot find this revision game.', array( 'status' => 404 ) );
		}
		$file = 6 === $c ? 'data/v1c4-6-snake.php' : 'data/v1c1-3-snake.php';
		return rest_ensure_response( array( 'items' => require HTSI_DIR . $file ) );
	}

	public static function send_map( WP_REST_Request $req ) {
		$open = self::open_chapter( $req );
		if ( is_wp_error( $open ) ) {
			return $open;
		}
		list( $reader, $ch, $v, $c ) = $open;
		if ( '' === $ch['map_url'] ) {
			return new WP_Error( 'htsi_no_map', 'This mind map is not ready yet.', array( 'status' => 404 ) );
		}
		$key  = 'htsi_mail_' . (int) $reader->id;
		$sent = (int) get_transient( $key );
		if ( $sent >= 5 ) {
			return new WP_Error( 'htsi_slow_down', 'You have asked for a lot of emails. Please try again in an hour.', array( 'status' => 429 ) );
		}
		set_transient( $key, $sent + 1, HOUR_IN_SECONDS );

		$hello   = '' !== $reader->name ? 'Hello ' . $reader->name . ',' : 'Hello,';
		$subject = 'Your mind map: ' . $ch['title'];
		$body    = $hello . "\n\n"
			. 'Here is the link to your mind map "' . $ch['title'] . '" (How to Say It in English, Volume ' . $v . ', Chapter ' . $c . "):\n\n"
			. $ch['map_url'] . "\n\n"
			. "The map works best on a computer or a tablet.\n\n"
			. 'The Blue Tree' . "\n" . home_url( '/' ) . "\n";
		$ok      = wp_mail( $reader->email, $subject, $body );
		if ( ! $ok ) {
			return new WP_Error( 'htsi_mail', 'We could not send the email. Please try again later.', array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'sent_to' => $reader->email ) );
	}
}


