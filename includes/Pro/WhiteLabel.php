<?php
/**
 * Agency white-label layer. Replaces "Gaucho Plugins" attribution strings
 * with a custom brand and hides the upgrade-CTA banner / inline CTAs in
 * the admin.
 *
 * Ships with every PRO tier (per the v1.1 pricing decision — every tier
 * includes every feature, including white-label).
 *
 * @package LSCP\Pro
 *
 * @fs_premium_only
 */

declare( strict_types = 1 );

namespace LSCP\Pro;

use LSCP\Sanitizer;
use LSCP\Settings;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class WhiteLabel {

	public const OPTION_ENABLED       = 'lscp_pro_white_label_enabled';
	public const OPTION_BRAND_NAME    = 'lscp_pro_white_label_brand_name';
	public const OPTION_HIDE_UPGRADE  = 'lscp_pro_white_label_hide_upgrade';

	public const DEFAULT_BRAND_FROM = 'Gaucho Plugins';

	public static function register(): void {
		\add_filter( 'gettext', array( __CLASS__, 'maybe_rewrite_brand' ), 10, 3 );
		\add_filter( 'lscp_settings_tabs', array( __CLASS__, 'maybe_filter_tabs' ), 5 );
		\add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	public static function register_settings(): void {
		\register_setting(
			'lscp_pro_white_label_group',
			self::OPTION_ENABLED,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( Sanitizer::class, 'sanitize_checkbox' ),
				'default'           => '0',
			)
		);
		\register_setting(
			'lscp_pro_white_label_group',
			self::OPTION_BRAND_NAME,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_brand' ),
				'default'           => '',
			)
		);
		\register_setting(
			'lscp_pro_white_label_group',
			self::OPTION_HIDE_UPGRADE,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( Sanitizer::class, 'sanitize_checkbox' ),
				'default'           => '1',
			)
		);
	}

	public static function sanitize_brand( $input ): string {
		return Sanitizer::sanitize_one_line_text( $input, 80 );
	}

	public static function is_enabled(): bool {
		return '1' === (string) \get_option( self::OPTION_ENABLED, '0' );
	}

	public static function hide_upgrade(): bool {
		if ( ! self::is_enabled() ) {
			return false;
		}
		return '1' === (string) \get_option( self::OPTION_HIDE_UPGRADE, '1' );
	}

	public static function brand_name(): string {
		$raw = (string) \get_option( self::OPTION_BRAND_NAME, '' );
		return '' === trim( $raw ) ? self::DEFAULT_BRAND_FROM : $raw;
	}

	/**
	 * Rewrite "Gaucho Plugins" strings to the configured brand whenever
	 * the gettext call originates from this plugin's domain.
	 */
	public static function maybe_rewrite_brand( $translated, $original, $domain ) {
		if ( 'login-stripe-customer-portal' !== $domain ) {
			return $translated;
		}
		if ( ! self::is_enabled() ) {
			return $translated;
		}
		$brand = self::brand_name();
		if ( self::DEFAULT_BRAND_FROM === $brand ) {
			return $translated;
		}
		return str_replace( self::DEFAULT_BRAND_FROM, $brand, (string) $translated );
	}

	/**
	 * When white-label is on, suppress the "Powered by …" tab markers
	 * (lock icons next to PRO tabs). Run at priority 5 so we fire BEFORE
	 * Settings::tabs() default consumers.
	 *
	 * @param array<string,array{label:string,pro:bool}> $tabs
	 */
	public static function maybe_filter_tabs( $tabs ) {
		if ( ! self::is_enabled() ) {
			return $tabs;
		}
		if ( ! is_array( $tabs ) ) {
			return $tabs;
		}
		// In white-label mode, the PRO-suffix lock icons are irrelevant
		// (all features already paid for). Strip the marker flag.
		foreach ( $tabs as $slug => $row ) {
			if ( is_array( $row ) && ! empty( $row['pro'] ) ) {
				$tabs[ $slug ]['pro'] = false;
			}
		}
		return $tabs;
	}
}
