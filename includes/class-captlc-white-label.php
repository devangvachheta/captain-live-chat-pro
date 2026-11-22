<?php
/**
 * White Label - lets an agency replace the "Captain Live Chat" name,
 * logo, author details, documentation/help links and footer text with
 * their own agency branding across WordPress admin and the frontend widget.
 *
 * @package captain-live-chat-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CAPTLC_White_Label
 */
class CAPTLC_White_Label {

	const OPTION_NAME             = 'captlc_white_label_name';
	const OPTION_DESC             = 'captlc_white_label_desc';
	const OPTION_AUTHOR           = 'captlc_white_label_author';
	const OPTION_AUTHOR_URL       = 'captlc_white_label_author_url';
	const OPTION_LOGO_URL         = 'captlc_white_label_logo_url';
	const OPTION_HIDE_PRODUCTS     = 'captlc_white_label_hide_products';
	const OPTION_HIDE_DOCS         = 'captlc_white_label_hide_docs';
	const OPTION_CUSTOM_DOCS_URL   = 'captlc_white_label_custom_docs_url';
	const OPTION_HIDE_HELP         = 'captlc_white_label_hide_help';
	const OPTION_CUSTOM_HELP_URL   = 'captlc_white_label_custom_help_url';
	const OPTION_HIDE_VERSION      = 'captlc_white_label_hide_version';
	const OPTION_SHOW_BRANDING     = 'captlc_white_label_show_branding';
	const OPTION_BRANDING_TEXT     = 'captlc_white_label_branding_text';
	const OPTION_BRANDING_URL      = 'captlc_white_label_branding_url';
	const OPTION_ADMIN_FOOTER      = 'captlc_white_label_admin_footer';

	/**
	 * Constructor - registers hooks.
	 */
	public function __construct() {
		add_action( 'wp_ajax_captlc_pro_save_white_label', array( $this, 'save_white_label' ) );
		add_filter( 'all_plugins', array( $this, 'filter_plugin_meta' ) );
		add_filter( 'admin_footer_text', array( $this, 'filter_admin_footer_text' ), 99 );
	}

	/**
	 * Returns all saved white label options in a single array.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'name'            => get_option( self::OPTION_NAME, '' ),
			'desc'            => get_option( self::OPTION_DESC, '' ),
			'author'          => get_option( self::OPTION_AUTHOR, '' ),
			'author_url'      => get_option( self::OPTION_AUTHOR_URL, '' ),
			'logo_url'        => get_option( self::OPTION_LOGO_URL, '' ),
			'hide_products'   => (bool) get_option( self::OPTION_HIDE_PRODUCTS, false ),
			'hide_docs'       => (bool) get_option( self::OPTION_HIDE_DOCS, false ),
			'custom_docs_url' => get_option( self::OPTION_CUSTOM_DOCS_URL, '' ),
			'hide_help'       => (bool) get_option( self::OPTION_HIDE_HELP, false ),
			'custom_help_url' => get_option( self::OPTION_CUSTOM_HELP_URL, '' ),
			'hide_version'    => (bool) get_option( self::OPTION_HIDE_VERSION, false ),
			'show_branding'   => (bool) get_option( self::OPTION_SHOW_BRANDING, false ),
			'branding_text'   => get_option( self::OPTION_BRANDING_TEXT, '' ),
			'branding_url'    => get_option( self::OPTION_BRANDING_URL, '' ),
			'admin_footer'    => get_option( self::OPTION_ADMIN_FOOTER, '' ),
		);
	}

	/**
	 * Modifies plugin name, description, author and URI on plugins.php.
	 *
	 * @param array $plugins Array of all installed plugins.
	 * @return array
	 */
	public function filter_plugin_meta( $plugins ) {
		$cfg = self::get_config();

		// Targets both free and pro plugin entries
		$target_files = array(
			'captain-live-chat/captain-live-chat.php',
			'captain-live-chat-pro/captain-live-chat-pro.php',
		);

		foreach ( $target_files as $file ) {
			if ( ! isset( $plugins[ $file ] ) ) {
				continue;
			}

			$is_pro = strpos( $file, 'captain-live-chat-pro' ) !== false;

			if ( ! empty( $cfg['name'] ) ) {
				$plugins[ $file ]['Name']  = $is_pro ? $cfg['name'] . ' (Pro)' : $cfg['name'];
				$plugins[ $file ]['Title'] = $plugins[ $file ]['Name'];
			}

			if ( ! empty( $cfg['desc'] ) ) {
				$plugins[ $file ]['Description'] = $cfg['desc'];
			}

			if ( ! empty( $cfg['author'] ) ) {
				$plugins[ $file ]['Author']     = $cfg['author'];
				$plugins[ $file ]['AuthorName'] = $cfg['author'];
			}

			if ( ! empty( $cfg['author_url'] ) ) {
				$plugins[ $file ]['AuthorURI'] = $cfg['author_url'];
				$plugins[ $file ]['PluginURI'] = $cfg['author_url'];
			}
		}

		return $plugins;
	}

	/**
	 * Customizes WordPress admin footer text on Captain Live Chat screens.
	 *
	 * @param string $text Existing footer text.
	 * @return string
	 */
	public function filter_admin_footer_text( $text ) {
		// Only apply on our plugin admin screen
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || strpos( $screen->id, 'captain-live-chat' ) === false ) {
			return $text;
		}

		$footer = get_option( self::OPTION_ADMIN_FOOTER, '' );
		if ( ! empty( $footer ) ) {
			return wp_kses_post( $footer );
		}

		return $text;
	}

	/**
	 * Saves all white label settings via AJAX.
	 *
	 * @return void
	 */
	public function save_white_label() {
		check_ajax_referer( CAPTLC_Ajax::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'captain-live-chat-pro' ) ), 403 );
		}

		$name            = isset( $_POST['name'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['name'] ) ), 0, 60 ) : '';
		$desc            = isset( $_POST['desc'] ) ? sanitize_text_field( wp_unslash( $_POST['desc'] ) ) : '';
		$author          = isset( $_POST['author'] ) ? sanitize_text_field( wp_unslash( $_POST['author'] ) ) : '';
		$author_url      = isset( $_POST['author_url'] ) ? esc_url_raw( wp_unslash( $_POST['author_url'] ), array( 'http', 'https' ) ) : '';
		$logo_url        = isset( $_POST['logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['logo_url'] ), array( 'http', 'https' ) ) : '';
		$hide_products   = ! empty( $_POST['hide_products'] ) ? '1' : '0';
		$hide_docs       = ! empty( $_POST['hide_docs'] ) ? '1' : '0';
		$custom_docs_url = isset( $_POST['custom_docs_url'] ) ? esc_url_raw( wp_unslash( $_POST['custom_docs_url'] ), array( 'http', 'https' ) ) : '';
		$hide_help       = ! empty( $_POST['hide_help'] ) ? '1' : '0';
		$custom_help_url = isset( $_POST['custom_help_url'] ) ? esc_url_raw( wp_unslash( $_POST['custom_help_url'] ), array( 'http', 'https' ) ) : '';
		$hide_version    = ! empty( $_POST['hide_version'] ) ? '1' : '0';
		$show_branding   = ! empty( $_POST['show_branding'] ) ? '1' : '0';
		$branding_text   = isset( $_POST['branding_text'] ) ? sanitize_text_field( wp_unslash( $_POST['branding_text'] ) ) : '';
		$branding_url    = isset( $_POST['branding_url'] ) ? esc_url_raw( wp_unslash( $_POST['branding_url'] ), array( 'http', 'https' ) ) : '';
		$admin_footer    = isset( $_POST['admin_footer'] ) ? wp_kses_post( wp_unslash( $_POST['admin_footer'] ) ) : '';

		if ( strlen( $logo_url ) > 500 ) {
			wp_send_json_error( array( 'message' => __( 'The logo URL is too long.', 'captain-live-chat-pro' ) ) );
		}

		update_option( self::OPTION_NAME, $name );
		update_option( self::OPTION_DESC, $desc );
		update_option( self::OPTION_AUTHOR, $author );
		update_option( self::OPTION_AUTHOR_URL, $author_url );
		update_option( self::OPTION_LOGO_URL, $logo_url );
		update_option( self::OPTION_HIDE_PRODUCTS, $hide_products );
		update_option( self::OPTION_HIDE_DOCS, $hide_docs );
		update_option( self::OPTION_CUSTOM_DOCS_URL, $custom_docs_url );
		update_option( self::OPTION_HIDE_HELP, $hide_help );
		update_option( self::OPTION_CUSTOM_HELP_URL, $custom_help_url );
		update_option( self::OPTION_HIDE_VERSION, $hide_version );
		update_option( self::OPTION_SHOW_BRANDING, $show_branding );
		update_option( self::OPTION_BRANDING_TEXT, $branding_text );
		update_option( self::OPTION_BRANDING_URL, $branding_url );
		update_option( self::OPTION_ADMIN_FOOTER, $admin_footer );

		wp_send_json_success( self::get_config() );
	}
}
