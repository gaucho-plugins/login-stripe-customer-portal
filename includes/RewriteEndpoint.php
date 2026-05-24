<?php
/**
 * Registers the customer-portal rewrite rule + query var.
 *
 * Empty slug disables the endpoint completely.
 *
 * @package LSCP
 */

declare( strict_types = 1 );

namespace LSCP;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class RewriteEndpoint {

	public const QUERY_VAR = 'lscp_stripe_customer_portal';

	public function register_hooks(): void {
		\add_action( 'init', array( $this, 'register' ) );
	}

	public function register(): void {
		$slug = $this->current_slug();
		if ( '' === $slug ) {
			return;
		}

		\add_rewrite_rule( $slug . '/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
		\add_rewrite_tag( '%' . self::QUERY_VAR . '%', '([^&]+)' );
	}

	public function current_slug(): string {
		$slug = (string) \get_option( Settings::OPTION_ENDPOINT_SLUG, Settings::DEFAULT_SLUG );
		return Sanitizer::sanitize_endpoint_slug( $slug );
	}

	/**
	 * Programmatic activation hook: register, flush.
	 */
	public function on_activate(): void {
		$this->register();
		\flush_rewrite_rules( false );
	}

	public function on_deactivate(): void {
		\flush_rewrite_rules( false );
	}
}
