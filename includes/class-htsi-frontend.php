<?php
/**
 * The reader-facing HUB: [htsi_hub] shortcode and the full-screen template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HTSI_Frontend {

	public static function init() {
		add_shortcode( 'htsi_hub', array( __CLASS__, 'shortcode' ) );
		add_filter( 'template_include', array( __CLASS__, 'template' ), 99 );
	}

	public static function config() {
		$s = HTSI_Content::settings();
		return array(
			'api'        => esc_url_raw( rest_url( HTSI_Api::NS ) ),
			'logoWhite'  => HTSI_URL . 'assets/img/tbt-logo-white.svg',
			'logoColour' => HTSI_URL . 'assets/img/tbt-logo-colour.svg',
			'privacy'    => function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '',
			'shop'       => array(
				1 => esc_url_raw( $s['shop_1'] ),
				2 => esc_url_raw( $s['shop_2'] ),
				3 => esc_url_raw( $s['shop_3'] ),
			),
		);
	}

	public static function shortcode() {
		wp_enqueue_style( 'htsi-hub', HTSI_URL . 'assets/hub.css', array(), HTSI_VERSION );
		wp_enqueue_script( 'htsi-hub', HTSI_URL . 'assets/hub.js', array(), HTSI_VERSION, true );
		wp_add_inline_script( 'htsi-hub', 'window.HTSI_CONFIG = ' . wp_json_encode( self::config() ) . ';', 'before' );
		return '<div id="htsi-app" class="htsi-embedded"><noscript>Please turn on JavaScript to use the HUB.</noscript></div>';
	}

	/** Serve the HUB page without the theme, so it feels like an app on a phone. */
	public static function template( $template ) {
		$s = HTSI_Content::settings();
		if ( ! $s['fullscreen'] || ! is_singular() ) {
			return $template;
		}
		$post = get_queried_object();
		if ( $post instanceof WP_Post && has_shortcode( (string) $post->post_content, 'htsi_hub' ) ) {
			return HTSI_DIR . 'templates/app.php';
		}
		return $template;
	}
}
