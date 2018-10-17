<?php
/*
Plugin Name:	Jarvis
Plugin URI:		http://www.wpjarvis.com
Description:	Jarvis is your administration assistant, putting WordPress at your fingertips.
Version:		0.51.0
Author:			wdgdc, David Everett, Joan Piedra, Kurtis Shaner
Author URI:		http://www.webdevelopmentgroup.com
License:		GPLv2 or later
Text Domain:	jarvis
*/

class Jarvis {

	const VERSION = '0.51.0';

	private static $_instance;
	public static function get_instance() {
		if (empty(self::$_instance)) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	private $themes = [
		'light' => 'Light',
		'dark'  => 'Dark'
	];

	private $options = array(
		'hotkey'  => '/',
		'keyCode' => 191,
		'theme'   => 'light',
	);

	private function __construct() {
		$this->options['nonce'] = wp_create_nonce( 'jarvis-search' );

		$theme = get_user_meta( get_current_user_id(), 'jarvis_theme', true );
		$this->options['theme'] = ( ! empty( $theme ) && in_array( $theme, array_keys( $this->themes ), true ) ) ? $theme : array_keys( $this->themes )[0];

		add_action( 'admin_bar_menu', [ $this, 'menubar_icon' ] , 100 );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'admin_init', [ $this, 'admin_init' ] );
		add_action( 'edit_user_profile', [ $this, 'show_user_profile' ] );
		add_action( 'edit_user_profile_update', [ $this, 'edit_user_profile_update' ] );
		add_action( 'personal_options_update', [ $this, 'edit_user_profile_update' ] );
		add_action( 'show_user_profile', [ $this, 'show_user_profile' ] );
		add_action( 'wp_ajax_jarvis-search', [ $this, 'get_search_results' ] , 1 );
	}

	/**
	 * Grab the users keycode setting
	 *
	 * @access public
	 * @action admin_init
	 */
	public function admin_init() {
		if ($user_keycode = get_user_meta(get_current_user_id(), 'jarvis_keycode', true)) {
			$this->options['keyCode'] = (int) $user_keycode;
		}
		if ($user_hotkey = get_user_meta(get_current_user_id(), 'jarvis_hotkey', true)) {
			$this->options['hotkey'] = $user_hotkey;
		}

		// this code allows us to determine the post type icon for custom post types that have a dashicon specified
		$post_types = array_filter( get_post_types( [], 'objects' ), function( $post_type ) {
			return ! empty( $post_type->menu_icon );
		} );

		$this->options['icons'] = array_combine( array_keys( $post_types ), array_map( function( $post_type ) {
			return [
				'type' => 'dashicon',
				'icon' => $post_type->menu_icon
			];
		}, $post_types ) );
	}

	/**
	 * Add the field and script to customize the Jarvis keyCode
	 *
	 * @access public
	 * @action show_user_profile, edit_user_profile
	 */
	public function show_user_profile( $user ) { ?>
		<h3>Jarvis</h3>

		<table class="form-table">
			<tr>
				<th><label for="jarvis-hotkey">Hotkey</label></th>
				<td>
					<p><input type="text" name="jarvis_hotkey" id="jarvis_hotkey" value="<?php echo $this->options['hotkey']; ?>" class="regular-text" autocomplete="off" style="width:25px;text-align:center;" /></p>
					<p><span class="description">Enter the key you would like to invoke jarvis with. Supports lowercase a-z, 0-9, and any of these special characters: ; = , - . / ` [ \ ] ' only.</span></p>
					<input type="hidden" id="jarvis_keycode" name="jarvis_keycode" value="<?php echo $this->options['keyCode']; ?>">
				</td>
			</tr>
			<tr>
				<th><label for="jarvis-theme">Theme</label></th>
				<td>
					<p>
						<select name="jarvis_theme" class="regular-text">
							<?php foreach( $this->themes as $theme => $label ) : ?>
							<option value="<?php echo esc_attr( $theme ); ?>"<?php if ( $theme === $this->options['theme'] ) echo ' selected'; ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
				</td>
			</tr>
		</table>

		<script>
			(function() {
				var hotKey  = document.getElementById('jarvis_hotkey');
				var keyCode = document.getElementById('jarvis_keycode');
				var keys = {
					"0":48,"1":49,"2":50,"3":51,"4":52,"5":53,"6":54,"7":55,"8":56,"9":57,
					"a":65,"b":66,"c":67,"d":68,"e":69,"f":70,"g":71,"h":72,"i":73,"j":74,
					"k":75,"l":76,"m":77,"n":78,"o":79,"p":80,"q":81,"r":82,"s":83,"t":84,
					"u":85,"v":86,"w":87,"x":88,"y":89,"z":90,";":186,"=":187,",":188,"-":189,
					".":190,"/":191,"`":192,"[":219,"\\":220,"]":221,"'":222
				};
				var keyCodes = [];
				for(var key in keys) {
					if (keys.hasOwnProperty(key)) {
						keyCodes.push(keys[key]);
					}
				}

				var keyUp = function(e) {
					if (keyCodes.indexOf(e.which) > -1) {
						this.value = this.value.charAt(0).toLowerCase();
						keyCode.value = keys[this.value];
					} else {
						this.value = '';
						keyCode.value = '';
					}
				}
				jQuery(hotKey).on('keyup', keyUp);
			})();
		</script>
	<?php }

	/**
	 * Save user fields
	 *
	 * @access public
	 * @action personal_options_update, edit_user_profile_update
	 */
	public function edit_user_profile_update( $user_id ) {
		if ( current_user_can( 'edit_user', $user_id ) ) {
			update_user_meta( $user_id, 'jarvis_hotkey', sanitize_text_field( $_POST['jarvis_hotkey'] ) );
			update_user_meta( $user_id, 'jarvis_keycode', sanitize_text_field( $_POST['jarvis_keycode'] ) );
			update_user_meta( $user_id, 'jarvis_theme', sanitize_text_field( $_POST['jarvis_theme'] ) );
		}
	}

	/**
	 * Enqueue jarvis style and scripts
	 *
	 * @access public
	 * @action admin_enqueue_scripts
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_style( 'wp-jarvis', plugins_url( 'dist/jarvis.css', __FILE__ ), [], self::VERSION, 'screen' );
		wp_register_script( 'typeahead', plugins_url( 'dist/vendor/typeahead.js/dist/typeahead.bundle.min.js', __FILE__ ), array( 'jquery' ), self::VERSION );
		wp_enqueue_script( 'wp-jarvis', plugins_url( 'dist/jarvis.js', __FILE__), array( 'jquery', 'underscore', 'typeahead' ), self::VERSION );

		wp_add_inline_script( 'wp-jarvis', 'window.jarvis = new Jarvis('. wp_json_encode( $this->options ) .');', 'after' );
	}

	/**
	 * Add Jarvis to the menu bar as a search icon
	 *
	 * @access public
	 * @action admin_footer
	 */
	public function menubar_icon($admin_bar) {
		$admin_bar->add_menu(array(
			'id' => 'jarvis_menubar_icon',
			'title' => '<span>Jarvis Search</span>',
			'href' => '#jarvis',
			'meta' => array(
				'title' => 'Invoke Jarvis',
				'class' => 'dashicon'
			),
			'parent' => 'top-secondary'
		));
	}

	/**
	 * Prepend post_id search to main search query
	 *
	 * @access private
	 */
	private function search_post_id($id = null) {
		if (!empty($id)) {
			$post = get_post($id);

			if (!empty($post)) {

				$post_result = (object) array(
					'id'    => $post->ID,
					'title' => apply_filters('the_title', $post->post_title) . " - (ID $id)",
					'type'  => $post->post_type,
					'kind'  => $post->post_type,
					'isId'  => true
				);

				array_unshift($this->results, $post_result);
			}
		}
	}

	/**
	 * Grab the item edit url's and thumbnails
	 *
	 * @access private
	 */
	private function normalize($result) {
		$edit_paths = array(
			'_default_'  => 'post.php?post=%d&action=edit',
			'term'       => 'edit-tags.php?action=edit&tag_ID=%d&taxonomy=%s',
			'post'       => 'post.php?post=%d&action=edit',
			'user'       => 'user-edit.php?user_id=%d',
			'attachment' => 'upload.php?item=%d',
		);

		if ( isset( $edit_paths[ $result->kind ] ) ) {
			if ( isset( $edit_paths[ $result->type ] ) ) {
				$edit_url = $edit_paths[ $result->type ];
			} else {
				$edit_url = $edit_paths[ $result->kind ];
			}
		} else {
			$edit_url = $edit_paths['_default_'];
		}

		$result->href = admin_url( sprintf( $edit_url, $result->id, $result->type ) );

		switch( $result->type ) {
			case 'attachment':
				$result->att_src = wp_get_attachment_image_src( $result->id, [ 28, 28 ] );
				$result->att_src = $result->att_src[0];
				break;
			case 'post':
				$result->att_src = wp_get_attachment_image_src( get_post_thumbnail_id( $result->id, [ 28, 28 ] ) );
				$result->att_src = $result->att_src[0];
				break;
			case 'user':
				$avatar = get_avatar_data( $result->id, [
					'size' => [ 28, 28 ],
				] );

				if ( ! empty( $avatar['found_avatar'] ) ) {
					$result->att_src = $avatar['url'];
				}
				break;
		}

		return $result;
	}

	/**
	 * Grab the item edit url's and thumbnails
	 *
	 * @access public
	 * @action wp_ajax_jarvis-search
	 */

	public function get_search_results() {
		global $wpdb;

		// Don't break the json if debug is off
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			error_reporting(0);
		}

		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], 'jarvis-search' ) ) {
			wp_send_json_error( 'invalid nonce' );
		}

		$_REQUEST['q'] = isset( $_REQUEST['q'] ) ? $_REQUEST['q'] : '';

		$srch_qry = $wpdb->esc_like( $_REQUEST['q'] );
		$srch_escaped_spaces = '%' . str_replace( ' ', '%', $srch_qry ) . '%';

		$post_types = "'" . implode( "','", array_values( get_post_types( array( 'show_ui' => true ) ) ) ) . "'";

		$strQry = "SELECT
				$wpdb->terms.term_id as 'id',
				$wpdb->terms.`name` as 'title',
				$wpdb->term_taxonomy.taxonomy as 'type',
				'term' as 'kind',
				$wpdb->terms.slug as 'slug',
				FLOOR( (LENGTH($wpdb->terms.term_id) - LENGTH(REPLACE(LOWER($wpdb->terms.term_id), LOWER(%s), '')) / LENGTH(%s)) ) as 'relv_id',
				FLOOR( (LENGTH($wpdb->term_taxonomy.taxonomy) - LENGTH(REPLACE(LOWER($wpdb->term_taxonomy.taxonomy), LOWER(%s), '')) / LENGTH(%s)) ) as 'relv_title',
				FLOOR( (LENGTH($wpdb->terms.`name`) - LENGTH(REPLACE(LOWER($wpdb->terms.`name`), LOWER(%s), '')) / LENGTH(%s)) ) as 'relv_type',
				FLOOR( LENGTH($wpdb->terms.slug) / LENGTH(REPLACE(LOWER($wpdb->terms.slug), LOWER(%s), '')) ) as 'relv_slug'
			FROM
				$wpdb->terms
			INNER JOIN
				$wpdb->term_taxonomy ON $wpdb->term_taxonomy.term_id = $wpdb->terms.term_id
			WHERE
				$wpdb->terms.`name` LIKE %s
			OR
				$wpdb->terms.slug LIKE %s
		UNION
			SELECT
				$wpdb->posts.ID as 'id',
				$wpdb->posts.post_title as 'title',
				$wpdb->posts.post_type as 'type',
				'post' as 'kind',
				$wpdb->posts.post_name as 'slug',
				FLOOR( (LENGTH($wpdb->posts.ID) - LENGTH(REPLACE(LOWER($wpdb->posts.ID), LOWER(%s), '')) / LENGTH(%s)) ) as 'relv_id',
				FLOOR( (LENGTH($wpdb->posts.post_title) - LENGTH(REPLACE(LOWER($wpdb->posts.post_title), LOWER(%s), '')) / LENGTH(%s)) ) as 'relv_title',
				FLOOR( (LENGTH($wpdb->posts.post_type) - LENGTH(REPLACE(LOWER($wpdb->posts.post_type), LOWER(%s), '')) / LENGTH(%s)) ) as 'relv_type',
				FLOOR( (LENGTH($wpdb->posts.post_name) / LENGTH(REPLACE(LOWER($wpdb->posts.post_name), LOWER(%s), '')) ) ) as 'relv_slug'
			FROM
				$wpdb->posts
			WHERE
				$wpdb->posts.post_status NOT IN ('revision', 'auto-draft') AND $wpdb->posts.post_type <> 'revision'
			AND
				$wpdb->posts.post_type IN ($post_types)
			AND (
				$wpdb->posts.post_title LIKE %s
				OR
				$wpdb->posts.post_name LIKE %s
			)
		UNION
			SELECT
				$wpdb->users.ID as 'id',
				$wpdb->users.display_name as 'title',
				'user' as 'type',
				'user' as 'kind',
				$wpdb->users.user_email as 'slug',
				FLOOR( (LENGTH($wpdb->users.ID) - LENGTH(REPLACE(LOWER($wpdb->users.ID), LOWER(%s), '')) / LENGTH(%s)) ) as 'relv_id',
				FLOOR( (LENGTH($wpdb->users.display_name) - LENGTH(REPLACE(LOWER($wpdb->users.display_name), LOWER(%s), '')) / LENGTH(%s)) ) as 'relv_title',
				0 as 'relv_type',
				FLOOR( (LENGTH($wpdb->users.user_email) / LENGTH(REPLACE(LOWER($wpdb->users.user_email), LOWER(%s), '')) ) ) as 'relv_slug'
			FROM
				$wpdb->users
			WHERE
				$wpdb->users.display_name LIKE %s
			OR
				$wpdb->users.user_email LIKE %s
			OR
				$wpdb->users.user_login LIKE %s
		ORDER BY relv_id, relv_slug, relv_type, relv_title DESC
		LIMIT 20
		";

		$sql_prepared = array(
			$srch_qry, $srch_qry, $srch_qry, $srch_qry, $srch_qry, $srch_qry, $srch_qry,
			$srch_escaped_spaces, $srch_escaped_spaces,
			$srch_qry, $srch_qry, $srch_qry, $srch_qry, $srch_qry, $srch_qry, $srch_qry,
			$srch_escaped_spaces, $srch_escaped_spaces,
			$srch_qry, $srch_qry, $srch_qry, $srch_qry, $srch_qry,
			$srch_escaped_spaces, $srch_escaped_spaces, $srch_escaped_spaces
		);

		$this->results = $wpdb->get_results( $wpdb->prepare( $strQry, $sql_prepared ) );

		$this->search_post_id( $_REQUEST['q'] );
		$this->results = array_map( array( $this, 'normalize' ), $this->results );

		wp_send_json_success( $this->results );
	}
}

if ( is_admin() ) {
	add_action( 'plugins_loaded', array( 'Jarvis', 'get_instance' ) );
}
