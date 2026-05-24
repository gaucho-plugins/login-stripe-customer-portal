<?php
/**
 * [login-stripe-customer-portal] shortcode — renders the magic-link email form.
 *
 * Shares the form template with PortalController so changes to the form
 * markup only happen in one place.
 *
 * @package LSCP
 */

declare( strict_types = 1 );

namespace LSCP;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class Shortcode {

	public const TAG = 'login-stripe-customer-portal';

	public function register_hooks(): void {
		\add_action( 'init', array( $this, 'register' ) );
	}

	public function register(): void {
		\add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	public function render( $atts = array() ): string {
		ob_start();
		FormRenderer::render( array( 'embedded' => true ) );
		return (string) ob_get_clean();
	}
}
