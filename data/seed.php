<?php
/**
 * Starting content. Anything saved on the Chapters admin page replaces
 * the matching chapter here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$htsi_titles = array(
	1 => array(
		'Opening and closing conversations',
		'Introducing yourself and other people',
		'Asking for information',
		'Asking for clarification and checking understanding',
		'Making requests and asking for help',
		'Offering help and offering things',
		'Asking for and giving permission',
		'Thanking people and responding to thanks',
		'Apologizing and accepting apologies',
		'Keeping a conversation going',
	),
	2 => array(
		'Talking about weather',
		'Inviting people and responding to invitations',
		'Making arrangements and plans',
		'Making suggestions and giving advice',
		'Congratulating, wishing luck and good wishes',
		'Expressing sympathy and concern',
		'Complaining and dealing with problems',
		'Warning people',
		'Likes, dislikes and preferences',
		'Encouraging and reassuring people',
	),
	3 => array(
		'Expressing opinions 1',
		'Expressing opinions 2',
		'Expressing certainty and uncertainty',
		'Speculating and talking about likelihood',
		'Saying you do not know',
		'Giving reasons and explaining',
		'Comparing things and people',
		'Talking about changes',
		'Talking about regrets and wishes',
		'Expressing feelings and emotions',
	),
);

$htsi_maps_v1 = array(
	'e1df2e5a6e1741d0bf8bfe22701b66a8',
	'5ef256aa0a4140b7ae63b1ec3dc67a1f',
	'74ca80bee07e4e10ad8d901b64caec3d',
	'627a0e528338453e86d75a8ef4d14858',
	'9df8ef3e27a3489db30e67dd290b156a',
	'4e951437344044b38199f029e6f76abb',
	'52dcf0a4eff84518b9fe98fe59b44d23',
	'ea4352b001604cc5b60c88529578c1b5',
	'682c55a61366447e90902b77267d218e',
	'0b5244e4e54c4c8aac35e9a576f3298b',
);

$htsi_seed = array();
foreach ( $htsi_titles as $htsi_v => $htsi_list ) {
	foreach ( $htsi_list as $htsi_i => $htsi_title ) {
		$htsi_seed[ $htsi_v ][ $htsi_i + 1 ] = array(
			'title'   => $htsi_title,
			'map_url' => 1 === $htsi_v ? 'https://www.mindomo.com/mindmap/' . $htsi_maps_v1[ $htsi_i ] : '',
		);
	}
}

// Volume 1, Chapter 1 — branches in map order, with the Fill games that exist so far.
$htsi_fill = 'https://thebluetree.pl/tbt-fill-students/?fill=';
$htsi_seed[1][1]['branches'] = array(
	array( 'title' => 'Greeting people you know', 'fill_url' => '' ),
	array( 'title' => 'Responding to greetings', 'fill_url' => $htsi_fill . 'hyu2qs4xfsk2' ),
	array( 'title' => 'Starting with a stranger', 'fill_url' => $htsi_fill . 'byxuky556k4d' ),
	array( 'title' => 'Small talk questions', 'fill_url' => '' ),
	array( 'title' => 'Signalling you have to go', 'fill_url' => $htsi_fill . 'h4zbc2hxugcq' ),
	array( 'title' => 'Closing the conversation', 'fill_url' => '' ),
	array( 'title' => 'Saying goodbye', 'fill_url' => '' ),
);

$htsi_json = file_get_contents( HTSI_DIR . 'data/v1c1-exercises.json' );
$htsi_ex   = $htsi_json ? json_decode( $htsi_json, true ) : null;
$htsi_seed[1][1]['exercises'] = is_array( $htsi_ex ) ? $htsi_ex : array();

return $htsi_seed;
