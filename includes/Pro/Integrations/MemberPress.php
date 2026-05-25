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
		// Block-based / template-override fallback: append on the MP account
		// page via the_content. The action fires only on the legacy template.
		\add_filter( 'the_content', array( __CLASS__, 'maybe_inject_into_account_content' ), 20 );
	}

	public static function render_account_button(): void {
		$user_id = function_exists( '\get_current_user_id' ) ? (int) \get_current_user_id() : 0;
		echo IntegrationContext::render_button( $user_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IntegrationContext escapes internally.
	}

	public static function maybe_inject_into_account_content( $content ) {
		static $injected = false;
		if ( $injected || ! is_string( $content ) ) {
			return $content;
		}
		// MemberPress sets a flag on its account page; class_exists check
		// avoids a fatal on sites where the integration option was left on
		// but MP was later deactivated.
		if ( ! class_exists( 'MeprAccountCtrl' ) || ! function_exists( '\MeprUtils' ) ) {
			$on_account = false;
		} else {
			$on_account = method_exists( '\MeprAccountCtrl', 'is_account_page' ) ? (bool) \MeprAccountCtrl::is_account_page() : false;
		}
		if ( ! $on_account ) {
			return $content;
		}
		$user_id  = function_exists( '\get_current_user_id' ) ? (int) \get_current_user_id() : 0;
		$injected = true;
		return $content . IntegrationContext::render_button( $user_id );
	}
}
