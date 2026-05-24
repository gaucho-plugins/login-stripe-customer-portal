<?php
/**
 * Magic-link token store. Backed by WP transients keyed by SHA-256(token).
 *
 * The token is what the user receives in their email. The storage key is
 * a one-way hash of the token, so a database leak does not expose
 * outstanding magic links.
 *
 * @package LSCP
 */

declare( strict_types = 1 );

namespace LSCP;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class TokenStore {

	public const TOKEN_LENGTH     = 32;
	public const TRANSIENT_TTL    = 3600;
	public const TRANSIENT_PREFIX = 'lscp_token_';

	/**
	 * Generate a fresh magic-link token and persist its hash → email mapping.
	 *
	 * @param string $email Email address bound to the token.
	 * @param int    $ttl   Time-to-live in seconds. Defaults to one hour.
	 * @return string The token to embed in the magic link.
	 */
	public function issue( string $email, int $ttl = 0 ): string {
		$ttl   = $ttl > 0 ? $ttl : self::TRANSIENT_TTL;
		$token = $this->generate_token();
		$key   = $this->key_for( $token );

		\set_transient( $key, $email, $ttl );
		return $token;
	}

	/**
	 * Resolve a token to the email it was issued for, if still valid.
	 *
	 * @param string $token
	 * @return string Email address, or '' if not found / expired.
	 */
	public function lookup( string $token ): string {
		if ( '' === $token ) {
			return '';
		}
		$value = \get_transient( $this->key_for( $token ) );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Atomic redeem: resolve + delete in one shot. Returns email or '' if invalid.
	 *
	 * The store is best-effort single-use: we always call delete_transient
	 * before returning the email, even if a concurrent request also reads.
	 *
	 * @param string $token
	 * @return string
	 */
	public function consume( string $token ): string {
		$email = $this->lookup( $token );
		if ( '' === $email ) {
			return '';
		}
		\delete_transient( $this->key_for( $token ) );
		return $email;
	}

	/**
	 * Compute the WP-transient key for a given token. Public so tests can verify
	 * that the storage key is not the raw token.
	 */
	public function key_for( string $token ): string {
		return self::TRANSIENT_PREFIX . hash( 'sha256', $token );
	}

	/**
	 * Generate a fresh high-entropy token.
	 *
	 * Uses random_bytes for cryptographic strength; falls back to
	 * wp_generate_password for environments that block random_bytes
	 * (extremely rare on supported PHP versions).
	 */
	private function generate_token(): string {
		try {
			$bytes = random_bytes( (int) ( self::TOKEN_LENGTH / 2 ) );
			return bin2hex( $bytes );
		} catch ( \Throwable $e ) {
			if ( function_exists( 'wp_generate_password' ) ) {
				return (string) \wp_generate_password( self::TOKEN_LENGTH, false, false );
			}
			return substr( bin2hex( (string) microtime( true ) . (string) mt_rand() ), 0, self::TOKEN_LENGTH );
		}
	}
}
