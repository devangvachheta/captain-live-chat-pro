<?php
/**
 * Pro-only chat export logic (bulk CSV export + single-thread transcript
 * export). Called directly (not via a separate AJAX action) from the
 * free plugin's CAPTLC_History::export_history() / export_thread_transcript(),
 * which class_exists()-gate the call the same way CAPTLC_Ajax defers to
 * CAPTLC_AI::auto_reply() for the AI add-on. This file only ever loads
 * when the Pro plugin is installed and active.
 *
 * @package captain-live-chat-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- reads the free plugin's own custom tables, which have no WordPress API; export must always read fresh rows.

/**
 * Class CAPTLC_Pro_Export
 */
class CAPTLC_Pro_Export {

	/**
	 * Builds a CSV of filtered threads + their messages.
	 *
	 * @param array $args Filters: thread_id, search, status, agent, date_from and date_to (dates as Y-m-d).
	 * @return string CSV content.
	 */
	public static function build_history_csv( $args ) {
		global $wpdb;

		$threads_table  = CAPTLC_DB::threads_table();
		$messages_table = CAPTLC_DB::messages_table();

		$thread_id = isset( $args['thread_id'] ) ? absint( $args['thread_id'] ) : 0;
		$search    = isset( $args['search'] ) ? (string) $args['search'] : '';
		$status    = isset( $args['status'] ) ? (string) $args['status'] : '';
		$agent     = isset( $args['agent'] ) ? (string) $args['agent'] : '';
		$date_from = isset( $args['date_from'] ) ? self::valid_date( (string) $args['date_from'] ) : '';
		$date_to   = isset( $args['date_to'] ) ? self::valid_date( (string) $args['date_to'] ) : '';

		// Soft-deleted conversations must never leak into an export.
		$where  = array( 't.deleted_at IS NULL' );
		$params = array();

		if ( $thread_id ) {
			$where[]  = 't.id = %d';
			$params[] = $thread_id;
		}

		if ( $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(t.visitor_name LIKE %s OR t.visitor_email LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		if ( $status ) {
			$where[]  = 't.status = %s';
			$params[] = $status;
		}

		if ( $agent ) {
			if ( 'unassigned' === $agent ) {
				$where[] = '(t.assigned_agent_id IS NULL OR t.assigned_agent_id = 0)';
			} elseif ( is_numeric( $agent ) ) {
				$where[]  = 't.assigned_agent_id = %d';
				$params[] = absint( $agent );
			}
		}

		if ( $date_from ) {
			$where[]  = 't.created_at >= %s';
			$params[] = $date_from . ' 00:00:00';
		}

		if ( $date_to ) {
			$where[]  = 't.created_at <= %s';
			$params[] = $date_to . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT t.* FROM {$threads_table} t WHERE {$where_sql} ORDER BY t.created_at DESC LIMIT 2000";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$threads = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Fetch every thread's messages in a few batched queries instead
		// of one query per thread.
		$messages_by_thread = array();
		$thread_ids         = array_map( 'intval', wp_list_pluck( $threads, 'id' ) );

		foreach ( array_chunk( $thread_ids, 500 ) as $id_batch ) {
			$placeholders = implode( ',', array_fill( 0, count( $id_batch ), '%d' ) );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- table name comes from the free plugin's helper; the IN() list is built from %d placeholders only.
			$batch = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$messages_table} WHERE thread_id IN ({$placeholders}) ORDER BY id ASC", $id_batch ) );

			foreach ( (array) $batch as $msg ) {
				$messages_by_thread[ (int) $msg->thread_id ][] = $msg;
			}
		}

		$rows   = array();
		$rows[] = array( 'Thread ID', 'Visitor Name', 'Email', 'Status', 'Browser', 'Device', 'Source URL', 'Date', 'Sender', 'Message' );

		foreach ( $threads as $thread ) {
			$msgs = isset( $messages_by_thread[ (int) $thread->id ] ) ? $messages_by_thread[ (int) $thread->id ] : array();

			$base = array(
				$thread->id,
				$thread->visitor_name,
				$thread->visitor_email,
				$thread->status,
				$thread->browser,
				$thread->device,
				$thread->source_url,
				$thread->created_at,
			);

			if ( empty( $msgs ) ) {
				$rows[] = array_merge( $base, array( '', '' ) );
				continue;
			}

			foreach ( $msgs as $msg ) {
				$rows[] = array_merge( $base, array( $msg->sender_type, $msg->message ) );
			}
		}

		if ( count( $threads ) >= 2000 ) {
			$rows[] = array(
				__( 'NOTE: Maximum export limit of 2,000 conversations reached. Please use date or status filters to export more records.', 'captain-live-chat-pro' ),
				'', '', '', '', '', '', '', '', ''
			);
		}

		// UTF-8 byte order mark so Excel opens non-English names correctly.
		$csv = "\xEF\xBB\xBF";

		foreach ( $rows as $row ) {
			$escaped = array_map(
				static function ( $field ) {
					$field = str_replace( '"', '""', self::neutralize_formula( (string) $field ) );
					return '"' . $field . '"';
				},
				$row
			);
			$csv    .= implode( ',', $escaped ) . "\r\n";
		}

		return $csv;
	}

	/**
	 * Stops a spreadsheet from running a cell as a formula (CSV injection).
	 * Visitor-controlled text that starts with = + - @ (or a tab / carriage
	 * return) gets a leading apostrophe, which Excel and Sheets treat as
	 * "plain text".
	 *
	 * @param string $value Raw cell value.
	 * @return string
	 */
	private static function neutralize_formula( $value ) {
		if ( '' !== $value && false !== strpos( "=+-@\t\r", $value[0] ) ) {
			return "'" . $value;
		}

		return $value;
	}

	/**
	 * Returns the value when it is a real Y-m-d date, otherwise ''.
	 *
	 * @param string $value Date string.
	 * @return string
	 */
	private static function valid_date( $value ) {
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Builds a full formatted transcript for a single thread.
	 *
	 * @param int $thread_id Thread ID.
	 * @return array|WP_Error {
	 *     @type string $transcript Plain-text transcript.
	 *     @type string $filename   Suggested download filename.
	 * } or WP_Error if the thread doesn't exist.
	 */
	public static function build_thread_transcript( $thread_id ) {
		global $wpdb;
		$threads_table  = CAPTLC_DB::threads_table();
		$messages_table = CAPTLC_DB::messages_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$thread = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$threads_table} WHERE id = %d AND deleted_at IS NULL", $thread_id ) );
		if ( ! $thread ) {
			return new WP_Error( 'thread_not_found', __( 'Thread not found.', 'captain-live-chat-pro' ) );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$messages = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$messages_table} WHERE thread_id = %d ORDER BY id ASC", $thread_id ) );

		$assigned_agent = __( 'Unassigned', 'captain-live-chat-pro' );
		if ( $thread->assigned_agent_id ) {
			$agent_user = get_userdata( $thread->assigned_agent_id );
			if ( $agent_user ) {
				$assigned_agent = $agent_user->display_name;
			}
		}

		$lines   = array();
		$lines[] = '============================================================';
		$lines[] = __( 'CAPTAIN LIVE CHAT - CONVERSATION TRANSCRIPT', 'captain-live-chat-pro' );
		$lines[] = '============================================================';
		$lines[] = __( 'Conversation ID :', 'captain-live-chat-pro' ) . ' #' . $thread->id;
		$lines[] = __( 'Visitor Name    :', 'captain-live-chat-pro' ) . ' ' . ( $thread->visitor_name ? $thread->visitor_name : __( 'Anonymous Visitor', 'captain-live-chat-pro' ) );
		$lines[] = __( 'Visitor Email   :', 'captain-live-chat-pro' ) . ' ' . ( $thread->visitor_email ? $thread->visitor_email : __( 'None', 'captain-live-chat-pro' ) );
		$lines[] = __( 'Status          :', 'captain-live-chat-pro' ) . ' ' . ucfirst( $thread->status );
		$lines[] = __( 'Assigned Agent  :', 'captain-live-chat-pro' ) . ' ' . $assigned_agent;
		$lines[] = __( 'Date Started    :', 'captain-live-chat-pro' ) . ' ' . $thread->created_at;
		$lines[] = __( 'Browser / Device:', 'captain-live-chat-pro' ) . ' ' . ( implode( ' / ', array_filter( array( $thread->browser, $thread->device ) ) ) ? implode( ' / ', array_filter( array( $thread->browser, $thread->device ) ) ) : __( 'Unknown', 'captain-live-chat-pro' ) );
		if ( ! empty( $thread->source_url ) ) {
			$lines[] = __( 'Source URL      :', 'captain-live-chat-pro' ) . ' ' . $thread->source_url;
		}
		$lines[] = __( 'Total Messages  :', 'captain-live-chat-pro' ) . ' ' . count( $messages );
		$lines[] = '============================================================';
		$lines[] = '';

		if ( empty( $messages ) ) {
			$lines[] = __( '[No messages recorded in this conversation]', 'captain-live-chat-pro' );
		} else {
			$agent_names = array();

			foreach ( $messages as $msg ) {
				if ( 'visitor' === $msg->sender_type ) {
					$sender = $thread->visitor_name ? $thread->visitor_name : __( 'Visitor', 'captain-live-chat-pro' );
				} elseif ( 'system' === $msg->sender_type ) {
					$sender = __( 'System', 'captain-live-chat-pro' );
				} else {
					// The messages table stores only the agent's user id; look the
					// name up. No id means the AI agent wrote it; id 0 is the automatic
					// offline reply.
					$sender = 'Agent';

					if ( ! empty( $msg->sender_id ) ) {
						if ( ! isset( $agent_names[ (int) $msg->sender_id ] ) ) {
							$agent                                = get_userdata( (int) $msg->sender_id );
							$agent_names[ (int) $msg->sender_id ] = $agent ? $agent->display_name : 'Agent';
						}

						$sender = $agent_names[ (int) $msg->sender_id ];
					} elseif ( null === $msg->sender_id ) {
						$sender = 'AI Agent';
					} else {
						// sender_id 0: the automatic offline reply.
						$sender = 'Automatic reply';
					}
				}

				$time    = $msg->created_at ? $msg->created_at : '';
				$lines[] = sprintf( '[%s] %s:', $time, $sender );
				$lines[] = $msg->message;
				if ( ! empty( $msg->attachment_url ) ) {
					$lines[] = '  Attachment: ' . $msg->attachment_url;
				}
				$lines[] = '';
			}
		}

		$lines[] = '============================================================';
		$lines[] = 'End of Transcript - Exported on ' . current_time( 'mysql' );
		$lines[] = '============================================================';

		$raw_slug   = sanitize_title( $thread->visitor_name ? $thread->visitor_name : 'visitor' );
		$clean_slug = trim( preg_replace( '/%[0-9a-fA-F]{2}/', '', $raw_slug ), '-' );
		$slug       = ! empty( $clean_slug ) ? $clean_slug : 'visitor';

		return array(
			'transcript' => implode( "\r\n", $lines ),
			'filename'   => sprintf( 'chat-transcript-%d-%s.txt', $thread->id, $slug ),
		);
	}
}
