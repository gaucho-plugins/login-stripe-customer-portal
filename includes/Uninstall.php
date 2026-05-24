<?php
/**
 * Uninstall cleanup. Called by uninstall.php at plugin root, which WP
 * invokes when the plugin is deleted from the Plugins screen.
 *
 * Removes every option and transient LSCP wrote, so a clean uninstall
 * leaves no orphans in wp_options.
 *
 * @package LSCP
 */

declare( strict_types = 1 );

namespace LSCP;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class Uninstall {

	/** @var array<int,string> All options LSCP persists. */
	public const OPTIONS = array(
		'lscp_stripe_api_key',
		'lscp_stripe_redirect_url',
		'lscp_stripe_endpoint_slug',
		'lscp_stripe_validate_existing_customers',
		// 1.1.0 — PRO email-templates feature options.
		'lscp_pro_email_template',
		'lscp_pro_email_logo_url',
		'lscp_pro_email_primary_color',
		'lscp_pro_email_subject',
		'lscp_pro_email_heading',
		'lscp_pro_email_cta_text',
		'lscp_pro_email_footer_text',
		'lscp_pro_email_from_name',
		'lscp_pro_email_from_email',
	);

	/** @var array<int,string> Transient prefixes LSCP writes. */
	public const TRANSIENT_PREFIXES = array(
		'lscp_token_',
		'lscp_rl_',
	);

	/**
	 * Top-level entry: delete options, transients, and clear any cron schedules.
	 */
	public static function run(): void {
		self::delete_options();
		self::delete_transients();
		self::clear_cron();
	}

	public static function delete_options(): void {
		foreach ( self::OPTIONS as $option ) {
			\delete_option( $option );
		}
		// Defensive: also drop the typoed option name from 1.0.5 that was
		// silently being written but never read.
		\delete_option( 'lscp_stripe_api_keylscp_stripe_redirect_url' );
	}

	public static function delete_transients(): void {
		// In real WP, transients live in wp_options as paired _transient_<k>
		// and _transient_timeout_<k> rows. Use a single targeted DELETE so
		// uninstall stays O(n) in the number of matching rows.
		global $wpdb;
		if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'query' ) ) {
			foreach ( self::TRANSIENT_PREFIXES as $prefix ) {
				$like_value   = $wpdb->esc_like( '_transient_' . $prefix ) . '%';
				$like_timeout = $wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%';
				// phpcs:disable WordPress.DB.DirectDatabaseQuery
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_value ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_timeout ) );
				// phpcs:enable
			}
		}

		// Test fallback: when run under the in-memory WP fake, iterate the
		// transient table and delete matching keys.
		if ( class_exists( '\\LSCP\\Tests\\Stubs\\WpInMemory' ) ) {
			foreach ( self::TRANSIENT_PREFIXES as $prefix ) {
				foreach ( array_keys( \LSCP\Tests\Stubs\WpInMemory::$transients ) as $key ) {
					if ( 0 === strpos( (string) $key, $prefix ) ) {
						unset( \LSCP\Tests\Stubs\WpInMemory::$transients[ $key ] );
					}
				}
			}
		}
	}

	public static function clear_cron(): void {
		$next = \wp_next_scheduled( TokenGC::HOOK );
		if ( $next ) {
			\wp_unschedule_event( (int) $next, TokenGC::HOOK );
		}
		// Defensive: clear any other scheduled instances.
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			\wp_clear_scheduled_hook( TokenGC::HOOK );
		}
	}
}
