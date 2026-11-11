<?php
/**
 * Admin screens: Codes, Readers, Chapters, Settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HTSI_Admin {

	const CAP  = 'manage_options';
	const SLUG = 'htsi-hub';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		foreach ( array( 'generate', 'export_codes', 'code_action', 'export_readers', 'delete_reader', 'save_chapter', 'save_settings' ) as $a ) {
			add_action( 'admin_post_htsi_' . $a, array( __CLASS__, 'do_' . $a ) );
		}
		add_action( 'wp_ajax_htsi_code_given', array( __CLASS__, 'ajax_code_given' ) );
	}

	public static function menu() {
		add_menu_page( 'How to Say It HUB', 'HTSI HUB', self::CAP, self::SLUG, array( __CLASS__, 'page' ), 'dashicons-book-alt', 58 );
	}

	private static function url( $tab, $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG, 'tab' => $tab ), $args ), admin_url( 'admin.php' ) );
	}

	private static function guard( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'You are not allowed to do this.' );
		}
		check_admin_referer( 'htsi_' . $action );
	}

	private static function back( $tab, $args = array() ) {
		wp_safe_redirect( self::url( $tab, $args ) );
		exit;
	}

	/* ---------- page shell ---------- */

	public static function page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'codes';
		$tabs = array(
			'codes'    => 'Codes',
			'readers'  => 'Readers',
			'chapters' => 'Chapters',
			'settings' => 'Settings',
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'codes';
		}
		echo '<div class="wrap"><h1>How to Say It — HUB</h1><h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $label ) {
			printf( '<a class="nav-tab %s" href="%s">%s</a>', $key === $tab ? 'nav-tab-active' : '', esc_url( self::url( $key ) ), esc_html( $label ) );
		}
		echo '</h2>';
		if ( ! empty( $_GET['msg'] ) ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( sanitize_text_field( wp_unslash( $_GET['msg'] ) ) ) );
		}
		if ( ! empty( $_GET['err'] ) ) {
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( sanitize_text_field( wp_unslash( $_GET['err'] ) ) ) );
		}
		call_user_func( array( __CLASS__, 'tab_' . $tab ) );
		echo '</div>';
	}

	/* ---------- codes ---------- */

	private static function tab_codes() {
		global $wpdb;
		$fresh = get_transient( 'htsi_fresh_codes_' . get_current_user_id() );
		if ( $fresh ) {
			delete_transient( 'htsi_fresh_codes_' . get_current_user_id() );
			echo '<h2>New codes</h2><p>Copy these now. You can also find them in the list below.</p>';
			printf( '<textarea readonly rows="%d" cols="24" style="font-family:monospace;font-size:14px" onclick="this.select()">%s</textarea>', min( 20, count( $fresh ) + 1 ), esc_textarea( implode( "\n", $fresh ) ) );
		}

		echo '<h2>Generate codes</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'htsi_generate' );
		echo '<input type="hidden" name="action" value="htsi_generate">';
		echo '<table class="form-table"><tr><th><label for="htsi-vol">Volume</label></th><td><select id="htsi-vol" name="volume">';
		foreach ( HTSI_Content::volume_names() as $n => $name ) {
			printf( '<option value="%d">Volume %d — %s</option>', $n, $n, esc_html( $name ) );
		}
		echo '</select></td></tr>';
		echo '<tr><th><label for="htsi-count">How many</label></th><td><input id="htsi-count" type="number" name="count" min="1" max="500" value="20" class="small-text"> <span class="description">1–500 at a time</span></td></tr>';
		echo '<tr><th><label for="htsi-batch">Note (optional)</label></th><td><input id="htsi-batch" type="text" name="batch" maxlength="60" class="regular-text" placeholder="e.g. First print run"></td></tr></table>';
		submit_button( 'Generate codes' );
		echo '</form>';

		$vol    = isset( $_GET['vol'] ) ? (int) $_GET['vol'] : 0;
		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$paged  = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
		$per    = 100;
		$where  = 'WHERE 1=1';
		if ( $vol ) {
			$where .= $wpdb->prepare( ' AND c.volume = %d', $vol );
		}
		$where .= self::status_where( $status );
		$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . HTSI_DB::codes() . " c $where" );
		$rows  = $wpdb->get_results(
			'SELECT c.*, r.email FROM ' . HTSI_DB::codes() . ' c LEFT JOIN ' . HTSI_DB::readers() . " r ON r.id = c.reader_id $where ORDER BY c.id DESC " . $wpdb->prepare( 'LIMIT %d OFFSET %d', $per, ( $paged - 1 ) * $per )
		);

		echo '<h2>All codes</h2>';
		echo '<form method="get" style="margin-bottom:8px"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '"><input type="hidden" name="tab" value="codes">';
		echo '<select name="vol"><option value="0">All volumes</option>';
		for ( $n = 1; $n <= 3; $n++ ) {
			printf( '<option value="%d" %s>Volume %d</option>', $n, selected( $vol, $n, false ), $n );
		}
		echo '</select> <select name="status">';
		foreach ( array( '' => 'Used and free', 'free' => 'Free', 'used' => 'Used', 'spare' => 'Not given out yet', 'given' => 'Given out, not used yet' ) as $k => $label ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $status, $k, false ), esc_html( $label ) );
		}
		echo '</select> ';
		submit_button( 'Filter', 'secondary', '', false );
		printf( ' <a class="button" href="%s">Export CSV</a>', esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=htsi_export_codes&vol=' . $vol . '&status=' . $status ), 'htsi_export_codes' ) ) );
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr><th>Code</th><th>Given out</th><th>Volume</th><th>Status</th><th>Reader</th><th>Used on</th><th>Note</th><th>Created</th><th></th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="9">No codes here.</td></tr>';
		}
		foreach ( $rows as $r ) {
			$act = '';
			if ( $r->reader_id ) {
				$act = sprintf(
					'<a href="%s" onclick="return confirm(\'Free this code? The reader loses this volume and the code can be used with a new email.\')">Free code</a>',
					esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=htsi_code_action&do=release&id=' . (int) $r->id ), 'htsi_code_action' ) )
				);
			} else {
				$act = sprintf(
					'<a href="%s" style="color:#b32d2e" onclick="return confirm(\'Delete this unused code?\')">Delete</a>',
					esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=htsi_code_action&do=delete&id=' . (int) $r->id ), 'htsi_code_action' ) )
				);
			}
			if ( $r->reader_id ) {
				$tick = '<input type="checkbox" checked disabled title="A reader has used this code.">';
			} else {
				$tick = sprintf(
					'<input type="checkbox" class="htsi-given" data-id="%d" aria-label="Given out: %s" %s>',
					(int) $r->id,
					esc_attr( $r->code ),
					checked( ! empty( $r->given_at ), true, false )
				);
			}
			printf(
				'<tr><td><code>%s</code></td><td>%s</td><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_html( $r->code ),
				$tick, // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts above.
				(int) $r->volume,
				$r->reader_id ? 'Used' : 'Free',
				esc_html( (string) $r->email ),
				esc_html( (string) $r->claimed_at ),
				esc_html( $r->batch ),
				esc_html( $r->created_at ),
				$act // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts above.
			);
		}
		echo '</tbody></table>';
		echo '<p class="description">Tick <strong>Given out</strong> when you put a code in a book or send it to someone. The tick is saved at once. Used codes are ticked automatically.</p>';
		self::pager( $total, $per, $paged, self::url( 'codes', array( 'vol' => $vol, 'status' => $status ) ) );
		?>
		<script>
		( function () {
			var nonce = <?php echo wp_json_encode( wp_create_nonce( 'htsi_code_given' ) ); ?>;
			document.addEventListener( 'change', function ( e ) {
				var box = e.target;
				if ( ! box.classList || ! box.classList.contains( 'htsi-given' ) ) {
					return;
				}
				var want = box.checked;
				var body = new URLSearchParams( { action: 'htsi_code_given', _wpnonce: nonce, id: box.dataset.id, given: want ? '1' : '0' } );
				box.disabled = true;
				fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( j ) { if ( ! j || ! j.success ) { throw new Error(); } } )
					.catch( function () {
						box.checked = ! want;
						window.alert( 'The tick was not saved. Reload the page and try again.' );
					} )
					.then( function () { box.disabled = false; } );
			} );
		} )();
		</script>
		<?php
	}

	/**
	 * A code counts as given out when it is ticked by hand,
	 * or when a reader has already used it.
	 */
	private static function status_where( $status ) {
		if ( 'free' === $status ) {
			return ' AND c.reader_id IS NULL';
		}
		if ( 'used' === $status ) {
			return ' AND c.reader_id IS NOT NULL';
		}
		if ( 'given' === $status ) {
			return ' AND c.reader_id IS NULL AND c.given_at IS NOT NULL';
		}
		if ( 'spare' === $status ) {
			return ' AND c.reader_id IS NULL AND c.given_at IS NULL';
		}
		return '';
	}

	private static function pager( $total, $per, $paged, $base ) {
		$pages = (int) ceil( $total / $per );
		echo '<p>' . (int) $total . ' in total.';
		if ( $pages > 1 ) {
			if ( $paged > 1 ) {
				printf( ' <a class="button" href="%s">‹ Previous</a>', esc_url( add_query_arg( 'paged', $paged - 1, $base ) ) );
			}
			printf( ' Page %d of %d ', (int) $paged, $pages );
			if ( $paged < $pages ) {
				printf( '<a class="button" href="%s">Next ›</a>', esc_url( add_query_arg( 'paged', $paged + 1, $base ) ) );
			}
		}
		echo '</p>';
	}

	public static function do_generate() {
		self::guard( 'generate' );
		$codes = HTSI_Auth::generate_codes(
			isset( $_POST['volume'] ) ? (int) $_POST['volume'] : 1,
			isset( $_POST['count'] ) ? (int) $_POST['count'] : 1,
			isset( $_POST['batch'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['batch'] ) ), 0, 60 ) : ''
		);
		set_transient( 'htsi_fresh_codes_' . get_current_user_id(), $codes, 10 * MINUTE_IN_SECONDS );
		self::back( 'codes', array( 'msg' => rawurlencode( count( $codes ) . ' codes created.' ) ) );
	}

	public static function do_code_action() {
		global $wpdb;
		self::guard( 'code_action' );
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$do = isset( $_GET['do'] ) ? sanitize_key( $_GET['do'] ) : '';
		if ( 'release' === $do ) {
			// The code is still in somebody's hands, so it stays ticked as given out.
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . HTSI_DB::codes() . ' SET reader_id = NULL, claimed_at = NULL, given_at = COALESCE(given_at, %s) WHERE id = %d', HTSI_DB::now(), $id ) );
			self::back( 'codes', array( 'msg' => rawurlencode( 'Code is free again.' ) ) );
		}
		if ( 'delete' === $do ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . HTSI_DB::codes() . ' WHERE id = %d AND reader_id IS NULL', $id ) );
			self::back( 'codes', array( 'msg' => rawurlencode( 'Code deleted.' ) ) );
		}
		self::back( 'codes' );
	}

	public static function ajax_code_given() {
		global $wpdb;
		if ( ! current_user_can( self::CAP ) || ! check_ajax_referer( 'htsi_code_given', '_wpnonce', false ) ) {
			wp_send_json_error( null, 403 );
		}
		$id    = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$given = ! empty( $_POST['given'] );
		$done  = $wpdb->update( HTSI_DB::codes(), array( 'given_at' => $given ? HTSI_DB::now() : null ), array( 'id' => $id ) );
		if ( false === $done ) {
			wp_send_json_error( null, 500 );
		}
		wp_send_json_success();
	}

	private static function csv( $filename, array $head, array $rows ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, $head );
		foreach ( $rows as $row ) {
			// A leading = + - @ could run as a formula when the file is opened in a spreadsheet.
			$row = array_map(
				function ( $cell ) {
					$cell = (string) $cell;
					return preg_match( '/^[=+\-@\t\r]/', $cell ) ? "'" . $cell : $cell;
				},
				$row
			);
			fputcsv( $out, $row );
		}
		fclose( $out );
		exit;
	}

	public static function do_export_codes() {
		global $wpdb;
		self::guard( 'export_codes' );
		$vol    = isset( $_GET['vol'] ) ? (int) $_GET['vol'] : 0;
		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$where  = 'WHERE 1=1';
		if ( $vol ) {
			$where .= $wpdb->prepare( ' AND c.volume = %d', $vol );
		}
		$where .= self::status_where( $status );
		$rows = $wpdb->get_results( "SELECT c.code, c.volume, IF(c.reader_id IS NOT NULL OR c.given_at IS NOT NULL, 'yes', 'no'), r.email, c.claimed_at, c.batch, c.created_at FROM " . HTSI_DB::codes() . ' c LEFT JOIN ' . HTSI_DB::readers() . " r ON r.id = c.reader_id $where ORDER BY c.id", ARRAY_N );
		self::csv( 'htsi-codes-' . gmdate( 'Y-m-d' ) . '.csv', array( 'code', 'volume', 'given_out', 'reader_email', 'used_on', 'note', 'created' ), $rows );
	}

	/* ---------- readers ---------- */

	private static function tab_readers() {
		global $wpdb;
		$paged = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
		$per   = 100;
		$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . HTSI_DB::readers() );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT r.*, (SELECT GROUP_CONCAT(DISTINCT c.volume ORDER BY c.volume) FROM ' . HTSI_DB::codes() . ' c WHERE c.reader_id = r.id) AS vols FROM ' . HTSI_DB::readers() . ' r ORDER BY r.id DESC LIMIT %d OFFSET %d',
				$per,
				( $paged - 1 ) * $per
			)
		);
		echo '<p>These readers signed in with a book code. They are not WordPress users and have no access to the rest of the site.</p>';
		printf( '<p><a class="button" href="%s">Export CSV</a></p>', esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=htsi_export_readers' ), 'htsi_export_readers' ) ) );
		echo '<table class="widefat striped"><thead><tr><th>Email</th><th>Name</th><th>Volumes</th><th>News by email</th><th>Joined</th><th>Last sign-in</th><th></th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="7">No readers yet.</td></tr>';
		}
		foreach ( $rows as $r ) {
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><a href="%s" style="color:#b32d2e" onclick="return confirm(\'Delete this reader and all their data? Their codes become free again.\')">Delete</a></td></tr>',
				esc_html( $r->email ),
				esc_html( $r->name ),
				esc_html( (string) $r->vols ),
				$r->news_consent ? 'Yes (' . esc_html( (string) $r->consent_at ) . ')' : 'No',
				esc_html( $r->created_at ),
				esc_html( (string) $r->last_seen ),
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=htsi_delete_reader&id=' . (int) $r->id ), 'htsi_delete_reader' ) )
			);
		}
		echo '</tbody></table>';
		self::pager( $total, $per, $paged, self::url( 'readers' ) );
	}

	public static function do_export_readers() {
		global $wpdb;
		self::guard( 'export_readers' );
		$rows = $wpdb->get_results(
			'SELECT r.email, r.name, (SELECT GROUP_CONCAT(DISTINCT c.volume ORDER BY c.volume) FROM ' . HTSI_DB::codes() . ' c WHERE c.reader_id = r.id), r.news_consent, r.consent_at, r.created_at, r.last_seen FROM ' . HTSI_DB::readers() . ' r ORDER BY r.id',
			ARRAY_N
		);
		self::csv( 'htsi-readers-' . gmdate( 'Y-m-d' ) . '.csv', array( 'email', 'name', 'volumes', 'news_consent', 'consent_on', 'joined', 'last_sign_in' ), $rows );
	}

	public static function do_delete_reader() {
		global $wpdb;
		self::guard( 'delete_reader' );
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( $id ) {
			$wpdb->delete( HTSI_DB::sessions(), array( 'reader_id' => $id ) );
			$wpdb->update( HTSI_DB::codes(), array( 'reader_id' => null, 'claimed_at' => null ), array( 'reader_id' => $id ) );
			$wpdb->delete( HTSI_DB::readers(), array( 'id' => $id ) );
		}
		self::back( 'readers', array( 'msg' => rawurlencode( 'Reader deleted.' ) ) );
	}

	/* ---------- chapters ---------- */

	private static function tab_chapters() {
		$v   = max( 1, min( 3, isset( $_GET['v'] ) ? (int) $_GET['v'] : 1 ) );
		$c   = max( 1, min( 10, isset( $_GET['c'] ) ? (int) $_GET['c'] : 1 ) );
		$all = HTSI_Content::all();
		$ch  = $all[ $v ][ $c ];
		$hub = HTSI_Content::hub_url();

		echo '<form method="get" style="margin:12px 0"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '"><input type="hidden" name="tab" value="chapters">';
		echo '<label>Chapter <select name="vc" onchange="var p=this.value.split(\'-\');this.form.v.value=p[0];this.form.c.value=p[1];this.form.submit()">';
		foreach ( $all as $vn => $chapters ) {
			printf( '<optgroup label="Volume %d">', (int) $vn );
			foreach ( $chapters as $cn => $row ) {
				printf( '<option value="%d-%d" %s>%d.%d %s</option>', $vn, $cn, selected( $vn === $v && $cn === $c, true, false ), $vn, $cn, esc_html( $row['title'] ) );
			}
			echo '</optgroup>';
		}
		echo '</select></label><input type="hidden" name="v" value="' . (int) $v . '"><input type="hidden" name="c" value="' . (int) $c . '"></form>';

		if ( $hub ) {
			$link = $hub . '#/' . $v . '/' . $c;
			printf( '<p><strong>Link for the QR code on this map:</strong> <code>%s</code></p>', esc_html( $link ) );
			printf(
				'<div id="htsi-qr" data-link="%s" data-name="%s" style="display:flex;gap:16px;align-items:flex-end;margin:0 0 16px"><p style="margin:0"><button type="button" class="button" data-qr="png">Download PNG</button> <button type="button" class="button" data-qr="svg">Download SVG</button><br><span class="description">PNG is about 1000 px wide. SVG stays sharp at any print size.</span></p></div>',
				esc_attr( $link ),
				esc_attr( sprintf( 'how-to-say-it-qr-v%d-ch%02d', $v, $c ) )
			);
			wp_enqueue_script( 'htsi-qrcode', HTSI_URL . 'assets/vendor/qrcode.js', array(), '1.4.4', true );
			wp_enqueue_script( 'htsi-admin-qr', HTSI_URL . 'assets/admin-qr.js', array( 'htsi-qrcode' ), HTSI_VERSION, true );
		} else {
			printf( '<p><strong>Link for the QR code:</strong> choose the HUB page in <a href="%s">Settings</a> first.</p>', esc_url( self::url( 'settings' ) ) );
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'htsi_save_chapter' );
		printf( '<input type="hidden" name="action" value="htsi_save_chapter"><input type="hidden" name="v" value="%d"><input type="hidden" name="c" value="%d">', (int) $v, (int) $c );
		echo '<table class="form-table">';
		printf( '<tr><th><label for="htsi-title">Chapter title</label></th><td><input id="htsi-title" type="text" name="title" class="large-text" value="%s"></td></tr>', esc_attr( $ch['title'] ) );
		printf( '<tr><th><label for="htsi-map">Mindomo link</label></th><td><input id="htsi-map" type="url" name="map_url" class="large-text" value="%s" placeholder="https://www.mindomo.com/mindmap/…"><p class="description">The public link that opens without a Mindomo login.</p></td></tr>', esc_attr( $ch['map_url'] ) );

		echo '<tr><th>Branches and Fill games</th><td><p class="description">One row per branch, in map order. Leave the Fill link empty until the game is ready — the reader sees “Soon”. Empty rows are ignored.</p>';
		echo '<p class="description">Pictures come from the Media Library. Name each file v1ch1b1, v1ch1b2 … (volume, chapter, branch), in map order.</p><table><thead><tr><th style="padding:4px 10px 4px 0"></th><th style="padding:4px 10px 4px 0">Branch title</th><th style="padding:4px 10px 4px 0">Fill link</th><th style="padding:4px 10px 4px 0">Picture</th></tr></thead><tbody>';
		$branches = array_values( $ch['branches'] );
		$pics     = HTSI_Content::picture_ids( $v, $c );
		$rows     = max( 8, count( $branches ) + 2, $pics ? max( array_keys( $pics ) ) : 0 );
		for ( $i = 0; $i < $rows; $i++ ) {
			$b   = isset( $branches[ $i ] ) ? $branches[ $i ] : array( 'title' => '', 'fill_url' => '' );
			$pic = '<span style="color:#8c8f94">No picture</span>';
			if ( isset( $pics[ $i + 1 ] ) ) {
				$thumb = wp_get_attachment_image_src( $pics[ $i + 1 ], 'medium' );
				if ( $thumb ) {
					$pic = sprintf(
						'<a href="%s"><img src="%s" alt="" style="width:96px;height:auto;display:block;border-radius:3px"></a>',
						esc_url( (string) get_edit_post_link( $pics[ $i + 1 ] ) ),
						esc_url( $thumb[0] )
					);
				}
			}
			printf(
				'<tr><td>%d</td><td><input type="text" name="branch_title[]" value="%s" placeholder="Branch title" style="width:280px" aria-label="Branch %d title"></td><td><input type="url" name="branch_fill[]" value="%s" placeholder="TBT Fill link" style="width:420px" aria-label="Branch %d Fill link"></td><td>%s</td></tr>',
				$i + 1,
				esc_attr( $b['title'] ),
				$i + 1,
				esc_attr( $b['fill_url'] ),
				$i + 1,
				$pic // Built above from escaped parts.
			);
		}
		echo '</tbody></table></td></tr>';

		$json = $ch['exercises'] ? wp_json_encode( $ch['exercises'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : '';
		echo '<tr><th><label for="htsi-ex">Exercises and key</label></th><td>';
		printf( '<textarea id="htsi-ex" name="exercises" rows="24" class="large-text code" spellcheck="false">%s</textarea>', esc_textarea( (string) $json ) );
		echo '<p class="description">Exercises in JSON form. The key is built from the answers inside, so there is nothing else to type. The format is described in <code>docs/exercise-format.md</code> in the plugin folder. Leave empty while the exercises are not ready.</p>';
		$seed = require HTSI_DIR . 'data/seed.php';
		if ( ! empty( $seed[ $v ][ $c ]['exercises'] ) ) {
			echo '<p><label><input type="checkbox" name="use_builtin" value="1"> Replace these exercises with the version that came with the plugin (use this after a plugin update that changes the exercises or the key).</label></p>';
		}
		echo '</td></tr>';
		echo '</table>';
		submit_button( 'Save chapter' );
		echo '</form>';
	}

	public static function do_save_chapter() {
		self::guard( 'save_chapter' );
		$v = max( 1, min( 3, isset( $_POST['v'] ) ? (int) $_POST['v'] : 1 ) );
		$c = max( 1, min( 10, isset( $_POST['c'] ) ? (int) $_POST['c'] : 1 ) );

		$titles   = isset( $_POST['branch_title'] ) ? (array) wp_unslash( $_POST['branch_title'] ) : array();
		$fills    = isset( $_POST['branch_fill'] ) ? (array) wp_unslash( $_POST['branch_fill'] ) : array();
		$branches = array();
		foreach ( $titles as $i => $t ) {
			$t = sanitize_text_field( $t );
			if ( '' === $t ) {
				continue;
			}
			$branches[] = array(
				'title'    => $t,
				'fill_url' => isset( $fills[ $i ] ) ? esc_url_raw( trim( $fills[ $i ] ), array( 'http', 'https' ) ) : '',
			);
		}

		$old       = HTSI_Content::chapter( $v, $c );
		$exercises = $old ? $old['exercises'] : array();
		$raw       = isset( $_POST['exercises'] ) ? trim( (string) wp_unslash( $_POST['exercises'] ) ) : '';
		$err       = '';
		$seed      = require HTSI_DIR . 'data/seed.php';
		if ( ! empty( $_POST['use_builtin'] ) && ! empty( $seed[ $v ][ $c ]['exercises'] ) ) {
			$exercises = $seed[ $v ][ $c ]['exercises'];
		} elseif ( '' === $raw ) {
			$exercises = array();
		} else {
			$parsed = json_decode( $raw, true );
			if ( ! is_array( $parsed ) ) {
				$err = 'The exercises were NOT saved: the JSON has a mistake (' . json_last_error_msg() . '). Everything else was saved.';
			} else {
				$exercises = self::clean_exercises( $parsed );
			}
		}

		HTSI_Content::save_chapter(
			$v,
			$c,
			array(
				'title'     => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
				'map_url'   => isset( $_POST['map_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['map_url'] ) ), array( 'http', 'https' ) ) : '',
				'branches'  => $branches,
				'exercises' => $exercises,
			)
		);
		$args = array( 'v' => $v, 'c' => $c );
		if ( $err ) {
			$args['err'] = rawurlencode( $err );
		} else {
			$args['msg'] = rawurlencode( 'Chapter saved.' );
		}
		self::back( 'chapters', $args );
	}

	/** Strip any HTML from every text in the exercise tree. */
	private static function clean_exercises( $node ) {
		if ( is_array( $node ) ) {
			return array_map( array( __CLASS__, 'clean_exercises' ), $node );
		}
		if ( is_string( $node ) ) {
			return wp_strip_all_tags( $node );
		}
		return $node;
	}

	/* ---------- settings ---------- */

	private static function tab_settings() {
		$s = HTSI_Content::settings();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'htsi_save_settings' );
		echo '<input type="hidden" name="action" value="htsi_save_settings"><table class="form-table">';
		echo '<tr><th><label for="page_id">HUB page</label></th><td>';
		wp_dropdown_pages(
			array(
				'name'             => 'page_id',
				'id'               => 'page_id',
				'selected'         => (int) $s['page_id'],
				'show_option_none' => '— choose —',
				'post_status'      => array( 'publish', 'private', 'draft' ),
			)
		);
		echo '<p class="description">Create a page, put the shortcode <code>[htsi_hub]</code> on it, and choose it here. QR links are built from this page.</p></td></tr>';
		printf( '<tr><th>Layout</th><td><label><input type="checkbox" name="fullscreen" value="1" %s> Show the HUB full screen, without the site header and footer (best on phones)</label></td></tr>', checked( $s['fullscreen'], 1, false ) );
		for ( $n = 1; $n <= 3; $n++ ) {
			printf( '<tr><th><label for="shop_%1$d">Where to buy Volume %1$d</label></th><td><input id="shop_%1$d" type="url" name="shop_%1$d" class="large-text" value="%2$s"><p class="description">Shown to readers who have not unlocked this volume. Leave empty to hide the link.</p></td></tr>', $n, esc_attr( $s[ 'shop_' . $n ] ) );
		}
		echo '</table>';
		submit_button( 'Save settings' );
		echo '</form>';
	}

	public static function do_save_settings() {
		self::guard( 'save_settings' );
		$s = array(
			'page_id'    => isset( $_POST['page_id'] ) ? (int) $_POST['page_id'] : 0,
			'fullscreen' => empty( $_POST['fullscreen'] ) ? 0 : 1,
		);
		for ( $n = 1; $n <= 3; $n++ ) {
			$s[ 'shop_' . $n ] = isset( $_POST[ 'shop_' . $n ] ) ? esc_url_raw( trim( wp_unslash( $_POST[ 'shop_' . $n ] ) ), array( 'http', 'https' ) ) : '';
		}
		update_option( 'htsi_settings', $s, false );
		self::back( 'settings', array( 'msg' => rawurlencode( 'Settings saved.' ) ) );
	}
}
