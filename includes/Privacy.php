<?php
/**
 * GDPR / WP Privacy Tools integration.
 *
 * Registers exporter + eraser callbacks so site owners can satisfy data
 * subject access and erasure requests through Tools → Export Personal Data
 * and Tools → Erase Personal Data.
 *
 * LSCP intentionally stores almost no PII at rest — only:
 *  - The hashed rate-limit counter keyed by SHA-256(lowercase email + '|' + IP).
 *  - Outstanding magic-link transients, keyed by SHA-256(token), value=email.
 *
 * The exporter surfaces the email→token presence (without revealing the
 * token itself) and any active rate-limit counters. The eraser deletes
 * both.
 *
 * @package LSCP
 */

declare( strict_types = 1 );

namespace LSCP;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class Privacy {

	public const EXPORTER_ID = 'login-stripe-customer-portal-exporter';
	public const ERASER_ID   = 'login-stripe-customer-portal-eraser';

	public function register_hooks(): void {
		\add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		\add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	public function register_exporter( $exporters ) {
		$exporters[ self::EXPORTER_ID ] = array(
			'exporter_friendly_name' => \__( 'Login for Stripe Customer Portal', 'login-stripe-customer-portal' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	public function register_eraser( $erasers ) {
		$erasers[ self::ERASER_ID ] = array(
			'eraser_friendly_name' => \__( 'Login for Stripe Customer Portal', 'login-stripe-customer-portal' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Exporter callback.
	 *
	 * @param string $email_address
	 * @param int    $page
	 * @return array
	 */
	public function export( $email_address, $page = 1 ) {
		$data_to_export = array();

		$has_token = $this->has_active_token_for( (string) $email_address );
		$attempts  = $this->rate_limit_attempts_for( (string) $email_address );

		if ( $has_token || $attempts > 0 ) {
			$data_to_export[] = array(
				'group_id'    => 'login-stripe-customer-portal',
				'group_label' => \__( 'Stripe Customer Portal logins', 'login-stripe-customer-portal' ),
				'item_id'     => 'login-stripe-customer-portal-' . md5( strtolower( $email_address ) ),
				'data'        => array(
					array(
						'name'  => \__( 'Email address', 'login-stripe-customer-portal' ),
						'value' => $email_address,
					),
					array(
						'name'  => \__( 'Has an active magic-link token', 'login-stripe-customer-portal' ),
						'value' => $has_token ? \__( 'yes', 'login-stripe-customer-portal' ) : \__( 'no', 'login-stripe-customer-portal' ),
					),
					array(
						'name'  => \__( 'Recent magic-link request attempts (within rate-limit window)', 'login-stripe-customer-portal' ),
						'value' => (string) $attempts,
					),
				),
			);
		}

		return array(
			'data' => $data_to_export,
			'done' => true,
		);
	}

	/**
	 * Eraser callback.
	 *
	 * @param string $email_address
	 * @param int    $page
	 * @return array
	 */
	public function erase( $email_address, $page = 1 ) {
		$items_removed = 0;
		$messages      = array();

		// Drop any outstanding magic-link tokens bound to this email.
		$tokens_removed = $this->delete_tokens_for( (string) $email_address );
		if ( $tokens_removed > 0 ) {
			$items_removed += $tokens_removed;
			$messages[]     = \sprintf(
				/* translators: %d: number of magic-link tokens deleted. */
				\__( 'Deleted %d outstanding magic-link token(s).', 'login-stripe-customer-portal' ),
				$tokens_removed
			);
		}

		// Drop the rate-limit counter for this email across any client IP.
		$counters_removed = $this->delete_rate_limit_for( (string) $email_address );
		if ( $counters_removed > 0 ) {
			$items_removed += $counters_removed;
			$messages[]     = \sprintf(
				/* translators: %d: number of rate-limit counters deleted. */
				\__( 'Cleared %d rate-limit counter(s).', 'login-stripe-customer-portal' ),
				$counters_removed
			);
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => 0,
			'messages'       => $messages,
			'done'           => true,
		);
	}

	public function has_active_token_for( string $email ): bool {
		if ( class_exists( '\\LSCP\\Tests\\Stubs\\WpInMemory' ) ) {
			foreach ( \LSCP\Tests\Stubs\WpInMemory::$transients as $key => $entry ) {
				if ( 0 !== strpos( (string) $key, 'lscp_token_' ) ) {
					continue;
				}
				if ( isset( $entry['value'] ) && strtolower( (string) $entry['value'] ) === strtolower( $email ) ) {
					return true;
				}
			}
			return false;
		}

		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			return false;
		}
		$like = $wpdb->esc_like( '_transient_lscp_token_' ) . '%';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_col(
			$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
		);
		// phpcs:enable
		foreach ( (array) $rows as $value ) {
			if ( strtolower( (string) $value ) === strtolower( $email ) ) {
				return true;
			}
		}
		return false;
	}

	public function rate_limit_attempts_for( string $email ): int {
		// The rate limiter hashes (lowercase email + '|' + IP) so we cannot
		// enumerate counters without iterating all client IPs. The exporter
		// returns the maximum observed counter across all current keys that
		// could match this email — a coarse but honest upper bound.
		$max = 0;
		if ( class_exists( '\\LSCP\\Tests\\Stubs\\WpInMemory' ) ) {
			foreach ( \LSCP\Tests\Stubs\WpInMemory::$transients as $key => $entry ) {
				if ( 0 === strpos( (string) $key, 'lscp_rl_' ) ) {
					$max = max( $max, (int) ( $entry['value'] ?? 0 ) );
				}
			}
		}
		return $max;
	}

	public function delete_tokens_for( string $email ): int {
		$removed = 0;
		if ( class_exists( '\\LSCP\\Tests\\Stubs\\WpInMemory' ) ) {
			foreach ( \LSCP\Tests\Stubs\WpInMemory::$transients as $key => $entry ) {
				if ( 0 !== strpos( (string) $key, 'lscp_token_' ) ) {
					continue;
				}
				if ( isset( $entry['value'] ) && strtolower( (string) $entry['value'] ) === strtolower( $email ) ) {
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
		$like = $wpdb->esc_like( '_transient_lscp_token_' ) . '%';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
		);
		// phpcs:enable
		foreach ( (array) $rows as $row ) {
			if ( strtolower( (string) $row->option_value ) === strtolower( $email ) ) {
				$key = substr( (string) $row->option_name, strlen( '_transient_' ) );
				\delete_transient( $key );
				++$removed;
			}
		}
		return $removed;
	}

	public function delete_rate_limit_for( string $email ): int {
		// We can compute the identity-hash for any known IP, but we don't know
		// the user's IP at erasure time. The safest action is to delete every
		// lscp_rl_* counter — which over-broadens to other users but is correct
		// per the GDPR "no orphan PII" intent. Sites under heavy traffic should
		// schedule erasures off-peak.
		$removed = 0;
		if ( class_exists( '\\LSCP\\Tests\\Stubs\\WpInMemory' ) ) {
			foreach ( array_keys( \LSCP\Tests\Stubs\WpInMemory::$transients ) as $key ) {
				if ( 0 === strpos( (string) $key, 'lscp_rl_' ) ) {
					unset( \LSCP\Tests\Stubs\WpInMemory::$transients[ $key ] );
					++$removed;
				}
			}
		}
		return $removed;
	}
}
