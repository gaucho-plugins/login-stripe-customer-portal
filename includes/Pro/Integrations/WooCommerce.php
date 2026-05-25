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
		// Classic (PHP-template) My Account page: fires once on the dashboard.
		\add_action( 'woocommerce_account_dashboard', array( __CLASS__, 'render_dashboard_button' ) );
		// Block-based My Account page (WC 8.7+ default with block themes): the
		// dashboard action doesn't fire and the_content filter doesn't run for
		// the inner block markup, so we inject in wp_footer as a final fallback.
		// The wp_footer handler is a no-op when the dashboard action already fired.
		\add_action( 'wp_footer', array( __CLASS__, 'maybe_inject_footer_button' ), 5 );
	}

	public static function render_dashboard_button(): void {
		$user_id = function_exists( '\get_current_user_id' ) ? (int) \get_current_user_id() : 0;
		echo IntegrationContext::render_button( $user_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IntegrationContext escapes internally.
	}

	/**
	 * Final-fallback injection: when WC's block-based My Account renders, the
	 * `woocommerce_account_dashboard` action never fires. We detect that
	 * post-template via did_action() and inject the button so block-theme
	 * sites still get the integration. Wrapped in a tag the integration spec
	 * + sites can target, OR style away if positioning needs adjustment.
	 */
	public static function maybe_inject_footer_button(): void {
		if ( did_action( 'woocommerce_account_dashboard' ) > 0 ) {
			return; // classic template path already rendered it
		}
		if ( ! self::is_wc_account_page() ) {
			return;
		}
		$user_id = function_exists( '\get_current_user_id' ) ? (int) \get_current_user_id() : 0;
		echo '<div class="lscp-manage-billing-fallback" style="max-width:640px;margin:0 auto 32px;padding:0 24px;">';
		echo IntegrationContext::render_button( $user_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IntegrationContext escapes internally.
		echo '</div>';
	}

	/**
	 * Robust WC My Account detection — works for classic shortcode AND
	 * block-based account pages. is_account_page() returns false on some
	 * block-based templates, so we fall back to the page-id match.
	 */
	private static function is_wc_account_page(): bool {
		if ( function_exists( '\is_account_page' ) && \is_account_page() ) {
			return true;
		}
		if ( ! function_exists( '\get_queried_object_id' ) || ! function_exists( '\get_option' ) ) {
			return false;
		}
		$wc_page_id = (int) \get_option( 'woocommerce_myaccount_page_id', 0 );
		if ( $wc_page_id <= 0 ) {
			return false;
		}
		return (int) \get_queried_object_id() === $wc_page_id;
	}
}
