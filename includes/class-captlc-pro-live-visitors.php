<?php
/**
 * Live Visitors Tracking and Real-Time Chat Initiation (Pro).
 *
 * @package captain-live-chat-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CAPTLC_Pro_Live_Visitors
 */
class CAPTLC_Pro_Live_Visitors {

	/**
	 * Table name without prefix.
	 */
	const TABLE_NAME = 'captlc_live_visitors';

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( get_option( 'captlc_pro_live_visitors_db_ver' ) !== CAPTLC_PRO_VERSION ) {
			self::maybe_create_table();
			update_option( 'captlc_pro_live_visitors_db_ver', CAPTLC_PRO_VERSION );
		}

		// Enqueue tracker on frontend if enabled.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracker' ) );

		// Tracking endpoints.
		add_action( 'wp_ajax_captlc_live_visitor_ping', array( $this, 'visitor_ping' ) );
		add_action( 'wp_ajax_nopriv_captlc_live_visitor_ping', array( $this, 'visitor_ping' ) );

		add_action( 'wp_ajax_captlc_live_visitor_leave', array( $this, 'visitor_leave' ) );
		add_action( 'wp_ajax_nopriv_captlc_live_visitor_leave', array( $this, 'visitor_leave' ) );

		// Admin management endpoints.
		add_action( 'wp_ajax_captlc_get_live_visitors', array( $this, 'get_live_visitors' ) );
		add_action( 'wp_ajax_captlc_start_visitor_chat', array( $this, 'start_visitor_chat' ) );
		add_action( 'wp_ajax_captlc_toggle_live_visitors', array( $this, 'toggle_live_visitors' ) );

		// Hook into settings sanitization.
		add_filter( 'captlc_sanitize_settings', array( $this, 'sanitize_settings' ), 10, 2 );

		// Cron cleanup handler (scheduled upon plugin activation).
		add_action( 'captlc_hourly_cleanup', array( $this, 'cleanup_stale_visitors' ) );
	}

	/**
	 * Returns full table name with prefix.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Creates the table if not present.
	 *
	 * @return void
	 */
	public static function maybe_create_table() {
		global $wpdb;
		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			visitor_id varchar(64) NOT NULL,
			session_token varchar(64) NOT NULL DEFAULT '',
			ip_hash varchar(64) NOT NULL DEFAULT '',
			current_url text NOT NULL,
			page_title text NOT NULL,
			referrer text NOT NULL,
			device varchar(20) NOT NULL DEFAULT 'desktop',
			browser varchar(50) NOT NULL DEFAULT 'unknown',
			os varchar(50) NOT NULL DEFAULT 'unknown',
			country varchar(10) NOT NULL DEFAULT '',
			city varchar(100) NOT NULL DEFAULT '',
			visitor_name varchar(100) NOT NULL DEFAULT '',
			visitor_email varchar(150) NOT NULL DEFAULT '',
			thread_id bigint(20) unsigned NULL DEFAULT NULL,
			page_count int(11) NOT NULL DEFAULT 1,
			first_seen datetime NOT NULL,
			last_seen datetime NOT NULL,
			is_online tinyint(1) NOT NULL DEFAULT 1,
			pending_invite text NULL,
			PRIMARY KEY (id),
			UNIQUE KEY visitor_id (visitor_id),
			KEY last_seen (last_seen),
			KEY is_online (is_online),
			KEY thread_id (thread_id),
			KEY last_online (is_online, last_seen)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Settings for live visitors.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$all = class_exists( 'CAPTLC_Settings' ) ? CAPTLC_Settings::get_settings() : array();

		$defaults = array(
			'live_visitors_enabled'       => false,
			'live_visitors_exclude_admin' => true,
			'live_visitors_ping_sec'      => 30,
		);

		return wp_parse_args( $all, $defaults );
	}

	/**
	 * Sanitizes live visitor settings when settings form is saved.
	 *
	 * @param array $settings Current sanitized settings.
	 * @param array $raw      Raw POST data.
	 * @return array
	 */
	public function sanitize_settings( $settings, $raw ) {
		if ( isset( $raw['live_visitors_enabled'] ) ) {
			$settings['live_visitors_enabled'] = ! empty( $raw['live_visitors_enabled'] ) ? 1 : 0;
		}
		if ( isset( $raw['live_visitors_exclude_admin'] ) ) {
			$settings['live_visitors_exclude_admin'] = ! empty( $raw['live_visitors_exclude_admin'] ) ? 1 : 0;
		}
		if ( isset( $raw['live_visitors_ping_sec'] ) ) {
			$settings['live_visitors_ping_sec'] = max( 10, min( 120, absint( $raw['live_visitors_ping_sec'] ) ) );
		}

		return $settings;
	}

	/**
	 * Enqueues lightweight live-tracker.js on frontend.
	 *
	 * @return void
	 */
	public function enqueue_tracker() {
		if ( is_admin() ) {
			return;
		}

		$settings = self::get_settings();
		if ( empty( $settings['live_visitors_enabled'] ) ) {
			return;
		}

		if ( ! apply_filters( 'captlc_live_visitors_tracking_allowed', true ) ) {
			return;
		}

		if ( ! empty( $settings['live_visitors_exclude_admin'] ) && is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_script(
			'captlc-live-tracker',
			CAPTLC_PRO_URL . 'assets/js/live-tracker.js',
			array(),
			CAPTLC_PRO_VERSION,
			true
		);

		wp_localize_script(
			'captlc-live-tracker',
			'captlcLiveTrackerData',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( CAPTLC_Ajax::NONCE_ACTION ),
				'pingInterval' => max( 15, (int) $settings['live_visitors_ping_sec'] ) * 1000,
			)
		);
	}

	/**
	 * Determines browser from User Agent.
	 *
	 * @param string $ua User agent string.
	 * @return string
	 */
	private static function detect_browser( $ua ) {
		if ( preg_match( '/Edge|Edg\//i', $ua ) ) return 'Edge';
		if ( preg_match( '/Chrome/i', $ua ) && ! preg_match( '/Edg|OPR/i', $ua ) ) return 'Chrome';
		if ( preg_match( '/Safari/i', $ua ) && ! preg_match( '/Chrome/i', $ua ) ) return 'Safari';
		if ( preg_match( '/Firefox/i', $ua ) ) return 'Firefox';
		if ( preg_match( '/Opera|OPR/i', $ua ) ) return 'Opera';
		return 'Browser';
	}

	/**
	 * Determines OS from User Agent.
	 *
	 * @param string $ua User agent string.
	 * @return string
	 */
	private static function detect_os( $ua ) {
		if ( preg_match( '/Windows NT 10.0/i', $ua ) ) return 'Windows 10/11';
		if ( preg_match( '/Windows/i', $ua ) ) return 'Windows';
		if ( preg_match( '/Macintosh|Mac OS X/i', $ua ) ) return 'macOS';
		if ( preg_match( '/iPhone|iPad|iPod/i', $ua ) ) return 'iOS';
		if ( preg_match( '/Android/i', $ua ) ) return 'Android';
		if ( preg_match( '/Linux/i', $ua ) ) return 'Linux';
		return 'OS';
	}

	/**
	 * Client IP determination with trusted proxy verification.
	 *
	 * @return string
	 */
	private static function get_client_ip() {
		$remote = ! empty( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$trusted = (array) apply_filters( 'captlc_trusted_proxies', array() );

		if ( '' !== $remote && ! empty( $trusted ) && in_array( $remote, $trusted, true ) ) {
			foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR' ) as $header ) {
				if ( ! empty( $_SERVER[ $header ] ) ) {
					$parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
					$ip    = trim( $parts[0] );

					if ( false !== filter_var( $ip, FILTER_VALIDATE_IP ) ) {
						return $ip;
					}
				}
			}
		}

		return $remote;
	}

	/**
	 * Handles frontend ping.
	 *
	 * @return void
	 */
	public function visitor_ping() {
		check_ajax_referer( CAPTLC_Ajax::NONCE_ACTION, 'nonce' );

		if ( class_exists( 'CAPTLC_Ajax' ) && ! CAPTLC_Ajax::enforce_rate_limit( 'visitor_ping', 60, 60 ) ) {
			wp_send_json_error( array( 'message' => __( 'Rate limit exceeded.', 'captain-live-chat-pro' ) ), 429 );
		}

		$settings = self::get_settings();
		if ( empty( $settings['live_visitors_enabled'] ) ) {
			wp_send_json_success( array( 'tracked' => false ) );
		}

		if ( ! apply_filters( 'captlc_live_visitors_tracking_allowed', true ) ) {
			wp_send_json_success( array( 'tracked' => false ) );
		}

		if ( ! empty( $settings['live_visitors_exclude_admin'] ) && is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			wp_send_json_success( array( 'tracked' => false ) );
		}

		$visitor_id = isset( $_POST['visitor_id'] ) ? sanitize_text_field( wp_unslash( $_POST['visitor_id'] ) ) : '';
		if ( empty( $visitor_id ) || ! wp_is_uuid( $visitor_id, 4 ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid visitor ID.', 'captain-live-chat-pro' ) ), 400 );
		}

		$current_url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		$page_title  = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$referrer    = isset( $_POST['referrer'] ) ? esc_url_raw( wp_unslash( $_POST['referrer'] ) ) : '';
		$device      = isset( $_POST['device'] ) ? sanitize_key( $_POST['device'] ) : 'desktop';
		if ( ! in_array( $device, array( 'desktop', 'mobile', 'tablet' ), true ) ) {
			$device = 'desktop';
		}

		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$browser = self::detect_browser( $ua );
		$os      = self::detect_os( $ua );

		$ip = self::get_client_ip();
		$ip_hash = ( $ip && 'unknown' !== $ip ) ? wp_hash( $ip ) : '';

		// Country detection from Cloudflare headers if from trusted proxy or with valid CF-RAY header.
		$remote = ! empty( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$trusted = (array) apply_filters( 'captlc_trusted_proxies', array() );
		$is_trusted = ( '' !== $remote && ! empty( $trusted ) && in_array( $remote, $trusted, true ) );
		$country = '';
		if ( ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) && ( $is_trusted || ! empty( $_SERVER['HTTP_CF_RAY'] ) ) ) {
			$country = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) );
		}
		$country = apply_filters( 'captlc_live_visitor_country', $country, $ip );

		global $wpdb;
		$table = self::table_name();

		// Check if visitor has an active thread in Free DB.
		$threads_table = CAPTLC_DB::threads_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$thread = $wpdb->get_row( $wpdb->prepare( "SELECT id, visitor_name, visitor_email FROM {$threads_table} WHERE visitor_id = %s AND status = 'open' ORDER BY id DESC LIMIT 1", $visitor_id ) );

		$thread_id     = $thread ? (int) $thread->id : null;
		$visitor_name  = $thread && ! empty( $thread->visitor_name ) ? $thread->visitor_name : '';
		$visitor_email = $thread && ! empty( $thread->visitor_email ) ? $thread->visitor_email : '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, page_count, current_url, pending_invite FROM {$table} WHERE visitor_id = %s", $visitor_id ) );

		$invite_data = null;
		$now_utc = gmdate( 'Y-m-d H:i:s' );

		if ( $existing ) {
			$page_inc = ( $existing->current_url !== $current_url ) ? 1 : 0;
			$new_page_count = (int) $existing->page_count + $page_inc;

			$update_data = array(
				'current_url' => $current_url,
				'page_title'  => $page_title,
				'referrer'    => $referrer,
				'device'      => $device,
				'browser'     => $browser,
				'os'          => $os,
				'ip_hash'     => $ip_hash,
				'last_seen'   => $now_utc,
				'is_online'   => 1,
				'page_count'  => $new_page_count,
			);

			if ( ! empty( $country ) ) {
				$update_data['country'] = $country;
			}
			if ( $thread_id ) {
				$update_data['thread_id'] = $thread_id;
			}
			if ( ! empty( $visitor_name ) ) {
				$update_data['visitor_name'] = $visitor_name;
			}
			if ( ! empty( $visitor_email ) ) {
				$update_data['visitor_email'] = $visitor_email;
			}

			// Check pending invite.
			if ( ! empty( $existing->pending_invite ) ) {
				$invite_data = json_decode( $existing->pending_invite, true );
				// Clear once delivered.
				$update_data['pending_invite'] = null;
			}

			$wpdb->update(
				$table,
				$update_data,
				array( 'visitor_id' => $visitor_id )
			);
		} else {
			// Cap total visitor rows to prevent bloat from fake UUID flooding (max 5,000 rows).
			$total_rows = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $total_rows >= 5000 ) {
				$wpdb->query( "DELETE FROM {$table} ORDER BY last_seen ASC LIMIT 100" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}

			$wpdb->insert(
				$table,
				array(
					'visitor_id'    => $visitor_id,
					'session_token' => wp_generate_password( 32, false ),
					'ip_hash'       => $ip_hash,
					'current_url'   => $current_url,
					'page_title'    => $page_title,
					'referrer'      => $referrer,
					'device'        => $device,
					'browser'       => $browser,
					'os'            => $os,
					'country'       => $country,
					'visitor_name'  => $visitor_name,
					'visitor_email' => $visitor_email,
					'thread_id'     => $thread_id,
					'page_count'    => 1,
					'first_seen'    => $now_utc,
					'last_seen'     => $now_utc,
					'is_online'     => 1,
				)
			);
		}

		wp_send_json_success( array(
			'tracked' => true,
			'invite'  => $invite_data,
		) );
	}

	/**
	 * Handles unload beacon leave event.
	 *
	 * @return void
	 */
	public function visitor_leave() {
		check_ajax_referer( CAPTLC_Ajax::NONCE_ACTION, 'nonce' );

		$visitor_id = isset( $_POST['visitor_id'] ) ? sanitize_text_field( wp_unslash( $_POST['visitor_id'] ) ) : '';
		if ( ! empty( $visitor_id ) && wp_is_uuid( $visitor_id, 4 ) ) {
			global $wpdb;
			$wpdb->update(
				self::table_name(),
				array(
					'is_online' => 0,
					'last_seen' => gmdate( 'Y-m-d H:i:s' ),
				),
				array( 'visitor_id' => $visitor_id )
			);
		}
		wp_send_json_success();
	}

	/**
	 * Admin endpoint to list live online visitors.
	 *
	 * @return void
	 */
	public function get_live_visitors() {
		check_ajax_referer( CAPTLC_Ajax::NONCE_ACTION, 'nonce' );

		if ( ! is_user_logged_in() || ! CAPTLC_Roles::can_reply( get_current_user_id() ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'captain-live-chat-pro' ) ), 403 );
		}

		global $wpdb;
		$table = self::table_name();

		// Threshold: seen within last 60 seconds (UTC) and is_online = 1.
		$threshold = gmdate( 'Y-m-d H:i:s', time() - 60 );

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$filter_device = isset( $_POST['device'] ) ? sanitize_key( $_POST['device'] ) : '';
		$filter_status = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : ''; // 'chatting' or 'browsing'

		$where = $wpdb->prepare( "last_seen >= %s AND is_online = 1", $threshold );

		if ( ! empty( $search ) ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$where .= $wpdb->prepare( " AND (visitor_name LIKE %s OR visitor_email LIKE %s OR current_url LIKE %s OR page_title LIKE %s OR visitor_id LIKE %s)", $like, $like, $like, $like, $like );
		}

		if ( ! empty( $filter_device ) && in_array( $filter_device, array( 'desktop', 'mobile', 'tablet' ), true ) ) {
			$where .= $wpdb->prepare( " AND device = %s", $filter_device );
		}

		if ( 'chatting' === $filter_status ) {
			$where .= " AND thread_id IS NOT NULL";
		} elseif ( 'browsing' === $filter_status ) {
			$where .= " AND thread_id IS NULL";
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where} ORDER BY last_seen DESC LIMIT 100" );

		// Compute summary counts in a single query using (is_online, last_seen) composite index.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$counts = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) AS total_online, SUM(CASE WHEN thread_id IS NOT NULL THEN 1 ELSE 0 END) AS total_chatting FROM {$table} WHERE is_online = 1 AND last_seen >= %s",
			$threshold
		) );

		$total_online   = $counts ? (int) $counts->total_online : 0;
		$total_chatting = $counts ? (int) $counts->total_chatting : 0;
		$total_browsing = max( 0, $total_online - $total_chatting );

		$visitors = array();
		$now_ts   = time();

		foreach ( $rows as $r ) {
			$first_ts     = strtotime( $r->first_seen . ' UTC' );
			$duration_sec = max( 0, $now_ts - $first_ts );

			$visitors[] = array(
				'id'            => (int) $r->id,
				'visitor_id'    => $r->visitor_id,
				'visitor_name'  => ! empty( $r->visitor_name ) ? $r->visitor_name : __( 'Anonymous Visitor', 'captain-live-chat-pro' ),
				'visitor_email' => $r->visitor_email,
				'current_url'   => $r->current_url,
				'page_title'    => ! empty( $r->page_title ) ? $r->page_title : $r->current_url,
				'referrer'      => $r->referrer,
				'device'        => $r->device,
				'browser'       => $r->browser,
				'os'            => $r->os,
				'country'       => $r->country,
				'city'          => $r->city,
				'page_count'    => (int) $r->page_count,
				'first_seen'    => $r->first_seen,
				'last_seen'     => $r->last_seen,
				'time_on_site'  => $duration_sec,
				'thread_id'     => $r->thread_id ? (int) $r->thread_id : null,
				'is_chatting'   => ! empty( $r->thread_id ),
			);
		}

		$settings = class_exists( 'CAPTLC_Settings' ) ? CAPTLC_Settings::get_settings() : array();
		$enabled  = ! empty( $settings['live_visitors_enabled'] );

		wp_send_json_success( array(
			'enabled'        => $enabled,
			'visitors'       => $visitors,
			'total_online'   => $total_online,
			'total_chatting' => $total_chatting,
			'total_browsing' => $total_browsing,
		) );
	}

	/**
	 * Agent initiates a proactive chat with an online visitor.
	 *
	 * @return void
	 */
	public function start_visitor_chat() {
		check_ajax_referer( CAPTLC_Ajax::NONCE_ACTION, 'nonce' );

		if ( ! is_user_logged_in() || ! CAPTLC_Roles::can_reply( get_current_user_id() ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'captain-live-chat-pro' ) ), 403 );
		}

		$visitor_id = isset( $_POST['visitor_id'] ) ? sanitize_text_field( wp_unslash( $_POST['visitor_id'] ) ) : '';
		if ( empty( $visitor_id ) || ! wp_is_uuid( $visitor_id, 4 ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing or invalid visitor ID.', 'captain-live-chat-pro' ) ), 400 );
		}

		$initial_message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		if ( empty( $initial_message ) ) {
			$initial_message = __( 'Hello! How can I help you today?', 'captain-live-chat-pro' );
		}

		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$visitor = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE visitor_id = %s", $visitor_id ) );

		if ( ! $visitor ) {
			wp_send_json_error( array( 'message' => __( 'Visitor not found or no longer online.', 'captain-live-chat-pro' ) ), 404 );
		}

		$current_user = wp_get_current_user();
		$agent_id     = get_current_user_id();
		$agent_name   = $current_user->display_name;

		$threads_table = CAPTLC_DB::threads_table();

		// Check if active thread already exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$thread = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$threads_table} WHERE visitor_id = %s AND status = 'open' ORDER BY id DESC LIMIT 1", $visitor_id ) );

		if ( ! $thread ) {
			$thread_id = CAPTLC_DB::create_thread( array(
				'visitor_id'    => $visitor_id,
				'visitor_name'  => ! empty( $visitor->visitor_name ) ? $visitor->visitor_name : __( 'Visitor', 'captain-live-chat-pro' ),
				'visitor_email' => ! empty( $visitor->visitor_email ) ? $visitor->visitor_email : '',
				'source_url'    => $visitor->current_url,
				'browser'       => $visitor->browser,
				'device'        => $visitor->device,
				'location'      => $visitor->country,
			) );
		} else {
			$thread_id = (int) $thread->id;
		}

		if ( ! $thread_id ) {
			wp_send_json_error( array( 'message' => __( 'Could not create conversation.', 'captain-live-chat-pro' ) ), 500 );
		}

		// Insert agent message.
		$msg_id = CAPTLC_DB::add_message( array(
			'thread_id'   => $thread_id,
			'sender_type' => 'agent',
			'sender_id'   => $agent_id,
			'message'     => $initial_message,
		) );

		// Assign thread to this agent.
		CAPTLC_DB::assign_thread_agent( $thread_id, $agent_id );

		// Set pending invite on the live visitor record so widget auto-opens.
		$invite_payload = json_encode( array(
			'thread_id'   => $thread_id,
			'message_id'  => $msg_id,
			'agent_name'  => $agent_name,
			'message'     => $initial_message,
			'avatar_url'  => get_avatar_url( $agent_id, array( 'size' => 64 ) ),
		) );

		$wpdb->update(
			$table,
			array(
				'thread_id'      => $thread_id,
				'pending_invite' => $invite_payload,
			),
			array( 'visitor_id' => $visitor_id )
		);

		// Log activity.
		CAPTLC_DB::log_activity(
			$thread_id,
			'chat_initiated_by_agent',
			'agent',
			$agent_id,
			$agent_name,
			'',
			__( 'Agent initiated chat from Live Visitors', 'captain-live-chat-pro' )
		);

		wp_send_json_success( array(
			'thread_id' => $thread_id,
			'message'   => __( 'Chat initiated! The visitor has received your message.', 'captain-live-chat-pro' ),
		) );
	}

	/**
	 * Toggles live visitor tracking on/off directly from the Live Visitors page.
	 *
	 * @return void
	 */
	public function toggle_live_visitors() {
		check_ajax_referer( CAPTLC_Ajax::NONCE_ACTION, 'nonce' );

		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'captain-live-chat-pro' ) ), 403 );
		}

		$enabled = isset( $_POST['enabled'] ) && ( 'true' === $_POST['enabled'] || '1' === $_POST['enabled'] || true === $_POST['enabled'] );

		$settings = class_exists( 'CAPTLC_Settings' ) ? CAPTLC_Settings::get_settings() : array();
		$settings['live_visitors_enabled'] = $enabled;

		if ( class_exists( 'CAPTLC_Settings' ) ) {
			CAPTLC_Settings::save_settings( $settings );
		}

		wp_send_json_success( array(
			'enabled' => $enabled,
			'message' => $enabled
				? __( 'Real-Time Live Visitor tracking turned ON.', 'captain-live-chat-pro' )
				: __( 'Real-Time Live Visitor tracking turned OFF.', 'captain-live-chat-pro' ),
		) );
	}

	/**
	 * Cleans up records older than 24 hours (UTC).
	 *
	 * @return void
	 */
	public function cleanup_stale_visitors() {
		global $wpdb;
		$table  = self::table_name();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - 86400 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE last_seen < %s", $cutoff ) );
	}
}
