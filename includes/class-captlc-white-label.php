<?php
/**
 * White Label - lets an agency replace the "Captain Live Chat" name and
 * logo with their own branding. Saves to two options that the free
 * plugin's menu class (includes/admin/class-captlc-menu.php) and its
 * localized captlc_data.white_label read (only while Pro is active) -
 * this file is the only thing that ever WRITES them.
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

	const OPTION_NAME     = 'captlc_white_label_name';
	const OPTION_LOGO_URL = 'captlc_white_label_logo_url';

	/**
	 * Constructor - registers hooks.
	 */
	public function __construct() {
		add_action( 'wp_ajax_captlc_pro_save_white_label', array( $this, 'save_white_label' ) );
	}

	/**
	 * Saves the custom plugin name / logo URL.
	 *
	 * @return void
	 */
	public function save_white_label() {
		check_ajax_referer( CAPTLC_Ajax::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'captain-live-chat-pro' ) ), 403 );
		}

		$name     = isset( $_POST['name'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['name'] ) ), 0, 60 ) : '';
		$logo_url = isset( $_POST['logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['logo_url'] ), array( 'http', 'https' ) ) : '';

		if ( strlen( $logo_url ) > 500 ) {
			wp_send_json_error( array( 'message' => __( 'The logo URL is too long.', 'captain-live-chat-pro' ) ) );
		}

		update_option( self::OPTION_NAME, $name );
		update_option( self::OPTION_LOGO_URL, $logo_url );

		wp_send_json_success(
			array(
				'name'     => $name,
				'logo_url' => $logo_url,
			)
		);
	}
}
