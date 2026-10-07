<?php
/**
 * Enqueues Captain Live Chat Pro's compiled JS bundle (AI Agent, White Label) on the free
 * plugin's own admin screen, as a dependency of the free plugin's
 * script - so it always runs after `window.CaptlcExtensions` exists.
 *
 * @package captain-live-chat-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CAPTLC_Pro_Admin
 */
class CAPTLC_Pro_Admin {

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueues build/index.js on the same hook the free plugin uses for
	 * its own admin page, declared as depending on the free plugin's
	 * 'captlc-admin-script' handle so script execution order is
	 * guaranteed regardless of plugin activation order.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( 'toplevel_page_captain-live-chat' !== $hook ) {
			return;
		}

		// The free plugin's script must already be registered for us to
		// depend on it - if somehow it isn't (e.g. free plugin update
		// mid-flight), skip rather than enqueue a broken dependency.
		if ( ! wp_script_is( 'captlc-admin-script', 'registered' ) ) {
			return;
		}

		$asset_file = CAPTLC_PRO_PATH . 'build/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		// Versioned by file time, so the new stylesheet is fetched after every build.
		wp_enqueue_style(
			'captlc-pro-css',
			CAPTLC_PRO_URL . 'build/index.css',
			array(),
			CAPTLC_PRO_VERSION . '.' . filemtime( CAPTLC_PRO_PATH . 'build/index.css' )
		);
		wp_style_add_data( 'captlc-pro-css', 'rtl', 'replace' );

		wp_enqueue_script(
			'captlc-pro-script',
			CAPTLC_PRO_URL . 'build/index.js',
			array_merge( $asset['dependencies'], array( 'captlc-admin-script' ) ),
			$asset['version'],
			true
		);

		wp_set_script_translations( 'captlc-pro-script', 'captain-live-chat-pro' );
	}
}
