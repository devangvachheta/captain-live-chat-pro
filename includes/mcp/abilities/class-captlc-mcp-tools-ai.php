<?php
/**
 * MCP - AI (auto-reply engine) domain, as WordPress Abilities. SCAFFOLD
 * - see class-captlc-mcp-tools-agents.php header.
 *
 * IMPORTANT when wiring get_settings: provider API keys must return
 * masked (e.g. "sk-...abcd") never the decrypted value, matching the
 * existing admin UI behaviour in class-captlc-ai.php.
 *
 * @package captain-live-chat
 * @subpackage captain-live-chat/includes/mcp/abilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CAPTLC_MCP_Tools_Ai
 */
class CAPTLC_MCP_Tools_Ai {

	/**
	 * Registers this domain's abilities.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'captlc/ai-get-settings',
			array(
				'label'               => __( 'Get AI Settings', 'captain-live-chat-pro' ),
				'description'         => __( 'Get AI auto-reply settings (provider API keys are masked).', 'captain-live-chat-pro' ),
				'category'            => CAPTLC_MCP_Categories::AI,
				'execute_callback'    => array( __CLASS__, 'not_implemented' ),
				'permission_callback' => array( 'CAPTLC_MCP_Settings', 'can_manage' ),
				'meta'                => CAPTLC_MCP_Ability_Meta::build( 'captlc/ai-get-settings', true ),
			)
		);

		wp_register_ability(
			'captlc/ai-save-provider',
			array(
				'label'               => __( 'Save AI Provider', 'captain-live-chat-pro' ),
				'description'         => __( 'Save the active AI provider and its API key.', 'captain-live-chat-pro' ),
				'category'            => CAPTLC_MCP_Categories::AI,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'provider' => array( 'type' => 'string' ),
						'api_key'  => array( 'type' => 'string' ),
						'model'    => array( 'type' => 'string' ),
					),
					'required'   => array( 'provider' ),
				),
				'execute_callback'    => array( __CLASS__, 'not_implemented' ),
				'permission_callback' => array( 'CAPTLC_MCP_Settings', 'can_manage' ),
				'meta'                => CAPTLC_MCP_Ability_Meta::build( 'captlc/ai-save-provider', false ),
			)
		);

		wp_register_ability(
			'captlc/ai-test-provider',
			array(
				'label'               => __( 'Test AI Provider', 'captain-live-chat-pro' ),
				'description'         => __( 'Test the currently configured AI provider connection.', 'captain-live-chat-pro' ),
				'category'            => CAPTLC_MCP_Categories::AI,
				'execute_callback'    => array( __CLASS__, 'not_implemented' ),
				'permission_callback' => array( 'CAPTLC_MCP_Settings', 'can_manage' ),
				'meta'                => CAPTLC_MCP_Ability_Meta::build( 'captlc/ai-test-provider', false ),
			)
		);

		wp_register_ability(
			'captlc/ai-save-general',
			array(
				'label'               => __( 'Save AI General Settings', 'captain-live-chat-pro' ),
				'description'         => __( 'Save general AI behaviour settings (tone, auto-reply on/off, escalation rules).', 'captain-live-chat-pro' ),
				'category'            => CAPTLC_MCP_Categories::AI,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'settings' => array( 'type' => 'object' ) ),
					'required'   => array( 'settings' ),
				),
				'execute_callback'    => array( __CLASS__, 'not_implemented' ),
				'permission_callback' => array( 'CAPTLC_MCP_Settings', 'can_manage' ),
				'meta'                => CAPTLC_MCP_Ability_Meta::build( 'captlc/ai-save-general', false ),
			)
		);
	}

	/**
	 * Placeholder callback for not-yet-wired abilities in this domain.
	 *
	 * @return WP_Error
	 */
	public static function not_implemented() {
		return new WP_Error( 'captlc_mcp_not_implemented', __( 'This ability is scaffolded but not yet implemented.', 'captain-live-chat-pro' ) );
	}
}
