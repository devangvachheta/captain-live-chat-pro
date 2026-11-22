<?php
/**
 * Proactive Greeting / Auto-Open PRO feature for Floating Chat Buttons.
 *
 * @package captain-live-chat-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CAPTLC_Pro_Proactive_Greeting
 */
class CAPTLC_Pro_Proactive_Greeting {

	/**
	 * Constructor - registers hooks.
	 */
	public function __construct() {
		add_filter( 'captlc_chat_buttons_settings_defaults', array( $this, 'filter_defaults' ) );
		add_filter( 'captlc_chat_buttons_sanitize_settings', array( $this, 'filter_sanitize' ), 10, 2 );
		add_action( 'captlc_launcher_after_render', array( $this, 'render_greeting_bubble' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
	}

	/**
	 * Injects Pro defaults into Chat Buttons settings.
	 *
	 * @param array $defaults Array of default settings.
	 * @return array
	 */
	public function filter_defaults( $defaults ) {
		$defaults['greeting_enabled']    = false;
		$defaults['greeting_message']    = __( 'Hi there! How can we help you today? 👋', 'captain-live-chat-pro' );
		$defaults['trigger_type']        = 'delay'; // 'delay' | 'scroll' | 'exit_intent'
		$defaults['delay_seconds']       = 5;
		$defaults['scroll_percentage']   = 50;
		$defaults['action']              = 'bubble_only'; // 'bubble_only' | 'bubble_and_open'
		$defaults['auto_hide_seconds']   = 0;
		$defaults['frequency']           = 'session'; // 'session' | 'days' | 'page_load'
		$defaults['frequency_days']      = 7;
		$defaults['page_rules_mode']     = 'all'; // 'all' | 'include' | 'exclude'
		$defaults['page_rules_urls']     = '';
		$defaults['page_rules_home_only']= false;
		$defaults['greeting_device']     = 'both'; // 'both' | 'desktop' | 'mobile'

		return $defaults;
	}

	/**
	 * Sanitizes Pro settings.
	 *
	 * @param array $clean Cleaned settings.
	 * @param array $raw   Raw input.
	 * @return array
	 */
	public function filter_sanitize( $clean, $raw ) {
		$clean['greeting_enabled']    = ! empty( $raw['greeting_enabled'] );
		$clean['greeting_message']    = sanitize_text_field( isset( $raw['greeting_message'] ) ? $raw['greeting_message'] : '' );
		$clean['trigger_type']        = isset( $raw['trigger_type'] ) && in_array( $raw['trigger_type'], array( 'delay', 'scroll', 'exit_intent' ), true ) ? $raw['trigger_type'] : 'delay';
		$clean['delay_seconds']       = max( 1, min( 120, isset( $raw['delay_seconds'] ) ? absint( $raw['delay_seconds'] ) : 5 ) );
		$clean['scroll_percentage']   = max( 10, min( 90, isset( $raw['scroll_percentage'] ) ? absint( $raw['scroll_percentage'] ) : 50 ) );
		$clean['action']              = isset( $raw['action'] ) && in_array( $raw['action'], array( 'bubble_only', 'bubble_and_open' ), true ) ? $raw['action'] : 'bubble_only';
		$clean['auto_hide_seconds']   = max( 0, min( 60, isset( $raw['auto_hide_seconds'] ) ? absint( $raw['auto_hide_seconds'] ) : 0 ) );
		$clean['frequency']           = isset( $raw['frequency'] ) && in_array( $raw['frequency'], array( 'session', 'days', 'page_load' ), true ) ? $raw['frequency'] : 'session';
		$clean['frequency_days']      = max( 1, min( 365, isset( $raw['frequency_days'] ) ? absint( $raw['frequency_days'] ) : 7 ) );
		$clean['page_rules_mode']     = isset( $raw['page_rules_mode'] ) && in_array( $raw['page_rules_mode'], array( 'all', 'include', 'exclude' ), true ) ? $raw['page_rules_mode'] : 'all';
		$clean['page_rules_urls']     = sanitize_textarea_field( isset( $raw['page_rules_urls'] ) ? $raw['page_rules_urls'] : '' );
		$clean['page_rules_home_only']= ! empty( $raw['page_rules_home_only'] );
		$clean['greeting_device']     = isset( $raw['greeting_device'] ) && in_array( $raw['greeting_device'], array( 'both', 'desktop', 'mobile' ), true ) ? $raw['greeting_device'] : 'both';

		return $clean;
	}

	/**
	 * Evaluates whether the greeting should render on the current page.
	 *
	 * @return bool
	 */
	public function should_render_greeting() {
		$launcher_state = ( class_exists( 'CAPTLC_Public' ) && method_exists( 'CAPTLC_Public', 'get_launcher_state' ) ) ? CAPTLC_Public::get_launcher_state() : 'none';
		if ( 'none' === $launcher_state ) {
			return false;
		}

		$settings = class_exists( 'CAPTLC_Chat_Buttons' ) ? CAPTLC_Chat_Buttons::get_settings() : array();
		if ( empty( $settings['greeting_enabled'] ) ) {
			return false;
		}

		// Device check.
		$is_mobile = wp_is_mobile();
		if ( 'desktop' === $settings['greeting_device'] && $is_mobile ) {
			return false;
		}
		if ( 'mobile' === $settings['greeting_device'] && ! $is_mobile ) {
			return false;
		}

		// Exit intent on mobile is not supported; fallback or skip.
		if ( 'exit_intent' === $settings['trigger_type'] && $is_mobile ) {
			return false;
		}

		// Page rules: Home only check.
		if ( ! empty( $settings['page_rules_home_only'] ) && ! ( is_front_page() || is_home() ) ) {
			return false;
		}

		// Page rules: URLs matching.
		$mode = isset( $settings['page_rules_mode'] ) ? $settings['page_rules_mode'] : 'all';
		if ( 'all' !== $mode && ! empty( $settings['page_rules_urls'] ) ) {
			$current_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$parsed_path = wp_parse_url( $current_uri, PHP_URL_PATH );
			$parsed_path = $parsed_path ? untrailingslashit( $parsed_path ) : '';
			$lines       = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $settings['page_rules_urls'] ) ) ) );
			$matched     = false;

			foreach ( $lines as $line ) {
				$clean_line = untrailingslashit( trim( $line ) );
				if ( '' === $clean_line ) {
					continue;
				}
				// Exact path match or directory segment match (/shop/ but not /shop-old).
				if ( $parsed_path === $clean_line || 0 === strpos( $parsed_path, $clean_line . '/' ) ) {
					$matched = true;
					break;
				}
			}

			if ( 'include' === $mode && ! $matched ) {
				return false;
			}
			if ( 'exclude' === $mode && $matched ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Enqueues proactive greeting CSS and JS.
	 */
	public function enqueue_assets() {
		if ( ! $this->should_render_greeting() ) {
			return;
		}

		wp_enqueue_style(
			'captlc-pro-greeting',
			CAPTLC_PRO_URL . 'assets/css/proactive-greeting.css',
			array(),
			CAPTLC_PRO_VERSION
		);

		wp_enqueue_script(
			'captlc-pro-greeting',
			CAPTLC_PRO_URL . 'assets/js/proactive-greeting.js',
			array(),
			CAPTLC_PRO_VERSION,
			true
		);

		$settings = class_exists( 'CAPTLC_Chat_Buttons' ) ? CAPTLC_Chat_Buttons::get_settings() : array();

		wp_localize_script(
			'captlc-pro-greeting',
			'captlcGreetingData',
			array(
				'triggerType'      => isset( $settings['trigger_type'] ) ? $settings['trigger_type'] : 'delay',
				'delaySeconds'     => isset( $settings['delay_seconds'] ) ? (int) $settings['delay_seconds'] : 5,
				'scrollPercentage' => isset( $settings['scroll_percentage'] ) ? (int) $settings['scroll_percentage'] : 50,
				'action'           => isset( $settings['action'] ) ? $settings['action'] : 'bubble_only',
				'autoHideSeconds'  => isset( $settings['auto_hide_seconds'] ) ? (int) $settings['auto_hide_seconds'] : 0,
				'frequency'        => isset( $settings['frequency'] ) ? $settings['frequency'] : 'session',
				'frequencyDays'    => isset( $settings['frequency_days'] ) ? (int) $settings['frequency_days'] : 7,
			)
		);
	}

	/**
	 * Renders accessible greeting bubble near the floating buttons or widget toggle.
	 */
	public function render_greeting_bubble() {
		static $rendered = false;
		if ( $rendered ) {
			return;
		}

		if ( ! $this->should_render_greeting() ) {
			return;
		}

		$rendered = true;

		$settings = class_exists( 'CAPTLC_Chat_Buttons' ) ? CAPTLC_Chat_Buttons::get_settings() : array();
		$message  = ! empty( $settings['greeting_message'] ) ? $settings['greeting_message'] : __( 'Hi there! How can we help? 👋', 'captain-live-chat-pro' );
		?>
		<div
			id="captlc-proactive-greeting"
			class="captlc-proactive-bubble"
			role="status"
			aria-live="polite"
			tabindex="0"
			style="display:none;"
		>
			<div class="captlc-proactive-bubble__content">
				<span class="captlc-proactive-bubble__text"><?php echo esc_html( $message ); ?></span>
				<button
					type="button"
					class="captlc-proactive-bubble__close"
					aria-label="<?php esc_attr_e( 'Close greeting', 'captain-live-chat-pro' ); ?>"
				>
					✕
				</button>
			</div>
			<div class="captlc-proactive-bubble__arrow"></div>
		</div>
		<?php
	}
}
