<?php
/**
 * Plugin Name:       HUB — How to Say It in English
 * Description:       Mobile-first reader hub for the "How to Say It in English" mind-map books: mind maps, TBT Fill games, revision exercises and keys, unlocked with a book code.
 * Version:           0.3.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            The Blue Tree
 * License:           GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HTSI_VERSION', '0.3.1' );
define( 'HTSI_FILE', __FILE__ );
define( 'HTSI_DIR', plugin_dir_path( __FILE__ ) );
define( 'HTSI_URL', plugin_dir_url( __FILE__ ) );

require_once HTSI_DIR . 'includes/class-htsi-db.php';
require_once HTSI_DIR . 'includes/class-htsi-content.php';
require_once HTSI_DIR . 'includes/class-htsi-auth.php';
require_once HTSI_DIR . 'includes/class-htsi-api.php';
require_once HTSI_DIR . 'includes/class-htsi-frontend.php';
require_once HTSI_DIR . 'includes/class-htsi-admin.php';

register_activation_hook( __FILE__, array( 'HTSI_DB', 'install' ) );

add_action(
	'plugins_loaded',
	function () {
		HTSI_DB::maybe_upgrade();
		HTSI_Api::init();
		HTSI_Frontend::init();
		if ( is_admin() ) {
			HTSI_Admin::init();
		}
	}
);
