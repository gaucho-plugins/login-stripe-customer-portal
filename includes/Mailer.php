<?php
/**
 * Outbound mailer for magic-link emails.
 *
 * Kept separate from PortalController so it can be unit-tested via
 * brain/monkey (which fakes wp_mail) and so the message body is one
 * function we can grep for HTML-structure assertions.
 *
 * @package LSCP
 */

declare( strict_types = 1 );

namespace LSCP;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class Mailer {

	/**
	 * Send the magic-link email. Returns the boolean wp_mail returns.
	 *
	 * @param string $email     Recipient.
	 * @param string $login_url Fully-qualified magic link.
	 */
	public function send_magic_link( string $email, string $login_url ): bool {
		$subject = $this->subject();
		$body    = $this->render_body( $login_url );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		return (bool) \wp_mail( $email, $subject, $body, $headers );
	}

	public function subject(): string {
		return \__( 'Login to Stripe Customer Portal', 'login-stripe-customer-portal' );
	}

	/**
	 * Render the HTML body for the magic-link email. Public so tests can
	 * assert on link presence, link escaping, and overall HTML structure
	 * without round-tripping through wp_mail.
	 */
	public function render_body( string $login_url ): string {
		$safe_url = \esc_url( $login_url );

		// Translators: 1: anchor opening tag, 2: anchor closing tag.
		$line = \__(
			'Click %1$shere%2$s to log in to the Stripe Customer Portal. This link expires in one hour and can only be used once.',
			'login-stripe-customer-portal'
		);

		$linked_line = sprintf(
			$line,
			'<a href="' . $safe_url . '">',
			'</a>'
		);

		// Plain-text fallback URL on its own line so mail clients that strip HTML
		// still surface a usable link.
		$plain = sprintf(
			/* translators: %s: magic-link URL. */
			\__( 'If the link does not work, copy and paste this URL into your browser: %s', 'login-stripe-customer-portal' ),
			$safe_url
		);

		return sprintf(
			'<p>%s</p><p style="font-size:12px;color:#666;">%s</p>',
			$linked_line,
			\esc_html( $plain )
		);
	}
}
