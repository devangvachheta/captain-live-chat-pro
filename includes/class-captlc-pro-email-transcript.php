<?php
/**
 * Email Chat Transcript PRO feature.
 *
 * @package captain-live-chat-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CAPTLC_Pro_Email_Transcript
 */
class CAPTLC_Pro_Email_Transcript {

	/**
	 * Rate limit: max emails per thread within window.
	 */
	const MAX_PER_THREAD_HOUR = 3;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Listen for background scheduled sends.
		add_action( 'captlc_deliver_transcript_email', array( $this, 'deliver_scheduled_email' ), 10, 4 );

		// Hook into thread close for auto-send.
		add_action( 'captlc_thread_status_changed', array( $this, 'maybe_auto_send_on_close' ), 10, 3 );

		// Hook into settings sanitization.
		add_filter( 'captlc_sanitize_settings', array( $this, 'sanitize_settings' ), 10, 2 );
	}

	/**
	 * Sanitizes email transcript settings when main settings form is saved.
	 *
	 * @param array $settings Current sanitized settings.
	 * @param array $raw      Raw POST data.
	 * @return array
	 */
	public function sanitize_settings( $settings, $raw ) {
		if ( isset( $raw['email_transcript_enabled'] ) ) {
			$settings['email_transcript_enabled'] = ! empty( $raw['email_transcript_enabled'] ) ? 1 : 0;
		}
		if ( isset( $raw['transcript_from_name'] ) ) {
			$settings['transcript_from_name'] = sanitize_text_field( wp_unslash( $raw['transcript_from_name'] ) );
		}
		if ( isset( $raw['transcript_from_email'] ) ) {
			$settings['transcript_from_email'] = sanitize_email( wp_unslash( $raw['transcript_from_email'] ) );
		}
		if ( isset( $raw['transcript_subject'] ) ) {
			$settings['transcript_subject'] = sanitize_text_field( wp_unslash( $raw['transcript_subject'] ) );
		}
		if ( isset( $raw['transcript_intro'] ) ) {
			$settings['transcript_intro'] = sanitize_textarea_field( wp_unslash( $raw['transcript_intro'] ) );
		}
		if ( isset( $raw['transcript_footer'] ) ) {
			$settings['transcript_footer'] = sanitize_textarea_field( wp_unslash( $raw['transcript_footer'] ) );
		}
		if ( isset( $raw['transcript_bcc_admin'] ) ) {
			$settings['transcript_bcc_admin'] = ! empty( $raw['transcript_bcc_admin'] ) ? 1 : 0;
		}
		if ( isset( $raw['transcript_bcc_email'] ) ) {
			$settings['transcript_bcc_email'] = sanitize_email( wp_unslash( $raw['transcript_bcc_email'] ) );
		}
		if ( isset( $raw['transcript_auto_send'] ) ) {
			$settings['transcript_auto_send'] = ! empty( $raw['transcript_auto_send'] ) ? 1 : 0;
		}

		return $settings;
	}

	/**
	 * Returns email transcript settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$all = class_exists( 'CAPTLC_Settings' ) ? CAPTLC_Settings::get_settings() : array();

		$defaults = array(
			'email_transcript_enabled'       => true,
			'transcript_from_name'           => get_bloginfo( 'name' ),
			'transcript_from_email'          => get_option( 'admin_email' ),
			'transcript_subject'             => __( 'Your chat transcript with {site_name}', 'captain-live-chat-pro' ),
			'transcript_intro'               => __( 'Here is a copy of your recent chat conversation with our team.', 'captain-live-chat-pro' ),
			'transcript_footer'              => __( 'Thank you for contacting us! If you have further questions, feel free to reply directly.', 'captain-live-chat-pro' ),
			'transcript_bcc_admin'           => false,
			'transcript_bcc_email'           => '',
			'transcript_auto_send'           => false,
			'transcript_include_timestamps'  => true,
			'transcript_include_agent_names' => true,
			'transcript_include_attachments' => true,
			'transcript_consent_text'        => __( 'I agree to receive a copy of this chat conversation at this email address.', 'captain-live-chat-pro' ),
			'transcript_require_consent'     => false,
		);

		return wp_parse_args( $all, $defaults );
	}

	/**
	 * Builds beautiful HTML email markup for a thread.
	 *
	 * @param object $thread Thread database row.
	 * @param array  $messages Messages database rows.
	 * @param array  $settings Plugin settings.
	 * @return string HTML email content.
	 */
	public static function build_html_email( $thread, $messages, $settings ) {
		$site_name   = get_bloginfo( 'name' );
		$intro_text  = ! empty( $settings['transcript_intro'] ) ? esc_html( $settings['transcript_intro'] ) : '';
		$footer_text = ! empty( $settings['transcript_footer'] ) ? esc_html( $settings['transcript_footer'] ) : '';

		$include_time   = ! empty( $settings['transcript_include_timestamps'] );
		$include_agents = ! empty( $settings['transcript_include_agent_names'] );
		$include_files  = ! empty( $settings['transcript_include_attachments'] );

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php echo esc_html( $site_name ); ?></title>
		</head>
		<body style="margin:0;padding:24px 0;background-color:#F8FAFC;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0F172A;">
			<table width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color:#F8FAFC;">
				<tr>
					<td align="center">
						<table width="600" border="0" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.06);border:1px solid #E2E8F0;">
							<!-- Header -->
							<tr>
								<td style="background:#0F172A;padding:28px 32px;text-align:left;">
									<h1 style="margin:0;font-size:20px;font-weight:700;color:#FFFFFF;letter-spacing:-0.01em;">
										<?php echo esc_html( $site_name ); ?>
									</h1>
									<p style="margin:6px 0 0;font-size:13px;color:#94A3B8;">
										<?php
										/* translators: %s: Thread ID */
										printf( esc_html__( 'Chat Conversation #%s', 'captain-live-chat-pro' ), esc_html( $thread->id ) );
										?>
										&bull; <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $thread->created_at ) ) ); ?>
									</p>
								</td>
							</tr>

							<!-- Intro -->
							<?php if ( $intro_text ) : ?>
							<tr>
								<td style="padding:24px 32px 12px;font-size:14px;line-height:1.6;color:#334155;border-bottom:1px solid #F1F5F9;">
									<?php echo nl2br( $intro_text ); ?>
								</td>
							</tr>
							<?php endif; ?>

							<!-- Messages Body -->
							<tr>
								<td style="padding:24px 32px;">
									<table width="100%" border="0" cellpadding="0" cellspacing="0">
										<?php
										if ( empty( $messages ) ) :
											?>
											<tr>
												<td style="padding:20px;text-align:center;color:#64748B;font-size:14px;">
													<?php esc_html_e( 'No messages recorded in this chat.', 'captain-live-chat-pro' ); ?>
												</td>
											</tr>
											<?php
										else :
											$agent_names = array();
											foreach ( $messages as $msg ) :
												$is_visitor = 'visitor' === $msg->sender_type;

												// Resolve sender label.
												if ( $is_visitor ) {
													$sender_name = ! empty( $thread->visitor_name ) ? $thread->visitor_name : __( 'You', 'captain-live-chat-pro' );
												} elseif ( 'system' === $msg->sender_type ) {
													$sender_name = __( 'System', 'captain-live-chat-pro' );
												} else {
													if ( ! $include_agents ) {
														$sender_name = __( 'Support Agent', 'captain-live-chat-pro' );
													} elseif ( ! empty( $msg->sender_id ) ) {
														if ( ! isset( $agent_names[ (int) $msg->sender_id ] ) ) {
															$u = get_userdata( (int) $msg->sender_id );
															$agent_names[ (int) $msg->sender_id ] = $u ? $u->display_name : __( 'Agent', 'captain-live-chat-pro' );
														}
														$sender_name = $agent_names[ (int) $msg->sender_id ];
													} elseif ( null === $msg->sender_id ) {
														$sender_name = __( 'AI Assistant', 'captain-live-chat-pro' );
													} else {
														$sender_name = __( 'Auto-reply', 'captain-live-chat-pro' );
													}
												}

												$msg_time = $include_time ? date_i18n( get_option( 'time_format' ), strtotime( $msg->created_at ) ) : '';
												$bg_bubble = $is_visitor ? '#EFF6FF' : '#F1F5F9';
												$align = $is_visitor ? 'right' : 'left';
												?>
												<tr>
													<td style="padding:6px 0;" align="<?php echo esc_attr( $align ); ?>">
														<div style="display:inline-block;max-width:85%;text-align:left;background:<?php echo esc_attr( $bg_bubble ); ?>;padding:12px 16px;border-radius:10px;">
															<div style="font-size:11.5px;font-weight:700;color:<?php echo $is_visitor ? '#1D4ED8' : '#475569'; ?>;margin-bottom:4px;">
																<?php echo esc_html( $sender_name ); ?>
																<?php if ( $msg_time ) : ?>
																	<span style="font-weight:normal;color:#94A3B8;margin-left:6px;"><?php echo esc_html( $msg_time ); ?></span>
																<?php endif; ?>
															</div>
															<div style="font-size:13.5px;line-height:1.5;color:#0F172A;word-break:break-word;">
																<?php echo nl2br( esc_html( $msg->message ) ); ?>
															</div>
															<?php if ( $include_files && ! empty( $msg->attachment_url ) ) : ?>
																<div style="margin-top:8px;font-size:12px;">
																	<a href="<?php echo esc_url( $msg->attachment_url ); ?>" target="_blank" rel="noopener noreferrer" style="color:#2563EB;text-decoration:underline;">
																		📎 <?php esc_html_e( 'View Attached File', 'captain-live-chat-pro' ); ?>
																	</a>
																</div>
															<?php endif; ?>
														</div>
													</td>
												</tr>
												<?php
											endforeach;
										endif;
										?>
									</table>
								</td>
							</tr>

							<!-- Footer -->
							<tr>
								<td style="background:#F8FAFC;padding:20px 32px;border-top:1px solid #E2E8F0;text-align:center;font-size:12px;color:#64748B;">
									<?php if ( $footer_text ) : ?>
										<p style="margin:0 0 8px;"><?php echo esc_html( $footer_text ); ?></p>
									<?php endif; ?>
									<p style="margin:0;font-size:11px;color:#94A3B8;">
										&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php echo esc_html( $site_name ); ?>. <?php esc_html_e( 'All rights reserved.', 'captain-live-chat-pro' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</body>
		</html>
		<?php
		$html = ob_get_clean();

		/**
		 * Filter the full transcript HTML email body.
		 *
		 * @param string $html     Rendered HTML.
		 * @param object $thread   Thread database row.
		 * @param array  $messages Messages array.
		 */
		return (string) apply_filters( 'captlc_transcript_email_body', $html, $thread, $messages );
	}

	/**
	 * Builds plain text alternative.
	 *
	 * @param object $thread Thread row.
	 * @param array  $messages Messages array.
	 * @param array  $settings Settings.
	 * @return string Plain text content.
	 */
	public static function build_plain_text( $thread, $messages, $settings ) {
		if ( class_exists( 'CAPTLC_Pro_Export' ) ) {
			$export = CAPTLC_Pro_Export::build_thread_transcript( $thread->id );
			if ( ! is_wp_error( $export ) && ! empty( $export['transcript'] ) ) {
				return $export['transcript'];
			}
		}

		$lines   = array();
		/* translators: %d: chat thread ID */
		$lines[] = get_bloginfo( 'name' ) . ' - ' . sprintf( __( 'Chat Transcript #%d', 'captain-live-chat-pro' ), $thread->id );
		$lines[] = '========================================';
		foreach ( $messages as $m ) {
			$sender = 'visitor' === $m->sender_type ? ( $thread->visitor_name ? $thread->visitor_name : 'Visitor' ) : 'Agent';
			$lines[] = "[{$m->created_at}] {$sender}: {$m->message}";
		}
		return implode( "\n", $lines );
	}

	/**
	 * Replaces dynamic subject placeholders safely.
	 *
	 * @param string $template Subject template.
	 * @param object $thread Thread row.
	 * @return string
	 */
	public static function format_subject( $template, $thread ) {
		$replacements = array(
			'{site_name}'    => get_bloginfo( 'name' ),
			'{visitor_name}' => ! empty( $thread->visitor_name ) ? $thread->visitor_name : __( 'Visitor', 'captain-live-chat-pro' ),
			'{date}'         => date_i18n( get_option( 'date_format' ) ),
			'{thread_id}'    => '#' . $thread->id,
		);

		$subject = strtr( $template, $replacements );
		$subject = str_replace( array( "\r", "\n" ), '', $subject );

		/**
		 * Filter the email transcript subject line.
		 *
		 * @param string $subject Subject line.
		 * @param object $thread  Thread row.
		 */
		return (string) apply_filters( 'captlc_transcript_email_subject', $subject, $thread );
	}

	/**
	 * Sends the email transcript directly or via background queue.
	 *
	 * @param int    $thread_id  Thread ID.
	 * @param string $recipient  Destination email.
	 * @param bool   $is_admin   Whether triggered by agent.
	 * @param string $note       Optional note from agent.
	 * @return true|WP_Error
	 */
	public static function send_transcript( $thread_id, $recipient, $is_admin = false, $note = '' ) {
		global $wpdb;
		$threads_table  = CAPTLC_DB::threads_table();
		$messages_table = CAPTLC_DB::messages_table();

		$recipient = sanitize_email( $recipient );
		if ( ! is_email( $recipient ) ) {
			return new WP_Error( 'invalid_email', __( 'Please provide a valid email address.', 'captain-live-chat-pro' ) );
		}

		$thread = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$threads_table} WHERE id = %d AND deleted_at IS NULL", $thread_id ) );
		if ( ! $thread ) {
			return new WP_Error( 'thread_not_found', __( 'Chat thread not found.', 'captain-live-chat-pro' ) );
		}

		// Verify recipient: If not triggered by admin and thread has visitor_email, restrict recipient to the thread's visitor email to prevent email relay abuse.
		if ( ! $is_admin && ! empty( $thread->visitor_email ) && is_email( $thread->visitor_email ) ) {
			if ( strtolower( trim( $recipient ) ) !== strtolower( trim( $thread->visitor_email ) ) ) {
				$recipient = sanitize_email( $thread->visitor_email );
			}
		}

		// Rate limiting: max 3 per thread per hour.
		if ( ! $is_admin ) {
			$rate_key = 'captlc_transcript_rate_' . $thread_id;
			$sent_count = (int) get_transient( $rate_key );
			if ( $sent_count >= self::MAX_PER_THREAD_HOUR ) {
				return new WP_Error( 'rate_limited', __( 'Transcript has already been emailed recently. Please check your inbox.', 'captain-live-chat-pro' ) );
			}
		}

		$messages = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$messages_table} WHERE thread_id = %d ORDER BY id ASC", $thread_id ) );
		$settings = self::get_settings();

		$subject = self::format_subject( $settings['transcript_subject'], $thread );
		$html_body = self::build_html_email( $thread, $messages, $settings );

		$from_name  = ! empty( $settings['transcript_from_name'] ) ? preg_replace( '/[\r\n"\'<>]/', '', $settings['transcript_from_name'] ) : get_bloginfo( 'name' );
		$from_email = ! empty( $settings['transcript_from_email'] ) && is_email( $settings['transcript_from_email'] ) ? $settings['transcript_from_email'] : get_option( 'admin_email' );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_email ),
		);

		// BCC Admin if enabled.
		if ( ! empty( $settings['transcript_bcc_admin'] ) ) {
			$bcc = ! empty( $settings['transcript_bcc_email'] ) && is_email( $settings['transcript_bcc_email'] ) ? $settings['transcript_bcc_email'] : get_option( 'admin_email' );
			if ( $bcc && $bcc !== $recipient ) {
				$headers[] = 'Bcc: ' . sanitize_email( $bcc );
			}
		}

		/**
		 * Filter the email transcript headers.
		 *
		 * @param array  $headers Headers list.
		 * @param object $thread  Thread row.
		 */
		$headers = (array) apply_filters( 'captlc_transcript_email_headers', $headers, $thread );

		$sent = wp_mail( $recipient, $subject, $html_body, $headers );

		if ( $sent ) {
			if ( ! $is_admin ) {
				set_transient( $rate_key, $sent_count + 1, HOUR_IN_SECONDS );
			}

			// Audit logging.
			CAPTLC_DB::log_activity(
				$thread_id,
				'transcript_emailed',
				$is_admin ? 'agent' : 'visitor',
				$is_admin ? get_current_user_id() : null,
				$is_admin ? wp_get_current_user()->display_name : 'Visitor',
				'',
				$recipient,
				array( 'note' => $note )
			);

			/**
			 * Action fired when a transcript is successfully sent.
			 *
			 * @param int    $thread_id Thread ID.
			 * @param string $recipient Destination email.
			 */
			do_action( 'captlc_transcript_email_sent', $thread_id, $recipient );

			return true;
		}

		return new WP_Error( 'mail_failed', __( 'Could not deliver transcript email. Please check your mail server configuration.', 'captain-live-chat-pro' ) );
	}

	/**
	 * Auto-send on chat closure if configured.
	 *
	 * @param int    $thread_id  Thread ID.
	 * @param string $new_status New status.
	 * @param string $old_status Old status.
	 */
	public function maybe_auto_send_on_close( $thread_id, $new_status, $old_status ) {
		if ( 'closed' !== $new_status ) {
			return;
		}

		$settings = self::get_settings();
		if ( empty( $settings['email_transcript_enabled'] ) || empty( $settings['transcript_auto_send'] ) ) {
			return;
		}

		global $wpdb;
		$threads_table = CAPTLC_DB::threads_table();
		$thread = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$threads_table} WHERE id = %d", $thread_id ) );

		if ( ! $thread || empty( $thread->visitor_email ) || ! is_email( $thread->visitor_email ) ) {
			return;
		}

		// If consent is required, verify visitor explicitly consented.
		if ( ! empty( $settings['transcript_require_consent'] ) ) {
			$custom = ! empty( $thread->custom_data ) ? json_decode( $thread->custom_data, true ) : array();
			if ( empty( $custom['transcript_consent'] ) ) {
				return;
			}
		}

		// Schedule single event to avoid delaying the response.
		wp_schedule_single_event( time() + 5, 'captlc_deliver_transcript_email', array( $thread_id, $thread->visitor_email, false, '' ) );
	}

	/**
	 * Background delivery callback.
	 */
	public function deliver_scheduled_email( $thread_id, $recipient, $is_admin, $note ) {
		self::send_transcript( $thread_id, $recipient, $is_admin, $note );
	}
}
