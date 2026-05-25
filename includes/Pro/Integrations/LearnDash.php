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
		// Universal fallback: wp_footer-based injection when the [ld_profile]
		// shortcode is present in the current page content.
		\add_action( 'wp_footer', array( __CLASS__, 'maybe_inject_footer_button' ), 5 );
	}

	public static function render_profile_button(): void {
		$user_id = function_exists( '\get_current_user_id' ) ? (int) \get_current_user_id() : 0;
		echo IntegrationContext::render_button( $user_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IntegrationContext escapes internally.
	}

	public static function maybe_inject_footer_button(): void {
		if ( did_action( 'ld_profile_after_top' ) > 0 ) {
			return; // legacy LD profile template already rendered it
		}
		if ( ! self::is_ld_profile_page() ) {
			return;
		}
		$user_id = function_exists( '\get_current_user_id' ) ? (int) \get_current_user_id() : 0;
		echo '<div class="lscp-manage-billing-fallback" style="max-width:640px;margin:0 auto 32px;padding:0 24px;">';
		echo IntegrationContext::render_button( $user_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IntegrationContext escapes internally.
		echo '</div>';
	}

	/**
	 * LD profile detection: page contains the [ld_profile] shortcode OR
	 * the wp-block-learndash profile block. Avoids injecting on every
	 * unrelated LD content page (courses/lessons).
	 */
	private static function is_ld_profile_page(): bool {
		if ( ! function_exists( '\get_post' ) ) {
			return false;
		}
		$post = \get_post();
		if ( ! is_object( $post ) || empty( $post->post_content ) ) {
			return false;
		}
		$content = (string) $post->post_content;
		return false !== strpos( $content, '[ld_profile' )
			|| false !== strpos( $content, 'wp-block-learndash' );
	}
}
