<?php
/**
 * Renders the Manage-Billing button on LearnDash profile pages when the
 * admin has enabled the integration AND LearnDash is active.
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

final class LearnDash {

	public static function register(): void {
		if ( ! IntegrationContext::is_learndash_enabled() ) {
			return;
		}
		// LearnDash fires `ld_profile_after_top` inside its profile template
		// after the user header; we render the billing button there.
		\add_action( 'ld_profile_after_top', array( __CLASS__, 'render_profile_button' ) );
	}

	public static function render_profile_button(): void {
		$user_id = function_exists( '\get_current_user_id' ) ? (int) \get_current_user_id() : 0;
		echo IntegrationContext::render_button( $user_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IntegrationContext escapes internally.
	}
}
