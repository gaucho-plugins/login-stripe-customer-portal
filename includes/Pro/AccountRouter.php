<?php
/**
 * Routes the FREE plugin's settings to per-account values at runtime.
 *
 * Two halves:
 *  1. Register an extra rewrite rule for each PRO account's slug (the
 *     FREE single-slug rule is unchanged).
 *  2. Hook `pre_option_lscp_stripe_*` to dynamically return the
 *     resolved account's value when the current request matches one of
 *     the configured account slugs.
 *
 * The FREE plugin code is completely unchanged — it still calls
 * `get_option('lscp_stripe_api_key')` etc., and the AccountRouter
 * intercepts before WP queries the DB.
 *
 * @package LSCP\Pro
 *
 * @fs_premium_only
 */

declare( strict_types = 1 );

namespace LSCP\Pro;

use LSCP\RewriteEndpoint;
use LSCP\Settings;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class AccountRouter {

	/**
	 * Once resolved per request, cache the active account so multiple
	 * pre_option_ calls don't re-parse REQUEST_URI.
	 *
	 * @var ?array<string,string>
	 */
	private static $cached = null;

	private static $cached_for_path = '';

	public static function register(): void {
		// Add extra rewrite rules at priority 11 so they sit AFTER the FREE
		// single-slug rule (FREE registers at default priority 10).
		\add_action( 'init', array( __CLASS__, 'register_rewrite_rules' ), 11 );

		\add_filter( 'pre_option_' . Settings::OPTION_API_KEY, array( __CLASS__, 'maybe_override_api_key' ), 10, 2 );
		\add_filter( 'pre_option_' . Settings::OPTION_ENDPOINT_SLUG, array( __CLASS__, 'maybe_override_slug' ), 10, 2 );
		\add_filter( 'pre_option_' . Settings::OPTION_REDIRECT_URL, array( __CLASS__, 'maybe_override_redirect_url' ), 10, 2 );
		\add_filter( 'pre_option_' . Settings::OPTION_VALIDATE_EXISTING, array( __CLASS__, 'maybe_override_validate_existing' ), 10, 2 );

		// Email From overrides via Phase 1's lscp_email_headers filter so the
		// per-account from address rides on top of any branded-email config.
		\add_filter( 'lscp_email_headers', array( __CLASS__, 'maybe_override_from_header' ), 9, 2 );
	}

	public static function register_rewrite_rules(): void {
		foreach ( Accounts::all() as $account ) {
			$slug = $account['slug'];
			if ( '' === $slug ) {
				continue;
			}
			\add_rewrite_rule( $slug . '/?$', 'index.php?' . RewriteEndpoint::QUERY_VAR . '=1', 'top' );
		}
	}

	/**
	 * Resolve the active account for the current request, with caching.
	 *
	 * @return ?array<string,string>
	 */
	public static function active_account(): ?array {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- read-only request-path lookup, no DB/echo.
		$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) \wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = strtok( $path, '?' ) ?: '';
		if ( $path === self::$cached_for_path && null !== self::$cached ) {
			return self::$cached;
		}
		self::$cached_for_path = $path;
		self::$cached          = Accounts::resolve_for_request_path( $path );
		return self::$cached;
	}

	public static function reset_cache(): void {
		self::$cached          = null;
		self::$cached_for_path = '';
	}

	public static function maybe_override_api_key( $pre, $option ) {
		$account = self::active_account();
		if ( null === $account || '' === $account['api_key'] ) {
			return $pre;
		}
		return $account['api_key'];
	}

	public static function maybe_override_slug( $pre, $option ) {
		$account = self::active_account();
		if ( null === $account || '' === $account['slug'] ) {
			return $pre;
		}
		return $account['slug'];
	}

	public static function maybe_override_redirect_url( $pre, $option ) {
		$account = self::active_account();
		if ( null === $account || '' === $account['redirect_url'] ) {
			return $pre;
		}
		return $account['redirect_url'];
	}

	public static function maybe_override_validate_existing( $pre, $option ) {
		$account = self::active_account();
		if ( null === $account ) {
			return $pre;
		}
		return $account['validate_existing'];
	}

	/**
	 * Append a per-account `From:` header. Runs at priority 9 so it sits
	 * before Phase 1's EmailTemplates::filter_headers (priority 10). That
	 * way EmailTemplates' "remove existing From:" logic correctly replaces
	 * us when both are configured.
	 *
	 * @param array<int,string>    $headers
	 * @param array<string,string> $context
	 */
	public static function maybe_override_from_header( $headers, $context ): array {
		$headers = (array) $headers;
		$account = self::active_account();
		if ( null === $account || '' === $account['from_email'] ) {
			return $headers;
		}
		$name = $account['from_name'];
		$from = '' !== $name ? sprintf( '%s <%s>', $name, $account['from_email'] ) : $account['from_email'];
		$out  = array();
		foreach ( $headers as $h ) {
			if ( 0 !== stripos( (string) $h, 'From:' ) ) {
				$out[] = $h;
			}
		}
		$out[] = 'From: ' . $from;
		return $out;
	}
}
