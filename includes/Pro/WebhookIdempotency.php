<?php
/**
 * Webhook replay protection. Stripe retries delivery for 3+ days when a
 * webhook endpoint returns non-2xx; once we've successfully processed an
 * event, every retry of the same event-id must be a no-op (otherwise
 * subscriber roles oscillate, mails get re-sent, etc.).
 *
 * We reuse the same SHA-256-keyed transient pattern TokenStore uses for
 * magic-link tokens (just with a different prefix). The key is keyed on
 * the Stripe event id, NOT on the request body, so a re-signed retry
 * still dedupes.
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

final class WebhookIdempotency {

	public const TRANSIENT_PREFIX = 'lscp_wh_';
	public const TTL_SECONDS      = 604800; // 7 days

	/**
	 * Returns true the FIRST time we see this event id (and atomically
	 * records the marker so subsequent calls return false).
	 *
	 * The transient set + get sequence is intentionally check-then-set —
	 * on the rare concurrent-retry case, both requests may proceed, but
	 * the worst outcome is the rule firing twice. The Stripe action
	 * handlers are idempotent themselves (role assignment is a no-op if
	 * the role is already present).
	 */
	public static function mark_seen( string $event_id ): bool {
		if ( '' === $event_id ) {
			return false;
		}
		$key = self::TRANSIENT_PREFIX . hash( 'sha256', $event_id );
		if ( false !== \get_transient( $key ) ) {
			return false; // already processed
		}
		\set_transient( $key, '1', self::TTL_SECONDS );
		return true;
	}

	public static function is_seen( string $event_id ): bool {
		if ( '' === $event_id ) {
			return false;
		}
		$key = self::TRANSIENT_PREFIX . hash( 'sha256', $event_id );
		return false !== \get_transient( $key );
	}
}
