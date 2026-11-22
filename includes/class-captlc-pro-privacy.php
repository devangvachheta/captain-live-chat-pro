<?php
/**
 * GDPR / Privacy Policy integration for Pro features.
 *
 * @package captain-live-chat-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CAPTLC_Pro_Privacy
 */
class CAPTLC_Pro_Privacy {

	/**
	 * Registers privacy hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'add_privacy_policy_content' ) );
	}

	/**
	 * Adds suggested text to the WordPress Privacy Policy Guide for Pro features.
	 *
	 * @return void
	 */
	public static function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf(
			'<h2>%1$s</h2>' .
			'<p>%2$s</p>' .
			'<h3>%3$s</h3>' .
			'<p>%4$s</p>' .
			'<h3>%5$s</h3>' .
			'<p>%6$s</p>' .
			'<h3>%7$s</h3>' .
			'<p>%8$s</p>',
			esc_html__( 'Captain Live Chat Pro - Extended Features & Privacy', 'captain-live-chat-pro' ),
			esc_html__( 'If the site owner has enabled Captain Live Chat Pro, additional features such as Live Visitors presence tracking, AI-powered automated responses, Post-Chat Surveys, and Email Transcripts may process visitor data.', 'captain-live-chat-pro' ),
			esc_html__( 'Live Visitors Tracking', 'captain-live-chat-pro' ),
			esc_html__( 'When Live Visitors tracking is active, temporary heartbeat pings are sent while browsing to show online presence to active support agents. Data collected includes current page URL, referrer, device type, browser, and session duration. This tracking does not store permanent cookies across sessions and records are automatically cleaned up.', 'captain-live-chat-pro' ),
			esc_html__( 'AI Chatbot & Knowledge Base', 'captain-live-chat-pro' ),
			esc_html__( 'If AI responses are enabled, visitor inquiries may be processed by third-party AI APIs (such as OpenAI, Anthropic Claude, or Groq) configured by the site administrator. The message text is sent to the respective AI provider solely to generate relevant replies and is subject to the provider\'s API privacy policy.', 'captain-live-chat-pro' ),
			esc_html__( 'Surveys & Email Transcripts', 'captain-live-chat-pro' ),
			esc_html__( 'Post-chat ratings and optional feedback comments are stored in the local site database linked to the conversation thread. When a visitor requests a chat transcript by email or when auto-send is enabled with consent, the conversation summary is dispatched via email to the address provided.', 'captain-live-chat-pro' )
		);

		wp_add_privacy_policy_content( __( 'Captain Live Chat Pro', 'captain-live-chat-pro' ), $content );
	}
}
