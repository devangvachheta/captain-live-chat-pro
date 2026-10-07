<?php
/**
 * Plugin Name:       Captain Live Chat Pro
 * Description:       Adds an AI agent with a knowledge base, chat history export and white labelling to Captain Live Chat. Requires the free Captain Live Chat plugin. Bring your own API key for Groq, OpenAI, OpenRouter, Google Gemini, or Anthropic.
 * Version:           1.0.0
 * Author:            devangvachheta
 * Text Domain:       captain-live-chat-pro
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Requires Plugins:  captain-live-chat
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package captain-live-chat-pro
 */

// phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- the main plugin file also defines the tiny CAPTLC_Pro marker class.

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CAPTLC_PRO_VERSION', '1.0.0' );

// Oldest free Captain Live Chat this add-on works with. Older versions are
// missing code Pro relies on, so Pro stays switched off (with a notice)
// instead of crashing the site.
define( 'CAPTLC_PRO_MIN_FREE_VERSION', '1.1.1' );
define( 'CAPTLC_PRO_FILE', __FILE__ );
define( 'CAPTLC_PRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'CAPTLC_PRO_URL', plugin_dir_url( __FILE__ ) );

/**
 * Why Pro can't run right now: '' when it can, 'missing' when the free
 * plugin isn't active, 'outdated' when it is older than the minimum.
 *
 * @return string
 */
function captlc_pro_dependency_problem() {
	if ( ! class_exists( 'CAPTLC_Ajax' ) ) {
		return 'missing';
	}

	if ( ! defined( 'CAPTLC_VERSION' ) || version_compare( CAPTLC_VERSION, CAPTLC_PRO_MIN_FREE_VERSION, '<' ) ) {
		return 'outdated';
	}

	return '';
}

/**
 * Everything in this add-on is gated behind a compatible free Captain Live
 * Chat plugin being active - it has no independent functionality (it hooks
 * into CAPTLC_Ajax's auto-reply call site, the free plugin's admin
 * screen and nonce).
 *
 * @return bool
 */
function captlc_pro_dependency_met() {
	return '' === captlc_pro_dependency_problem();
}

/**
 * Shows an admin notice if the free plugin is missing or too old, instead
 * of fataling.
 *
 * @return void
 */
function captlc_pro_missing_dependency_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$problem = captlc_pro_dependency_problem();

	if ( '' === $problem ) {
		return;
	}

	if ( 'outdated' === $problem ) {
		$message = sprintf(
			/* translators: 1: minimum free plugin version, 2: installed free plugin version */
			esc_html__( '"Captain Live Chat Pro" needs the free "Captain Live Chat" plugin version %1$s or newer (you have %2$s). Please update the free plugin to switch Pro back on.', 'captain-live-chat-pro' ),
			esc_html( CAPTLC_PRO_MIN_FREE_VERSION ),
			esc_html( defined( 'CAPTLC_VERSION' ) ? CAPTLC_VERSION : __( 'unknown', 'captain-live-chat-pro' ) )
		);
	} else {
		$message = esc_html__( '"Captain Live Chat Pro" requires the free "Captain Live Chat" plugin to be installed and active.', 'captain-live-chat-pro' );
	}

	printf( '<div class="notice notice-error"><p>%s</p></div>', $message ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- both messages are escaped above.
}
add_action( 'admin_notices', 'captlc_pro_missing_dependency_notice' );

/**
 * Bootstraps the add-on once we know the free plugin is present. Runs on
 * plugins_loaded at low priority so the free plugin (which also hooks
 * plugins_loaded, default priority 10) has already defined its classes.
 *
 * @return void
 */
function captlc_pro_init() {
	if ( ! captlc_pro_dependency_met() ) {
		return;
	}

	// Canonical "Pro is active" marker - free-plugin code that needs a
	// simple yes/no (e.g. the Canned Responses free-plan cap) checks
	// class_exists( 'CAPTLC_Pro' ) rather than depending on any one
	// specific feature class.
	if ( ! class_exists( 'CAPTLC_Pro' ) ) {
		/**
		 * Marker class: its existence tells the free plugin that Pro is active.
		 */
		class CAPTLC_Pro {} // phpcs:ignore Generic.Classes.OpeningBraceSameLine,PEAR.NamingConventions.ValidClassName
	}

	// Knowledge Base (links/files the AI draws on) - loaded before the AI
	// engine, which reads it through CAPTLC_Knowledge::get_context_text().
	require_once CAPTLC_PRO_PATH . 'includes/class-captlc-knowledge.php';
	new CAPTLC_Knowledge();

	require_once CAPTLC_PRO_PATH . 'includes/class-captlc-ai.php';
	new CAPTLC_AI();

	require_once CAPTLC_PRO_PATH . 'includes/class-captlc-pro-export.php';

	require_once CAPTLC_PRO_PATH . 'includes/class-captlc-white-label.php';
	new CAPTLC_White_Label();

	require_once CAPTLC_PRO_PATH . 'includes/class-captlc-pro-alerts.php';
	new CAPTLC_Pro_Alerts();

	if ( is_admin() ) {
		require_once CAPTLC_PRO_PATH . 'includes/class-captlc-pro-admin.php';
		new CAPTLC_Pro_Admin();
	}
}
add_action( 'plugins_loaded', 'captlc_pro_init', 20 );
