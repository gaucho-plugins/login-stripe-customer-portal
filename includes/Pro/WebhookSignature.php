<?php
/**
 * Stripe webhook signature verification.
 *
 * Stripe signs every webhook with HMAC-SHA256 over `<timestamp>.<payload>`
 * and sends the result in the `Stripe-Signature` header formatted as:
 *
 *   t=<unix-ts>,v1=<hex-sig>[,v1=<rotated-hex-sig>...]
 *
 * We reject any request whose timestamp is outside the configured
 * tolerance window (default 5 minutes) — that's how Stripe defends against
 * replay of intercepted requests with an old timestamp.
 *
 * Pure helper — no WP, no I/O. Lives in PRO so it ships only in the
 * premium build.
 *
 * @package LSCP\Pro
 *
 * @fs_premium_only
 */

declare( strict_types = 1 );

namespace LSCP\Pro;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class WebhookSignature {

	public const DEFAULT_TOLERANCE_SECONDS = 300;

	/**
	 * Verify a signed Stripe webhook.
	 *
	 * @param string $payload    Raw request body (must NOT be re-encoded).
	 * @param string $header     The Stripe-Signature header value.
	 * @param string $secret     The whsec_… secret from the Stripe dashboard.
	 * @param int    $tolerance  Max age of the request in seconds.
	 * @param ?int   $now        Optional current time (testable seam).
	 */
	public static function verify( string $payload, string $header, string $secret, int $tolerance = self::DEFAULT_TOLERANCE_SECONDS, ?int $now = null ): bool {
		if ( '' === $payload || '' === $header || '' === $secret ) {
			return false;
		}
		$now    = null === $now ? time() : $now;
		$parsed = self::parse_header( $header );
		if ( null === $parsed || empty( $parsed['signatures'] ) ) {
			return false;
		}

		if ( abs( $now - $parsed['timestamp'] ) > $tolerance ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $parsed['timestamp'] . '.' . $payload, $secret );
		foreach ( $parsed['signatures'] as $sig ) {
			if ( hash_equals( $expected, $sig ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Parse a `Stripe-Signature` header into { timestamp, signatures[] }.
	 * Returns null on malformed input.
	 *
	 * @return array{timestamp:int,signatures:array<int,string>}|null
	 */
	public static function parse_header( string $header ): ?array {
		$timestamp  = 0;
		$signatures = array();
		foreach ( explode( ',', $header ) as $part ) {
			$part = trim( $part );
			if ( '' === $part || false === strpos( $part, '=' ) ) {
				continue;
			}
			list( $k, $v ) = explode( '=', $part, 2 );
			$k             = trim( $k );
			$v             = trim( $v );
			if ( 't' === $k && ctype_digit( $v ) ) {
				$timestamp = (int) $v;
			} elseif ( 'v1' === $k && '' !== $v ) {
				$signatures[] = $v;
			}
		}
		if ( 0 === $timestamp ) {
			return null;
		}
		return array(
			'timestamp'  => $timestamp,
			'signatures' => $signatures,
		);
	}

	/**
	 * Build a `Stripe-Signature` header value for the given payload + secret.
	 * Test-only — useful for E2E fixtures and integration tests.
	 */
	public static function sign( string $payload, string $secret, ?int $now = null ): string {
		$now = null === $now ? time() : $now;
		$sig = hash_hmac( 'sha256', $now . '.' . $payload, $secret );
		return 't=' . $now . ',v1=' . $sig;
	}
}
