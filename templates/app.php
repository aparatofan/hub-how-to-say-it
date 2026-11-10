<?php
/**
 * Full-screen HUB page. The theme is left out on purpose: no header, no
 * footer, no theme styles — only the HUB.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
nocache_headers();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0859C6">
<title>How to Say It in English — HUB</title>
<link rel="preload" href="<?php echo esc_url( HTSI_URL . 'assets/fonts/roboto-flex-latin-wght-normal.woff2' ); ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?php echo esc_url( HTSI_URL . 'assets/fonts/roboto-slab-latin-wght-normal.woff2' ); ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?php echo esc_url( HTSI_URL . 'assets/hub.css?ver=' . HTSI_VERSION ); ?>">
</head>
<body class="htsi-body">
<div id="htsi-app"><noscript>Please turn on JavaScript to use the HUB.</noscript></div>
<script>window.HTSI_CONFIG = <?php echo wp_json_encode( HTSI_Frontend::config() ); ?>;</script>
<script src="<?php echo esc_url( HTSI_URL . 'assets/hub.js?ver=' . HTSI_VERSION ); ?>"></script>
</body>
</html>
