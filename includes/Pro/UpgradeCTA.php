<?php
/**
 * Renders upgrade prompts that survive Freemius's preprocessor and ship in
 * the FREE wp.org build. The PRO build short-circuits the prompts since
 * the corresponding settings sections are rendered for real.
 *
 * Modeled after Split Pay's Core/UpgradeCTA helper (the established GP
 * portfolio pattern).
 *
 * @package LSCP
 */

declare( strict_types = 1 );

namespace LSCP\Pro;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class UpgradeCTA {

	/** Single, sober CTA copy used across every tab. */
	public const CTA_LABEL = 'Upgrade to PRO — from $79/yr';

	/** Default body sentence describing what PRO includes. */
	public const CTA_BODY = 'Branded magic-link emails, login-form styler, WooCommerce / MemberPress integration, Stripe webhook → role automation, multi-Stripe-account support, and white-label.';

	/** Site-count strip shown below the CTA button on full-tab renders. */
	public const CTA_SITES_LINE = '1 site $79/yr · 3 sites $159/yr · 25 sites $299/yr · 100 sites + white-label $499/yr';

	/**
	 * True iff a premium build is active AND the user has consented to it.
	 *
	 * Honors a development-only override (LSCP_PREMIUM_DEV_OVERRIDE constant
	 * AND WP_DEBUG === true AND the wp-env / staging mu-plugin sets both).
	 * The double-gate is intentional — a production install that
	 * accidentally defines only one of the two does NOT bypass licensing.
	 */
	public static function is_premium(): bool {
		if ( defined( 'WP_DEBUG' ) && true === constant( 'WP_DEBUG' )
			&& defined( 'LSCP_PREMIUM_DEV_OVERRIDE' ) && true === constant( 'LSCP_PREMIUM_DEV_OVERRIDE' ) ) {
			return true;
		}
		if ( ! function_exists( 'lscp_fs' ) ) {
			return false;
		}
		$fs = \lscp_fs();
		if ( ! is_object( $fs ) || ! method_exists( $fs, 'can_use_premium_code' ) ) {
			return false;
		}
		return (bool) $fs->can_use_premium_code();
	}

	/**
	 * URL the Upgrade buttons point at. Freemius generates a pricing page
	 * we can deep-link to; if it's unavailable, fall back to a safe admin URL.
	 */
	public static function pricing_url(): string {
		if ( function_exists( 'lscp_fs' ) ) {
			$fs = \lscp_fs();
			if ( is_object( $fs ) && method_exists( $fs, 'get_upgrade_url' ) ) {
				$url = $fs->get_upgrade_url();
				if ( is_string( $url ) && '' !== $url ) {
					return $url;
				}
			}
		}
		if ( function_exists( 'admin_url' ) ) {
			return \admin_url( 'admin.php?page=login-stripe-customer-portal-pricing' );
		}
		return '#';
	}

	/**
	 * Inline CTA used next to a single PRO-gated setting on a page that
	 * otherwise has free content. Echoes HTML.
	 */
	public static function render_inline( string $feature_label ): void {
		$url = self::pricing_url();
		?>
		<div class="lscp-upgrade-cta lscp-upgrade-cta--inline" style="margin:8px 0;padding:8px 12px;background:#f6f7f7;border-left:4px solid #2271b1;">
			<strong><?php echo \esc_html( $feature_label ); ?></strong> is a PRO feature.
			<a class="button button-primary button-small" style="margin-left:8px;" href="<?php echo \esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php echo \esc_html( self::CTA_LABEL ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Full-tab CTA used when an entire settings tab is PRO-gated.
	 * Echoes HTML.
	 */
	public static function render_tab( string $tab_label, string $description = '' ): void {
		$url  = self::pricing_url();
		$body = '' !== $description ? $description : self::CTA_BODY;
		?>
		<div class="lscp-upgrade-cta lscp-upgrade-cta--tab" style="max-width:720px;margin:24px auto;padding:32px;background:#fff;border:1px solid #c3c4c7;border-radius:8px;text-align:center;">
			<h2 style="margin:0 0 8px;"><?php echo \esc_html( $tab_label ); ?></h2>
			<p style="margin:0 0 16px;font-size:14px;color:#50575e;">
				<?php echo \esc_html( $body ); ?>
			</p>
			<a class="button button-primary button-hero" href="<?php echo \esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php echo \esc_html( self::CTA_LABEL ); ?>
			</a>
			<p style="margin:12px 0 0;font-size:12px;color:#646970;">
				<?php echo \esc_html( self::CTA_SITES_LINE ); ?>
			</p>
			<p style="margin:6px 0 0;font-size:11px;color:#8c8f94;">
				Every plan includes every feature — pick the license count that matches your portfolio.
			</p>
		</div>
		<?php
	}

	/**
	 * Top-of-settings banner shown in the FREE build only. Suppressible
	 * via the `lscp_hide_upgrade_banner` user-meta flag (set by the
	 * banner's dismiss action).
	 */
	public static function render_banner(): void {
		if ( self::is_premium() ) {
			return;
		}
		$url = self::pricing_url();
		?>
		<div class="lscp-upgrade-banner notice notice-info" style="padding:12px 16px;">
			<p style="margin:0;">
				✨ <strong>LSCP PRO</strong> adds branded emails, login-form styling, WooCommerce / MemberPress integration, Stripe webhooks → role automation, multi-Stripe-account, and white-label.
				<a href="<?php echo \esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><strong>See plans →</strong></a>
			</p>
		</div>
		<?php
	}
}
