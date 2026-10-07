import AiSettings from './page/ai_settings/ai_settings.jsx';
import WhiteLabelSettings from './page/white_label/white_label.jsx';
import ProfileAlerts from './page/profile_alerts/profile_alerts.jsx';

/**
 * This script is enqueued as a dependency of the free plugin's own
 * 'captlc-admin-script' (see class-captlc-pro-admin.php), so it
 * always finishes loading after that script has already registered
 * `window.CaptlcExtensions` on the same admin page. Registering here
 * swaps the free plugin's "install the add-on" placeholders for the
 * real Pro pages - see src/extensions/registry.js and extension_slot.jsx
 * in the free plugin.
 */
if ( typeof window !== 'undefined' && window.CaptlcExtensions ) {
	if ( typeof window.CaptlcExtensions.registerPage === 'function' ) {
		window.CaptlcExtensions.registerPage( 'ai-settings', AiSettings );
		window.CaptlcExtensions.registerPage( 'white-label', WhiteLabelSettings );
		// Each agent's own pop-up alert switches on the Profile page.
		window.CaptlcExtensions.registerPage( 'profile-alerts', ProfileAlerts );
	}
	// Lets free-plugin UI (export buttons, the 5-reply Canned Responses cap,
	// etc.) know Pro is active - see src/hooks/use_is_pro.js in the free
	// plugin. This is a UI-only signal; every feature it unlocks still has
	// its real logic gated server-side via class_exists( 'CAPTLC_...' ).
	if ( typeof window.CaptlcExtensions.registerCapability === 'function' ) {
		window.CaptlcExtensions.registerCapability( 'pro' );
	}
}
