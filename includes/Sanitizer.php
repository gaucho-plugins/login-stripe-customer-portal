<?php
/**
 * Pure-function input sanitizers for LSCP settings.
 *
 * Kept free of WordPress side effects (no get_option, no add_action)
 * so they can be unit-tested in isolation under brain/monkey.
 *
 * @package LSCP
 */

declare( strict_types = 1 );

namespace LSCP;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class Sanitizer {

	public const MASK_CHAR = '●';

	/**
	 * Preserve the existing secret key when the form posts a masked placeholder.
	 *
	 * The settings form renders the saved API key as a string of bullet characters
	 * of the same length as the real key. When the form is re-submitted unchanged,
	 * we must not overwrite the real key with the mask.
	 *
	 * @param string|null $input            New value submitted by the form.
	 * @param string|null $current_api_key  Currently saved API key (may be empty).
	 * @return string
	 */
	public static function sanitize_secret_key( $input, $current_api_key ): string {
		$input           = is_string( $input ) ? $input : '';
		$current_api_key = is_string( $current_api_key ) ? $current_api_key : '';

		// Empty submission — keep current.
		if ( '' === $input ) {
			return $current_api_key;
		}

		// Detect masked placeholder. We use a strict character-class test: if the input
		// contains ONLY mask characters (any count), treat it as the unchanged mask and
		// keep the saved key. Mixed inputs (e.g., a paste that happens to contain '●')
		// fall through to sanitize_text_field, which will preserve them.
		if ( self::is_pure_mask( $input ) ) {
			return $current_api_key;
		}

		// Live keys must never contain whitespace — Stripe keys are alphanumeric + underscore.
		// Trim whitespace defensively before sanitize_text_field.
		$candidate = trim( $input );

		if ( function_exists( 'sanitize_text_field' ) ) {
			$candidate = (string) \sanitize_text_field( $candidate );
		} else {
			$candidate = preg_replace( '/[\r\n\t\0\x0B]/', '', $candidate ) ?? '';
		}

		return $candidate;
	}

	/**
	 * Lightweight heuristic for whether a string looks like a Stripe secret key.
	 *
	 * Stripe keys follow well-known prefixes (sk_, rk_) with an environment
	 * marker (test_ / live_), but we deliberately do *not* enforce them on
	 * save — users may legitimately rotate to a new key format Stripe introduces
	 * later. This helper is for UI hints and CLI validation only.
	 */
	public static function looks_like_stripe_key( string $candidate ): bool {
		$candidate = trim( $candidate );
		if ( '' === $candidate ) {
			return false;
		}
		if ( ! preg_match( '/^(sk|rk|pk)_(test|live)_[A-Za-z0-9]{16,}$/', $candidate ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Validate and normalize the optional post-portal return URL.
	 *
	 * @param string|null $input
	 * @return string
	 */
	public static function sanitize_redirect_url( $input ): string {
		$input = is_string( $input ) ? trim( $input ) : '';
		if ( '' === $input ) {
			return '';
		}

		if ( function_exists( 'esc_url_raw' ) ) {
			return (string) \esc_url_raw( $input );
		}

		// Fallback for unit context — accept only http/https schemes.
		if ( ! preg_match( '#^https?://#i', $input ) ) {
			return '';
		}
		return $input;
	}

	/**
	 * Sanitize the endpoint slug. Empty slug means "disable the rewrite endpoint".
	 *
	 * Delegates to sanitize_title when WP is loaded; falls back to a strict
	 * a-z0-9-_ rule for the unit suite.
	 *
	 * @param string|null $input
	 * @return string
	 */
	public const MAX_SLUG_LENGTH = 64;

	public static function sanitize_endpoint_slug( $input ): string {
		$input = is_string( $input ) ? $input : '';
		if ( '' === $input ) {
			return '';
		}

		// Cap input length *before* sanitize_title; otherwise a megabyte input
		// burns memory and CPU normalizing characters we'll throw away.
		if ( strlen( $input ) > self::MAX_SLUG_LENGTH * 16 ) {
			$input = substr( $input, 0, self::MAX_SLUG_LENGTH * 16 );
		}

		if ( function_exists( 'sanitize_title' ) ) {
			$slug = (string) \sanitize_title( $input );
		} else {
			$slug = strtolower( trim( $input ) );
			$slug = preg_replace( '/[^a-z0-9_-]+/', '-', $slug );
			$slug = trim( $slug ?? '', '-' );
		}

		// Final hard cap on the post-normalization slug.
		if ( strlen( $slug ) > self::MAX_SLUG_LENGTH ) {
			$slug = trim( substr( $slug, 0, self::MAX_SLUG_LENGTH ), '-' );
		}

		return $slug;
	}

	/**
	 * Coerce a checkbox post value to '1' or '0'.
	 *
	 * WordPress checkbox inputs post their value attribute when checked and
	 * the field is omitted entirely when unchecked. We therefore accept the
	 * literal '1' as "checked" and reject everything else (including booleans
	 * and arrays) as "unchecked". Non-scalar input is silently treated as '0'
	 * — never throws, since this runs inside a sanitize_callback.
	 *
	 * @param mixed $input
	 * @return string
	 */
	public static function sanitize_checkbox( $input ): string {
		// Strict equality against the literal '1' string — booleans, arrays,
		// nulls and stray ints all become '0'. Real WP only posts the literal
		// '1' when the checkbox is checked.
		if ( is_string( $input ) ) {
			return '1' === $input ? '1' : '0';
		}
		if ( is_int( $input ) ) {
			return 1 === $input ? '1' : '0';
		}
		return '0';
	}

	/**
	 * Coerce arbitrary input to a 7-char `#rrggbb` hex color string. Accepts
	 * 3- or 6-digit hex with or without leading #. Anything else returns the
	 * empty string (Settings layer treats empty as "use default").
	 *
	 * @param mixed $input
	 */
	public static function sanitize_hex_color( $input ): string {
		$value = is_string( $input ) ? trim( $input ) : '';
		if ( '' === $value ) {
			return '';
		}
		if ( '#' !== $value[0] ) {
			$value = '#' . $value;
		}
		if ( preg_match( '/^#[0-9a-fA-F]{3}$/', $value ) ) {
			$value = '#' . str_repeat( $value[1], 2 ) . str_repeat( $value[2], 2 ) . str_repeat( $value[3], 2 );
		}
		return preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ? strtolower( $value ) : '';
	}

	/**
	 * Sanitize a logo URL. Accepts http(s) URLs only; everything else
	 * becomes empty (settings UI then renders no logo).
	 *
	 * @param mixed $input
	 */
	public static function sanitize_logo_url( $input ): string {
		$value = is_string( $input ) ? trim( $input ) : '';
		if ( '' === $value ) {
			return '';
		}
		if ( function_exists( 'esc_url_raw' ) ) {
			$value = (string) \esc_url_raw( $value, array( 'http', 'https' ) );
		}
		if ( ! preg_match( '#^https?://#i', $value ) ) {
			return '';
		}
		return $value;
	}

	/**
	 * Single-line text intended for email subject / heading / CTA / footer.
	 * Strips control chars + caps length to prevent abuse.
	 *
	 * @param mixed $input
	 */
	public static function sanitize_one_line_text( $input, int $max_length = 200 ): string {
		$value = is_string( $input ) ? $input : '';
		if ( '' === $value ) {
			return '';
		}
		// Strip CR/LF/tab/null to defeat header-injection if reused in mail headers.
		$value = preg_replace( '/[\r\n\t\0\x0B]+/', ' ', $value ) ?? '';
		if ( function_exists( 'sanitize_text_field' ) ) {
			$value = (string) \sanitize_text_field( $value );
		}
		$value = trim( $value );
		if ( strlen( $value ) > $max_length ) {
			$value = substr( $value, 0, $max_length );
		}
		return $value;
	}

	/**
	 * Multi-line text for the email footer. Allows newlines but strips
	 * control chars and caps length.
	 *
	 * @param mixed $input
	 */
	public static function sanitize_multi_line_text( $input, int $max_length = 500 ): string {
		$value = is_string( $input ) ? $input : '';
		if ( '' === $value ) {
			return '';
		}
		// Allow \n but strip \r and other control chars.
		$value = str_replace( "\r\n", "\n", $value );
		$value = preg_replace( '/[\r\t\0\x0B]/', '', $value ) ?? '';
		if ( function_exists( 'sanitize_textarea_field' ) ) {
			$value = (string) \sanitize_textarea_field( $value );
		}
		$value = trim( $value );
		if ( strlen( $value ) > $max_length ) {
			$value = substr( $value, 0, $max_length );
		}
		return $value;
	}

	/**
	 * Sanitize a From: email address. Returns '' if not a valid address —
	 * caller should treat empty as "fall back to wp_mail default".
	 *
	 * @param mixed $input
	 */
	public static function sanitize_from_email( $input ): string {
		$value = is_string( $input ) ? trim( $input ) : '';
		if ( '' === $value ) {
			return '';
		}
		// Strip header-injection vectors.
		$value = preg_replace( '/[\r\n\t\0\x0B,;]+/', '', $value ) ?? '';
		if ( function_exists( 'is_email' ) ) {
			$valid = \is_email( $value );
			return is_string( $valid ) ? $valid : '';
		}
		return filter_var( $value, FILTER_VALIDATE_EMAIL ) ? $value : '';
	}

	/**
	 * Sanitize a From: display name. Strips header-injection vectors and
	 * caps length.
	 *
	 * @param mixed $input
	 */
	public static function sanitize_from_name( $input ): string {
		$value = is_string( $input ) ? $input : '';
		$value = preg_replace( '/[\r\n\t\0\x0B,;<>]+/', '', $value ) ?? '';
		if ( function_exists( 'sanitize_text_field' ) ) {
			$value = (string) \sanitize_text_field( $value );
		}
		$value = trim( $value );
		if ( strlen( $value ) > 100 ) {
			$value = substr( $value, 0, 100 );
		}
		return $value;
	}

	/**
	 * Resolve an arbitrary slug against a whitelist of allowed values.
	 * Whitelist is the SOLE source of truth — anything not in it falls
	 * back to $default. Use for template/style pickers backed by a
	 * filesystem so users can't path-traverse via the option value.
	 *
	 * @param mixed              $input
	 * @param array<string,mixed> $allowed map slug => anything (only keys matter)
	 */
	public static function sanitize_whitelisted_slug( $input, array $allowed, string $fallback ): string {
		$value = is_string( $input ) ? strtolower( trim( $input ) ) : '';
		if ( '' === $value ) {
			return $fallback;
		}
		return isset( $allowed[ $value ] ) ? $value : $fallback;
	}

	/**
	 * True iff the input contains *only* mask characters and is non-empty.
	 */
	public static function is_pure_mask( string $input ): bool {
		if ( '' === $input ) {
			return false;
		}
		// Strip every mask character; if nothing remains, it was a pure mask.
		$stripped = str_replace( self::MASK_CHAR, '', $input );
		return '' === $stripped;
	}
}
