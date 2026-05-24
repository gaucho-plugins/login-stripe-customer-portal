<?php
/**
 * Daily cron sweep of expired LSCP transients.
 *
 * WordPress garbage-collects expired transients lazily — they linger in
 * wp_options until someone calls get_transient() on the expired key (which
 * triggers the cleanup). On low-traffic sites, an expired magic-link token
 * may sit in the DB indefinitely. This class adds an explicit daily sweep
 * for both lscp_token_* and lscp_rl_* prefixes.
 *
 * @package LSCP
 */

declare( strict_types = 1 );

namespace LSCP;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class TokenGC {

	public const HOOK = 'lscp_token_gc';

	public function register_hooks(): void {
		\add_action( self::HOOK, array( $this, 'sweep' ) );
		\add_action( 'init', array( $this, 'maybe_schedule' ) );
	}

	public function maybe_schedule(): void {
		if ( ! \wp_next_scheduled( self::HOOK ) ) {
			\wp_schedule_event( time() + 60, 'daily', self::HOOK );
		}
	}

	/**
	 * Sweep expired transients. Returns the number of keys deleted (handy in tests).
	 */
	public function sweep(): int {
		$deleted = 0;

		// In-memory fallback for tests.
		if ( class_exists( '\\LSCP\\Tests\\Stubs\\WpInMemory' ) ) {
			$now = \LSCP\Tests\Stubs\WpInMemory::current_time();
			foreach ( \LSCP\Tests\Stubs\WpInMemory::$transients as $key => $entry ) {
				if ( ! is_string( $key ) ) {
					continue;
				}
				if ( 0 !== strpos( $key, 'lscp_token_' ) && 0 !== strpos( $key, 'lscp_rl_' ) ) {
					continue;
				}
				if ( $entry['expires'] > 0 && $entry['expires'] <= $now ) {
					unset( \LSCP\Tests\Stubs\WpInMemory::$transients[ $key ] );
					++$deleted;
				}
			}
			return $deleted;
		}

		// Real WP path: query the options table for matching expired timeout rows.
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return 0;
		}

		$now = time();
		foreach ( array( 'lscp_token_', 'lscp_rl_' ) as $prefix ) {
			$like = $wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%';
			// phpcs:disable WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d",
					$like,
					$now
				)
			);
			// phpcs:enable

			foreach ( (array) $rows as $row ) {
				$timeout_name = $row->option_name;
				$key          = substr( $timeout_name, strlen( '_transient_timeout_' ) );
				if ( '' === $key ) {
					continue;
				}
				\delete_transient( $key );
				++$deleted;
			}
		}

		return $deleted;
	}
}
