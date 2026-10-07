<?php
/**
 * Pop-up alerts for new visitor messages (Captain Live Chat Pro).
 *
 * The free plugin shows the number of waiting conversations next to the
 * Inbox menu item and publishes live updates as a `captlc:unread` browser
 * event. This class adds the pop-up (toast) shown on ANY WordPress admin page
 * when a new message arrives, with an optional sound, and lets every agent
 * switch it on or off for themselves from their Profile page.
 *
 * @package captain-live-chat-pro
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CAPTLC_Pro_Alerts
 */
class CAPTLC_Pro_Alerts {

	/**
	 * User meta key holding one agent's alert preferences.
	 */
	const META_KEY = 'captlc_pro_alerts';

	/**
	 * Registers hooks.
	 */
	public function __construct() {
		add_action( 'wp_ajax_captlc_pro_get_alerts', array( $this, 'ajax_get' ) );
		add_action( 'wp_ajax_captlc_pro_save_alerts', array( $this, 'ajax_save' ) );
		// After the free plugin's own admin_enqueue_scripts callback (priority 10)
		// so its live-badge script is already queued when we depend on it.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 20 );
	}

	/**
	 * Default preferences: alerts on, sound off.
	 *
	 * @return array{enabled:bool,sound:bool}
	 */
	private static function defaults() {
		return array(
			'enabled' => true,
			'sound'   => false,
		);
	}

	/**
	 * Returns one agent's preferences.
	 *
	 * @param int $user_id User ID.
	 * @return array{enabled:bool,sound:bool}
	 */
	public static function get_prefs( $user_id ) {
		$stored = get_user_meta( (int) $user_id, self::META_KEY, true );
		$prefs  = self::defaults();

		if ( is_array( $stored ) ) {
			if ( isset( $stored['enabled'] ) ) {
				$prefs['enabled'] = (bool) $stored['enabled'];
			}
			if ( isset( $stored['sound'] ) ) {
				$prefs['sound'] = (bool) $stored['sound'];
			}
		}

		return $prefs;
	}

	/**
	 * Common guard for the two AJAX handlers: valid nonce and a real agent.
	 *
	 * @return void
	 */
	private function guard() {
		check_ajax_referer( CAPTLC_Ajax::NONCE_ACTION, 'nonce' );

		if ( ! is_user_logged_in() || ! CAPTLC_Roles::can_reply( get_current_user_id() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to perform this action.', 'captain-live-chat-pro' ) ), 403 );
		}
	}

	/**
	 * Returns the current agent's preferences.
	 *
	 * @return void
	 */
	public function ajax_get() {
		$this->guard();

		wp_send_json_success( self::get_prefs( get_current_user_id() ) );
	}

	/**
	 * Saves the current agent's preferences (each agent decides for themselves).
	 *
	 * @return void
	 */
	public function ajax_save() {
		$this->guard();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- the nonce is verified in guard() above.
		$prefs = array(
			'enabled' => isset( $_POST['enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['enabled'] ) ),
			'sound'   => isset( $_POST['sound'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['sound'] ) ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		update_user_meta( get_current_user_id(), self::META_KEY, $prefs );

		wp_send_json_success( $prefs );
	}

	/**
	 * Loads the pop-up script on every admin screen for agents who have
	 * alerts switched on.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! is_user_logged_in() || ! class_exists( 'CAPTLC_Roles' ) || ! CAPTLC_Roles::can_reply( get_current_user_id() ) ) {
			return;
		}

		$prefs = self::get_prefs( get_current_user_id() );

		// The free plugin's live-badge script is what announces new messages.
		// Without it (old free version) there is nothing to listen to.
		if ( ! $prefs['enabled'] || ! wp_script_is( 'captlc-admin-unread', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_style(
			'captlc-pro-alerts',
			CAPTLC_PRO_URL . 'assets/css/alerts.css',
			array(),
			CAPTLC_PRO_VERSION
		);

		wp_enqueue_script(
			'captlc-pro-alerts',
			CAPTLC_PRO_URL . 'assets/js/alerts.js',
			array( 'captlc-admin-unread' ),
			CAPTLC_PRO_VERSION,
			true
		);

		// Real JSON booleans (wp_localize_script would turn false into "").
		$config = array(
			'sound'    => (bool) $prefs['sound'],
			'inboxUrl' => admin_url( 'admin.php?page=captain-live-chat' ),
			'i18n'     => array(
				/* translators: %s: visitor name */
				'from'    => __( 'New message from %s', 'captain-live-chat-pro' ),
				/* translators: %d: number of additional conversations waiting */
				'more'    => __( '+%d more conversations waiting', 'captain-live-chat-pro' ),
				'open'    => __( 'Open chat', 'captain-live-chat-pro' ),
				'dismiss' => __( 'Dismiss', 'captain-live-chat-pro' ),
				'region'  => __( 'New chat messages', 'captain-live-chat-pro' ),
			),
		);

		wp_add_inline_script( 'captlc-pro-alerts', 'window.captlcProAlerts = ' . wp_json_encode( $config ) . ';', 'before' );
	}
}
