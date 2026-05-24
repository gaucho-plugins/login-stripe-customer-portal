<?php
/**
 * LSCP shortcodes.
 *
 *   [login-stripe-customer-portal]
 *   - Renders the magic-link email form (free-tier free + PRO styled via
 *     the lscp_form_template filter).
 *   - When the page is loaded with a ?lscp_message=… or ?lscp_error=…
 *     query arg (after a redirect-back from a form POST per the Story 7015
 *     fix), shows the message INSTEAD of the form, so users see the
 *     confirmation inline on the host page.
 *
 *   [lscp-message]
 *   - New in 1.1.0. Renders just the inline message (success/error) without
 *     the form. Useful when the form lives on one page and the merchant
 *     wants the confirmation to land on a different page (set via the
 *     lscp_post_redirect_url filter).
 *
 * @package LSCP
 */

declare( strict_types = 1 );

namespace LSCP;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class Shortcode {

	public const TAG_FORM    = 'login-stripe-customer-portal';
	public const TAG_MESSAGE = 'lscp-message';

	/** Back-compat alias. The user-facing tag name is permanent. */
	public const TAG = self::TAG_FORM;

	public function register_hooks(): void {
		\add_action( 'init', array( $this, 'register' ) );
	}

	public function register(): void {
		\add_shortcode( self::TAG_FORM, array( $this, 'render' ) );
		\add_shortcode( self::TAG_MESSAGE, array( $this, 'render_message' ) );
	}

	public function render( $atts = array() ): string {
		// If we landed here from a redirect-back after POST, show the
		// confirmation message inline instead of re-rendering the form.
		$message = PortalController::render_message_html();
		if ( '' !== $message ) {
			return $message;
		}

		ob_start();
		FormRenderer::render( array( 'embedded' => true ) );
		return (string) ob_get_clean();
	}

	/**
	 * `[lscp-message]` — renders just the inline message. Returns empty
	 * string when no message query arg is present (so dropping the
	 * shortcode on a page is safe even on first visit).
	 *
	 * @param array|string $atts
	 */
	public function render_message( $atts = array() ): string {
		return PortalController::render_message_html();
	}
}
