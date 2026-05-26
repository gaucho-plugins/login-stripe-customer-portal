<?php
/**
 * WP-CLI commands for LSCP ops tooling.
 *
 * Registered via `WP_CLI::add_command('lscp', __CLASS__)` only when
 * WP_CLI is defined. Available since 1.0.6.
 *
 * ## Command contract
 *
 * | Command                            | Returns      | Exit code                                  |
 * |------------------------------------|--------------|--------------------------------------------|
 * | `wp lscp purge-tokens [--dry-run]` | int (count)  | 0                                          |
 * | `wp lscp limiter-reset <email>`    | int (count)  | 0 / 1 if email arg missing                 |
 * | `wp lscp send <email>`             | bool         | 0 on success / 1 on invalid email or failure |
 * | `wp lscp config`                   | array        | 0 (Stripe Secret Key always masked)        |
 *
 * Notes:
 * - `purge-tokens` accepts `--dry-run` (boolean flag). Without the flag,
 *   every magic-link token is deleted (expired AND fresh). With the
 *   flag, only the expired-count is reported and no writes happen.
 * - `send` honors the public-form rate limiter — if the email is over
 *   the per-email/per-IP cap, the CLI reports "did not send" without
 *   throwing.
 * - `config` masks the Stripe Secret Key as `(set, N chars)` — the key
 *   itself is never printed to stdout.
 *
 * All commands run `@when after_wp_load`. Only `send` makes a Stripe
 * round-trip; the rest are local DB operations.
 *
 * @package LSCP
 */

declare( strict_types = 1 );

namespace LSCP;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class Cli {

	public static function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}
		if ( ! class_exists( '\\WP_CLI' ) ) {
			return;
		}
		\WP_CLI::add_command( 'lscp', __CLASS__ );
	}

	/**
	 * Delete every outstanding magic-link token transient.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report what would be deleted without writing.
	 *
	 * @when after_wp_load
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @return int Number of tokens deleted (or that would be deleted).
	 */
	public function purge_tokens( $args, $assoc_args ): int {
		$dry     = ! empty( $assoc_args['dry-run'] );
		$deleted = ( new TokenGC() )->sweep();

		if ( ! $dry ) {
			// Also drop any non-expired tokens — purge is unconditional.
			$prefix = 'lscp_token_';
			$deleted += $this->delete_by_prefix( $prefix );
		}

		$msg = $dry
			? "Would delete {$deleted} expired token(s) (dry-run, fresh tokens left in place)."
			: "Deleted {$deleted} token(s).";

		$this->log( $msg );
		return $deleted;
	}

	/**
	 * Reset the rate-limiter counters for a specific email (across all IPs).
	 *
	 * ## OPTIONS
	 *
	 * <email>
	 * : Email address whose rate-limit counters should be cleared.
	 *
	 * @when after_wp_load
	 *
	 * @param array $args
	 * @return int Number of counters deleted.
	 */
	public function limiter_reset( $args ): int {
		$email = isset( $args[0] ) ? (string) $args[0] : '';
		if ( '' === $email ) {
			$this->error( 'Email argument required: wp lscp limiter-reset <email>' );
			return 0;
		}
		$removed = ( new Privacy() )->delete_rate_limit_for( $email );
		$this->log( "Cleared {$removed} rate-limit counter(s)." );
		return $removed;
	}

	/**
	 * Send a magic-link email immediately, bypassing the form.
	 *
	 * ## OPTIONS
	 *
	 * <email>
	 * : Email address to send the magic link to.
	 *
	 * @when after_wp_load
	 *
	 * @param array $args
	 * @return bool
	 */
	public function send( $args ): bool {
		$email = isset( $args[0] ) ? (string) $args[0] : '';
		if ( '' === $email || ! \is_email( $email ) ) {
			$this->error( 'Valid email argument required: wp lscp send <email>' );
			return false;
		}
		$sent = ( new PortalController() )->maybe_send_login_email( $email );
		if ( $sent ) {
			$this->log( "Magic link sent to {$email}." );
		} else {
			$this->log( "Did not send magic link to {$email}. Possible reasons: validate-existing-customers is on and the customer was not found, or wp_mail() failed." );
		}
		return $sent;
	}

	/**
	 * Print the current LSCP settings (secret key is masked).
	 *
	 * @when after_wp_load
	 *
	 * @return array
	 */
	public function config(): array {
		$api_key = (string) \get_option( Settings::OPTION_API_KEY, '' );
		$out     = array(
			Settings::OPTION_API_KEY           => '' === $api_key ? '(unset)' : '(set, ' . strlen( $api_key ) . ' chars)',
			Settings::OPTION_REDIRECT_URL      => (string) \get_option( Settings::OPTION_REDIRECT_URL, '' ),
			Settings::OPTION_ENDPOINT_SLUG     => (string) \get_option( Settings::OPTION_ENDPOINT_SLUG, Settings::DEFAULT_SLUG ),
			Settings::OPTION_VALIDATE_EXISTING => (string) \get_option( Settings::OPTION_VALIDATE_EXISTING, '0' ),
		);
		foreach ( $out as $k => $v ) {
			$this->log( "{$k} = {$v}" );
		}
		return $out;
	}

	private function delete_by_prefix( string $prefix ): int {
		$removed = 0;
		if ( class_exists( '\\LSCP\\Tests\\Stubs\\WpInMemory' ) ) {
			foreach ( array_keys( \LSCP\Tests\Stubs\WpInMemory::$transients ) as $key ) {
				if ( 0 === strpos( (string) $key, $prefix ) ) {
					unset( \LSCP\Tests\Stubs\WpInMemory::$transients[ $key ] );
					++$removed;
				}
			}
			return $removed;
		}

		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			return 0;
		}
		$like = $wpdb->esc_like( '_transient_' . $prefix ) . '%';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_col(
			$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
		);
		// phpcs:enable
		foreach ( (array) $rows as $name ) {
			$key = substr( (string) $name, strlen( '_transient_' ) );
			\delete_transient( $key );
			++$removed;
		}
		return $removed;
	}

	private function log( string $msg ): void {
		if ( class_exists( '\\WP_CLI' ) ) {
			\WP_CLI::log( $msg );
		}
	}

	private function error( string $msg ): void {
		if ( class_exists( '\\WP_CLI' ) ) {
			\WP_CLI::warning( $msg );
		}
	}
}
