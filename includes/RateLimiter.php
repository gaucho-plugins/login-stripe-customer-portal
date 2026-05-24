<?php
/**
 * Per-identity rate limiter for magic-link issuance.
 *
 * Backed by transients. Prevents an attacker from using the form
 * as either an open mail relay or an email-enumeration oracle
 * against the merchant's Stripe account.
 *
 * @package LSCP
 */

declare( strict_types = 1 );

namespace LSCP;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class RateLimiter {

	public const PREFIX           = 'lscp_rl_';
	public const DEFAULT_MAX_HITS = 5;
	public const DEFAULT_WINDOW   = 600; // 10 minutes.

	/** @var int */
	private $max_hits;

	/** @var int */
	private $window_seconds;

	public function __construct( int $max_hits = self::DEFAULT_MAX_HITS, int $window_seconds = self::DEFAULT_WINDOW ) {
		$this->max_hits       = max( 1, $max_hits );
		$this->window_seconds = max( 1, $window_seconds );
	}

	/**
	 * Record a hit for the identity (email, IP, "$email|$ip", etc.) and return
	 * true if the request is allowed, false if the cap was already reached.
	 */
	public function check_and_hit( string $identity ): bool {
		$identity = trim( $identity );
		if ( '' === $identity ) {
			// Refuse to rate-limit empty identities — let the caller reject upstream.
			return true;
		}

		$key  = $this->key_for( $identity );
		$hits = (int) \get_transient( $key );

		if ( $hits >= $this->max_hits ) {
			return false;
		}

		\set_transient( $key, $hits + 1, $this->window_seconds );
		return true;
	}

	public function key_for( string $identity ): string {
		return self::PREFIX . hash( 'sha256', strtolower( $identity ) );
	}

	public function max_hits(): int {
		return $this->max_hits;
	}
}
