<?php
/**
 * Post-chat Survey and CSAT Rating (Pro).
 *
 * @package captain-live-chat-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CAPTLC_Pro_Survey
 */
class CAPTLC_Pro_Survey {

	/**
	 * Table name without prefix.
	 */
	const TABLE_NAME = 'captlc_surveys';

	/**
	 * Cached table existence flag.
	 *
	 * @var bool|null
	 */
	private static $table_exists = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( get_option( 'captlc_pro_survey_db_ver' ) !== CAPTLC_PRO_VERSION ) {
			self::maybe_create_table();
			update_option( 'captlc_pro_survey_db_ver', CAPTLC_PRO_VERSION );
		}

		// Expose survey settings to frontend widget.
		add_filter( 'captlc_widget_data', array( $this, 'add_widget_data' ) );

		// Hook into analytics CSAT computation.
		add_filter( 'captlc_analytics_csat', array( $this, 'get_csat_stats' ), 10, 2 );

		// AJAX handlers for rating submission.
		add_action( 'wp_ajax_captlc_submit_survey_rating', array( $this, 'submit_survey_rating' ) );
		add_action( 'wp_ajax_nopriv_captlc_submit_survey_rating', array( $this, 'submit_survey_rating' ) );

		// Hook into settings sanitization.
		add_filter( 'captlc_sanitize_settings', array( $this, 'sanitize_settings' ), 10, 2 );

		// Purge surveys when a thread is deleted.
		add_action( 'captlc_delete_thread', array( $this, 'on_thread_deleted' ) );
	}

	/**
	 * Sanitizes survey settings when settings form is saved.
	 *
	 * @param array $settings Current sanitized settings.
	 * @param array $raw      Raw POST data.
	 * @return array
	 */
	public function sanitize_settings( $settings, $raw ) {
		if ( isset( $raw['survey_enabled'] ) ) {
			$settings['survey_enabled'] = ! empty( $raw['survey_enabled'] ) ? 1 : 0;
		}
		if ( isset( $raw['survey_rating_type'] ) ) {
			$allowed_types = array( 'stars', 'thumbs', 'emoji' );
			$clean_type    = sanitize_key( $raw['survey_rating_type'] );
			$settings['survey_rating_type'] = in_array( $clean_type, $allowed_types, true ) ? $clean_type : 'stars';
		}
		if ( isset( $raw['survey_ask_resolved'] ) ) {
			$settings['survey_ask_resolved'] = ! empty( $raw['survey_ask_resolved'] ) ? 1 : 0;
		}
		if ( isset( $raw['survey_comment_mode'] ) ) {
			$allowed_modes = array( 'optional', 'hidden', 'required' );
			$clean_mode    = sanitize_key( $raw['survey_comment_mode'] );
			$settings['survey_comment_mode'] = in_array( $clean_mode, $allowed_modes, true ) ? $clean_mode : 'optional';
		}
		if ( isset( $raw['survey_title'] ) ) {
			$settings['survey_title'] = sanitize_text_field( wp_unslash( $raw['survey_title'] ) );
		}
		if ( isset( $raw['survey_resolved_title'] ) ) {
			$settings['survey_resolved_title'] = sanitize_text_field( wp_unslash( $raw['survey_resolved_title'] ) );
		}
		if ( isset( $raw['survey_thank_you'] ) ) {
			$settings['survey_thank_you'] = sanitize_text_field( wp_unslash( $raw['survey_thank_you'] ) );
		}

		return $settings;
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
	 * Creates the surveys table if it doesn't exist yet.
	 *
	 * @return void
	 */
	public static function maybe_create_table() {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			thread_id bigint(20) unsigned NOT NULL,
			visitor_id varchar(64) NOT NULL DEFAULT '',
			rating int(3) NOT NULL DEFAULT 5,
			rating_type varchar(20) NOT NULL DEFAULT 'stars',
			comment text NULL,
			issue_resolved tinyint(1) NULL DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY thread_id (thread_id),
			KEY visitor_id (visitor_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Returns survey settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$all = class_exists( 'CAPTLC_Settings' ) ? CAPTLC_Settings::get_settings() : array();

		$defaults = array(
			'survey_enabled'        => true,
			'survey_rating_type'    => 'stars', // 'stars', 'thumbs', 'emoji'
			'survey_ask_resolved'   => true,
			'survey_comment_mode'   => 'optional', // 'optional', 'hidden', 'required'
			'survey_title'          => __( 'How was your experience?', 'captain-live-chat-pro' ),
			'survey_resolved_title' => __( 'Was your issue resolved?', 'captain-live-chat-pro' ),
			'survey_thank_you'      => __( 'Thank you for your feedback!', 'captain-live-chat-pro' ),
		);

		return wp_parse_args( $all, $defaults );
	}

	/**
	 * Passes survey configuration into frontend widget captlcData.
	 *
	 * @param array $data Existing widget data.
	 * @return array
	 */
	public function add_widget_data( $data ) {
		$settings = self::get_settings();

		$data['survey'] = array(
			'enabled'       => ! empty( $settings['survey_enabled'] ),
			'ratingType'    => ! empty( $settings['survey_rating_type'] ) ? $settings['survey_rating_type'] : 'stars',
			'askResolved'   => ! empty( $settings['survey_ask_resolved'] ),
			'commentMode'   => ! empty( $settings['survey_comment_mode'] ) ? $settings['survey_comment_mode'] : 'optional',
			'title'         => ! empty( $settings['survey_title'] ) ? $settings['survey_title'] : __( 'How was your experience?', 'captain-live-chat-pro' ),
			'resolvedTitle' => ! empty( $settings['survey_resolved_title'] ) ? $settings['survey_resolved_title'] : __( 'Was your issue resolved?', 'captain-live-chat-pro' ),
			'thankYou'      => ! empty( $settings['survey_thank_you'] ) ? $settings['survey_thank_you'] : __( 'Thank you for your feedback!', 'captain-live-chat-pro' ),
			'emailTranscriptEnabled' => ! empty( $settings['email_transcript_enabled'] ),
		);

		return $data;
	}

	/**
	 * Handles AJAX rating submission from the widget.
	 *
	 * @return void
	 */
	public function submit_survey_rating() {
		check_ajax_referer( CAPTLC_Ajax::NONCE_ACTION, 'nonce' );

		if ( class_exists( 'CAPTLC_Ajax' ) && ! CAPTLC_Ajax::enforce_rate_limit( 'survey_submit', 10, 60 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many survey submissions. Please try again shortly.', 'captain-live-chat-pro' ) ), 429 );
		}

		$thread_id = isset( $_POST['thread_id'] ) ? absint( $_POST['thread_id'] ) : 0;
		if ( 0 === $thread_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing thread ID.', 'captain-live-chat-pro' ) ), 400 );
		}

		$thread = CAPTLC_DB::get_thread( $thread_id );
		if ( ! $thread ) {
			wp_send_json_error( array( 'message' => __( 'Chat thread not found.', 'captain-live-chat-pro' ) ), 404 );
		}

		$visitor_id = isset( $_POST['visitor_id'] ) ? sanitize_text_field( wp_unslash( $_POST['visitor_id'] ) ) : '';
		if ( '' === $visitor_id || ! hash_equals( (string) $thread->visitor_id, $visitor_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized thread access.', 'captain-live-chat-pro' ) ), 403 );
		}

		global $wpdb;
		$table_name = self::table_name();

		// Check if survey already submitted for this thread.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table_name} WHERE thread_id = %d LIMIT 1", $thread_id ) );
		if ( $existing ) {
			wp_send_json_success( array( 'message' => __( 'Feedback already recorded.', 'captain-live-chat-pro' ) ) );
		}

		$rating_type = isset( $_POST['rating_type'] ) ? sanitize_key( $_POST['rating_type'] ) : 'stars';
		$rating      = isset( $_POST['rating'] ) ? (int) $_POST['rating'] : 5;

		if ( 'thumbs' === $rating_type ) {
			$rating = $rating > 0 ? 1 : 0;
		} else {
			// Stars or emoji scale: 1 to 5.
			$rating = max( 1, min( 5, $rating ) );
		}

		$comment = isset( $_POST['comment'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ), 0, 500 ) : '';

		$issue_resolved = null;
		if ( isset( $_POST['issue_resolved'] ) && '' !== $_POST['issue_resolved'] ) {
			$issue_resolved = ! empty( $_POST['issue_resolved'] ) && '0' !== $_POST['issue_resolved'] && 'false' !== $_POST['issue_resolved'] ? 1 : 0;
		}

		$wpdb->insert(
			$table_name,
			array(
				'thread_id'      => $thread_id,
				'visitor_id'     => $visitor_id,
				'rating'         => $rating,
				'rating_type'    => $rating_type,
				'comment'        => $comment,
				'issue_resolved' => $issue_resolved,
				'created_at'     => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%d', '%s', '%s', null === $issue_resolved ? null : '%d', '%s' )
		);

		// Audit activity log.
		CAPTLC_DB::log_activity(
			$thread_id,
			'survey_submitted',
			'visitor',
			null,
			$thread->visitor_name ? $thread->visitor_name : 'Visitor',
			'',
			sprintf( '%s (%d/5)', $rating_type, $rating ),
			array(
				'rating'         => $rating,
				'rating_type'    => $rating_type,
				'comment'        => $comment,
				'issue_resolved' => $issue_resolved,
			)
		);

		/**
		 * Action triggered when post-chat survey rating is submitted.
		 *
		 * @param int   $thread_id Thread ID.
		 * @param int   $rating Rating score.
		 * @param array $details Submission details.
		 */
		do_action(
			'captlc_survey_submitted',
			$thread_id,
			$rating,
			array(
				'rating_type'    => $rating_type,
				'comment'        => $comment,
				'issue_resolved' => $issue_resolved,
			)
		);

		wp_send_json_success( array( 'message' => __( 'Thank you for your rating!', 'captain-live-chat-pro' ) ) );
	}

	/**
	 * Computes CSAT statistics for the Analytics module based on selected date range.
	 *
	 * @param mixed  $prev_csat Previous CSAT value (null by default in Free).
	 * @param string $range Selected date range ('week', 'month', 'year', 'all').
	 * @return array CSAT summary.
	 */
	public function get_csat_stats( $prev_csat, $range = 'week' ) {
		global $wpdb;
		$table_name = self::table_name();

		// Check if table exists (prepared and statically cached).
		if ( null === self::$table_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::$table_exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name );
		}
		if ( ! self::$table_exists ) {
			return null;
		}

		$where = '1=1';
		switch ( $range ) {
			case 'today':
				$where = $wpdb->prepare( 'created_at >= %s', gmdate( 'Y-m-d 00:00:00' ) );
				break;
			case 'week':
				$where = $wpdb->prepare( 'created_at >= %s', gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) );
				break;
			case 'month':
				$where = $wpdb->prepare( 'created_at >= %s', gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) );
				break;
			case 'year':
				$where = $wpdb->prepare( 'created_at >= %s', gmdate( 'Y-m-d H:i:s', time() - 365 * DAY_IN_SECONDS ) );
				break;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT rating, rating_type, issue_resolved FROM {$table_name} WHERE {$where}" );

		if ( empty( $rows ) ) {
			return array(
				'score'         => null,
				'total_ratings' => 0,
				'avg_rating'    => null,
				'resolved_pct'  => null,
				'breakdown'     => array(
					'positive' => 0,
					'neutral'  => 0,
					'negative' => 0,
				),
			);
		}

		$total        = count( $rows );
		$positive     = 0;
		$sum_stars    = 0;
		$stars_count  = 0;
		$resolved_yes = 0;
		$resolved_tot = 0;

		$breakdown = array(
			'positive' => 0,
			'neutral'  => 0,
			'negative' => 0,
		);

		foreach ( $rows as $r ) {
			$val = (int) $r->rating;
			if ( 'thumbs' === $r->rating_type ) {
				if ( 1 === $val ) {
					$positive++;
					$breakdown['positive']++;
				} else {
					$breakdown['negative']++;
				}
				$sum_stars += ( 1 === $val ? 5 : 1 );
				$stars_count++;
			} else {
				// 1-5 scale (stars or emoji)
				if ( $val >= 4 ) {
					$positive++;
					$breakdown['positive']++;
				} elseif ( 3 === $val ) {
					$breakdown['neutral']++;
				} else {
					$breakdown['negative']++;
				}
				$sum_stars += $val;
				$stars_count++;
			}

			if ( null !== $r->issue_resolved ) {
				$resolved_tot++;
				if ( (int) $r->issue_resolved === 1 ) {
					$resolved_yes++;
				}
			}
		}

		$score        = round( ( $positive / $total ) * 100 );
		$avg_rating   = $stars_count > 0 ? round( $sum_stars / $stars_count, 1 ) : 5.0;
		$resolved_pct = $resolved_tot > 0 ? round( ( $resolved_yes / $resolved_tot ) * 100, 1 ) : null;

		return array(
			'score'         => $score,
			'total_ratings' => $total,
			'avg_rating'    => $avg_rating,
			'resolved_pct'  => $resolved_pct,
			'breakdown'     => $breakdown,
		);
	}

	/**
	 * Deletes orphan surveys when a conversation thread is purged.
	 *
	 * @param int $thread_id Deleted thread ID.
	 * @return void
	 */
	public function on_thread_deleted( $thread_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( self::table_name(), array( 'thread_id' => absint( $thread_id ) ), array( '%d' ) );
	}
}
