<?php
/**
 * Pure helper for the multi-account schema.
 *
 * Accounts live in a JSON-encoded `lscp_pro_accounts` option as an array
 * of:
 *   {
 *     id, label, api_key, slug, validate_existing,
 *     redirect_url, from_email, from_name
 *   }
 *
 * The FREE single-account config (lscp_stripe_api_key, _slug, etc.)
 * remains the fallback when no PRO account matches the current request.
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

final class Accounts {

	public const OPTION = 'lscp_pro_accounts';

	/** @return array<int,array<string,string>> */
	public static function all(): array {
		$raw = (string) \get_option( self::OPTION, '' );
		if ( '' === $raw ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}
		$out = array();
		foreach ( $decoded as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$out[] = self::normalize_row( $row );
		}
		return $out;
	}

	public static function save( array $rows ): bool {
		$clean = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$norm = self::normalize_row( $row );
			if ( '' === $norm['slug'] ) {
				continue;
			}
			$clean[] = $norm;
		}
		return (bool) \update_option( self::OPTION, (string) \wp_json_encode( $clean ) );
	}

	/**
	 * Find account matching the current request path. Returns null when the
	 * request doesn't match any account.
	 *
	 * @return ?array<string,string>
	 */
	public static function resolve_for_request_path( string $path ): ?array {
		$path = '/' . trim( $path, '/' ) . '/';
		foreach ( self::all() as $account ) {
			$slug = trim( $account['slug'], '/' );
			if ( '' === $slug ) {
				continue;
			}
			if ( false !== strpos( $path, '/' . $slug . '/' ) ) {
				return $account;
			}
		}
		return null;
	}

	/**
	 * @return ?array<string,string>
	 */
	public static function resolve_current(): ?array {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- read-only request-path lookup, no DB/echo.
		$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) \wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( '' === $path ) {
			return null;
		}
		// Strip query string + fragment.
		$path = strtok( $path, '?' ) ?: '';
		return self::resolve_for_request_path( $path );
	}

	/**
	 * Normalize a row to a fixed-shape associative array. Every value is
	 * sanitized through the same helpers the FREE settings use.
	 *
	 * @return array<string,string>
	 */
	public static function normalize_row( array $row ): array {
		return array(
			'id'                => self::sanitize_id( $row['id'] ?? '' ),
			'label'             => Sanitizer::sanitize_one_line_text( $row['label'] ?? '', 80 ),
			'api_key'           => self::sanitize_key( $row['api_key'] ?? '' ),
			'slug'              => Sanitizer::sanitize_endpoint_slug( $row['slug'] ?? '' ),
			'validate_existing' => Sanitizer::sanitize_checkbox( $row['validate_existing'] ?? '0' ),
			'redirect_url'      => Sanitizer::sanitize_redirect_url( $row['redirect_url'] ?? '' ),
			'from_email'        => Sanitizer::sanitize_from_email( $row['from_email'] ?? '' ),
			'from_name'         => Sanitizer::sanitize_from_name( $row['from_name'] ?? '' ),
		);
	}

	public static function sanitize_id( $input ): string {
		$value = is_string( $input ) ? strtolower( trim( $input ) ) : '';
		if ( '' === $value ) {
			// Auto-generate when missing.
			return 'acc_' . substr( bin2hex( random_bytes( 5 ) ), 0, 10 );
		}
		$value = preg_replace( '/[^a-z0-9_-]/', '', $value ) ?? '';
		if ( '' === $value ) {
			return 'acc_' . substr( bin2hex( random_bytes( 5 ) ), 0, 10 );
		}
		return substr( $value, 0, 40 );
	}

	/**
	 * Mask-aware API key sanitizer. When the form posts a pure-mask value,
	 * the corresponding stored row's key is preserved.
	 */
	public static function sanitize_key( $input ): string {
		$value = is_string( $input ) ? trim( $input ) : '';
		if ( '' === $value ) {
			return '';
		}
		if ( Sanitizer::is_pure_mask( $value ) ) {
			return $value; // caller is responsible for substituting the stored key
		}
		return Sanitizer::sanitize_secret_key( $value, '' );
	}

	/**
	 * Build a "rendered" account object that the AccountRouter can feed to
	 * pre_option_ filters. Merges with FREE-default fallback.
	 *
	 * @return array{api_key:string,slug:string,validate_existing:string,redirect_url:string,from_email:string,from_name:string}
	 */
	public static function effective( array $account ): array {
		return array(
			'api_key'           => '' !== $account['api_key'] ? $account['api_key'] : (string) \get_option( Settings::OPTION_API_KEY, '' ),
			'slug'              => '' !== $account['slug'] ? $account['slug'] : (string) \get_option( Settings::OPTION_ENDPOINT_SLUG, Settings::DEFAULT_SLUG ),
			'validate_existing' => $account['validate_existing'],
			'redirect_url'      => '' !== $account['redirect_url'] ? $account['redirect_url'] : (string) \get_option( Settings::OPTION_REDIRECT_URL, '' ),
			'from_email'        => $account['from_email'],
			'from_name'         => $account['from_name'],
		);
	}
}
