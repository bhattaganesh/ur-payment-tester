<?php
/**
 * URPT Local Email Trap & Visual Mailbox Engine
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;

/**
 * Intercepts all outgoing WordPress emails during testing and stores them locally.
 */
class URPT_Mail_Trap {

	/**
	 * Maximum number of trapped emails to retain in storage.
	 */
	const MAX_TRAPPED_EMAILS = 100;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'pre_wp_mail', array( $this, 'intercept_email' ), 10, 2 );
	}

	/**
	 * Checks if the mail trap is currently enabled.
	 *
	 * @return bool True if active.
	 */
	public function is_enabled() {
		return 'yes' === get_option( 'urpt_mail_trap_enabled', 'yes' );
	}

	/**
	 * Intercepts outgoing emails and stores them in options.
	 *
	 * Returning true cancels external transmission while signaling success to callers.
	 *
	 * @param null|bool $return Short-circuit return value.
	 * @param array     $atts Arguments passed to wp_mail.
	 * @return null|bool True to abort external delivery and mark success, null to allow normal delivery.
	 */
	public function intercept_email( $return, $atts ) {
		if ( ! $this->is_enabled() ) {
			return $return;
		}

		$to          = is_array( $atts['to'] ) ? implode( ', ', $atts['to'] ) : $atts['to'];
		$subject     = $atts['subject'] ?? '';
		$message     = $atts['message'] ?? '';
		$headers     = $atts['headers'] ?? array();
		$attachments = $atts['attachments'] ?? array();

		$entry = array(
			'id'          => wp_generate_uuid4(),
			'to'          => $to,
			'subject'     => $subject,
			'body'        => $message,
			'headers'     => is_array( $headers ) ? implode( "\n", $headers ) : (string) $headers,
			'attachments' => is_array( $attachments ) ? $attachments : array( $attachments ),
			'timestamp'   => current_time( 'mysql' ),
		);

		$existing = get_option( 'urpt_trapped_emails', array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		// Keep recent entries capped at maximum limit.
		array_unshift( $existing, $entry );
		if ( count( $existing ) > self::MAX_TRAPPED_EMAILS ) {
			$existing = array_slice( $existing, 0, self::MAX_TRAPPED_EMAILS );
		}

		update_option( 'urpt_trapped_emails', $existing, false );

		// Return true to prevent external transmission while reporting success.
		return true;
	}

	/**
	 * Retrieves all currently trapped emails.
	 *
	 * @return array
	 */
	public function get_trapped_emails() {
		$emails = get_option( 'urpt_trapped_emails', array() );
		return is_array( $emails ) ? $emails : array();
	}

	/**
	 * Clears all trapped emails from storage.
	 *
	 * @return bool
	 */
	public function clear_trapped_emails() {
		return delete_option( 'urpt_trapped_emails' );
	}
}
