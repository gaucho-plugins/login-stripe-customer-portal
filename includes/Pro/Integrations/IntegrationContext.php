<?php
/**
 * Shared helpers used by every Phase 3 platform integration. Holds the
 * per-platform "is this plugin installed?" check, the customer-portal URL
 * builder, and the rendered Manage-Billing button markup.
 *
 * Pure helper — no WP hooks bound here.
 *
 * @package LSCP\Pro\Integrations
 *
 * @fs_premium_only
 */

declare( strict_types = 1 );

namespace LSCP\Pro\Integrations;

use LSCP\Settings;
use LSCP\Pro\UserBridge;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class IntegrationContext {

	public const OPTION_BUTTON_LABEL = 'lscp_pro_integration_button_label';
	public const DEFAULT_BUTTON_LABEL = 'Manage Billing';

	public const OPTION_WOOCOMMERCE = 'lscp_pro_integration_woocommerce';
	public const OPTION_MEMBERPRESS = 'lscp_pro_integration_memberpress';
	public const OPTION_LEARNDASH   = 'lscp_pro_integration_learndash';

	/**
	 * @return array<string,bool> slug => is detected
	 */
	public static function detected(): array {
		return array(
			'woocommerce' => self::is_woocommerce_active(),
			'memberpress' => self::is_memberpress_active(),
			'learndash'   => self::is_learndash_active(),
		);
	}

	public static function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	public static function is_memberpress_active(): bool {
		return class_exists( 'MeprAccountCtrl' ) || class_exists( 'MeprUser' );
	}

	public static function is_learndash_active(): bool {
		return class_exists( 'SFWD_LMS' ) || defined( 'LEARNDASH_VERSION' );
	}

	public static function is_woocommerce_enabled(): bool {
		return self::is_woocommerce_active() && '1' === (string) \get_option( self::OPTION_WOOCOMMERCE, '0' );
	}

	public static function is_memberpress_enabled(): bool {
		return self::is_memberpress_active() && '1' === (string) \get_option( self::OPTION_MEMBERPRESS, '0' );
	}

	public static function is_learndash_enabled(): bool {
		return self::is_learndash_active() && '1' === (string) \get_option( self::OPTION_LEARNDASH, '0' );
	}

	public static function button_label(): string {
		$raw = (string) \get_option( self::OPTION_BUTTON_LABEL, '' );
		return '' === trim( $raw ) ? self::DEFAULT_BUTTON_LABEL : $raw;
	}

	/**
	 * Customer-portal URL the Manage Billing button points at. Honors the
	 * site's configured LSCP slug.
	 */
	public static function portal_url(): string {
		$slug = (string) \get_option( Settings::OPTION_ENDPOINT_SLUG, Settings::DEFAULT_SLUG );
		$slug = '' === $slug ? Settings::DEFAULT_SLUG : $slug;
		if ( function_exists( '\home_url' ) ) {
			return \home_url( '/' . trim( $slug, '/' ) . '/' );
		}
		return '/' . trim( $slug, '/' ) . '/';
	}

	/**
	 * Build the Manage-Billing button HTML. Caller is responsible for
	 * deciding whether to render it (e.g. only for users who already have
	 * a linked Stripe customer). Returns '' if the user_id has no link
	 * AND the portal is publicly-accessible — in that case the public form
	 * can still serve them, so we still render the button.
	 */
	public static function render_button( int $user_id ): string {
		// Render the button for any logged-in user — even without a stored
		// link, the public portal form can issue them a magic link.
		$label = self::button_label();
		$url   = self::portal_url();

		// Append a hint flag the portal endpoint can read (no functional
		// effect today — reserved for Phase 4 analytics).
		if ( $user_id > 0 ) {
			$linked = UserBridge::get_stripe_customer_id_for_user( $user_id );
			if ( null !== $linked ) {
				$url = function_exists( '\add_query_arg' )
					? \add_query_arg( array( 'lscp_known' => 1 ), $url )
					: $url . '?lscp_known=1';
			}
		}

		$href  = function_exists( '\esc_url' ) ? \esc_url( $url ) : $url;
		$label = function_exists( '\esc_html' ) ? \esc_html( $label ) : $label;

		return sprintf(
			'<p class="lscp-manage-billing"><a href="%s" class="button lscp-manage-billing__button">%s</a></p>',
			$href,
			$label
		);
	}
}
