<?php
/**
 * Runs when Captain Live Chat Pro is deleted from the Plugins screen.
 *
 * Pro stores encrypted AI provider keys, AI settings, the knowledge base
 * and white-label settings. All of it is removed on uninstall - API keys in
 * particular must never be left behind in the database.
 *
 * @package captain-live-chat-pro
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Deletes every Pro option and transient for the current site.
 *
 * @return void
 */
function captlc_pro_uninstall_site() {
	global $wpdb;

	$options = array(
		'captlc_ai_providers',
		'captlc_ai_general',
		'captlc_ai_knowledge',
		'captlc_ai_last_error',
		'captlc_ai_kb_lock',
		'captlc_white_label_name',
		'captlc_white_label_logo_url',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Every agent's own alert preferences (user meta).
	delete_metadata( 'user', 0, 'captlc_pro_alerts', '', true );

	// Daily-usage counters and error-throttle flags (transients).
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_captlc_ai_usage_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_captlc_ai_usage_' ) . '%',
			$wpdb->esc_like( '_transient_captlc_ai_err_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_captlc_ai_err_' ) . '%'
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}

if ( is_multisite() ) {
	$captlc_pro_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $captlc_pro_site_ids as $captlc_pro_site_id ) {
		switch_to_blog( $captlc_pro_site_id );
		captlc_pro_uninstall_site();
		restore_current_blog();
	}
} else {
	captlc_pro_uninstall_site();
}
