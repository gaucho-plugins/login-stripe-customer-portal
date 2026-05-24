<?php
/**
 * Renders the Manage-Billing button on MemberPress account pages when the
 * admin has enabled the integration AND MemberPress is active.
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

final class MemberPress {

	public static function register(): void {
		if ( ! IntegrationContext::is_memberpress_enabled() ) {
			return;
		}
		// MemberPress fires `mepr-account-nav` inside its account-tabs nav and
		// `mepr_account_home_after` after the account-home content. We render
		// at the latter so the button shows below the welcome message.
		\add_action( 'mepr_account_home_after', array( __CLASS__, 'render_account_button' ) );
	}

	public static function render_account_button(): void {
		$user_id = function_exists( '\get_current_user_id' ) ? (int) \get_current_user_id() : 0;
		echo IntegrationContext::render_button( $user_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IntegrationContext escapes internally.
	}
}
