<?php
/**
 * Renders the Manage-Billing button on the WooCommerce My Account dashboard
 * when the admin has enabled the integration AND WooCommerce is active.
 *
 * @package LSCP\Pro\Integrations
 *
 * @fs_premium_only
 */

declare( strict_types = 1 );

namespace LSCP\Pro\Integrations;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class WooCommerce {

	public static function register(): void {
		if ( ! IntegrationContext::is_woocommerce_enabled() ) {
			return;
		}
		\add_action( 'woocommerce_account_dashboard', array( __CLASS__, 'render_dashboard_button' ) );
	}

	public static function render_dashboard_button(): void {
		$user_id = function_exists( '\get_current_user_id' ) ? (int) \get_current_user_id() : 0;
		echo IntegrationContext::render_button( $user_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IntegrationContext escapes internally.
	}
}
