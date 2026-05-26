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
		// MemberPress fires `mepr_account_home_after` after the account-home
		// content. Hook it so the button shows below the welcome message.
		\add_action( 'mepr_account_home_after', array( __CLASS__, 'render_account_button' ) );
		// Universal fallback: wp_footer + is-MP-account detection. Mirrors
		// the WC adapter — fires only when the legacy action didn't.
		\add_action( 'wp_footer', array( __CLASS__, 'maybe_inject_footer_button' ), 5 );
	}

	public static function render_account_button(): void {
		$user_id = function_exists( '\get_current_user_id' ) ? (int) \get_current_user_id() : 0;
		echo IntegrationContext::render_button( $user_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IntegrationContext escapes internally.
	}

	public static function maybe_inject_footer_button(): void {
		if ( did_action( 'mepr_account_home_after' ) > 0 ) {
			return; // classic template path already rendered it
		}
		if ( ! self::is_mp_account_page() ) {
			return;
		}
		$user_id = function_exists( '\get_current_user_id' ) ? (int) \get_current_user_id() : 0;
		echo '<div class="lscp-manage-billing-fallback" style="max-width:640px;margin:0 auto 32px;padding:0 24px;">';
		echo IntegrationContext::render_button( $user_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IntegrationContext escapes internally.
		echo '</div>';
	}

	private static function is_mp_account_page(): bool {
		// `is_account_page()` has been a public MeprAccountCtrl method
		// since MemberPress 1.x; if the class is loaded, the method exists.
		// (PHPStan can't see MP's source so it would flag method_exists()
		// as always-false; the class_exists guard alone is sufficient.)
		if ( class_exists( 'MeprAccountCtrl' ) ) {
			$is_account = \MeprAccountCtrl::is_account_page();
			if ( (bool) $is_account ) {
				return true;
			}
		}
		// Page-id fallback: MP stores its account-page id in the
		// `mepr_options` array, keyed by `account_page_id`.
		$opts = \get_option( 'mepr_options', array() );
		if ( ! is_array( $opts ) || empty( $opts['account_page_id'] ) ) {
			return false;
		}
		return function_exists( '\get_queried_object_id' ) && (int) \get_queried_object_id() === (int) $opts['account_page_id'];
	}
}
