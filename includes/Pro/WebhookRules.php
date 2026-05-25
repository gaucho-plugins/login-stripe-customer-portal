<?php
/**
 * Translates Stripe webhook events into WordPress effects. Each event type
 * has a configurable rule (e.g. "on customer.subscription.created assign
 * role X"); after the built-in rule fires, an extension action
 * `lscp_pro_webhook_<event_type>` is dispatched with the full Stripe event
 * payload so 3rd-party integrations can hook in.
 *
 * Supported event types (Phase 4 baseline):
 *   - customer.subscription.created   → assign configured "active" role
 *   - customer.subscription.updated   → assign role based on status
 *   - customer.subscription.deleted   → remove role (or assign downgrade)
 *   - invoice.paid                    → fire action only (no role change)
 *   - invoice.payment_failed          → assign configured "past_due" role
 *
 * Every supported event lives in SUPPORTED_EVENTS — the Phase 4
 * WebhookCompletenessTest grep-locks this list against the handler
 * dispatcher to prevent silent gaps.
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

final class WebhookRules {

	public const OPTION_ROLE_ON_CREATED   = 'lscp_pro_webhook_role_on_created';
	public const OPTION_ROLE_ON_DELETED   = 'lscp_pro_webhook_role_on_deleted';
	public const OPTION_ROLE_WHEN_PAST_DUE = 'lscp_pro_webhook_role_when_past_due';
	public const OPTION_EVENTS_ENABLED    = 'lscp_pro_webhook_events_enabled';

	/** @var array<int,string> Every event type the engine knows how to dispatch. */
	public const SUPPORTED_EVENTS = array(
		'customer.subscription.created',
		'customer.subscription.updated',
		'customer.subscription.deleted',
		'invoice.paid',
		'invoice.payment_failed',
	);

	/**
	 * Top-level dispatcher. Called by WebhookEndpoint after signature +
	 * idempotency pass. Returns a short string describing what happened
	 * so the endpoint can echo it in the JSON response (useful for the
	 * Stripe CLI / dashboard debug view).
	 *
	 * @param array<string,mixed> $event Decoded Stripe event JSON.
	 */
	public static function dispatch( array $event ): string {
		$type = isset( $event['type'] ) ? (string) $event['type'] : '';
		if ( '' === $type ) {
			return 'ignored:no_type';
		}
		if ( ! self::is_event_enabled( $type ) ) {
			return 'ignored:disabled';
		}
		if ( ! in_array( $type, self::SUPPORTED_EVENTS, true ) ) {
			// Still fire the per-type action so 3rd-party integrations can
			// handle their own custom events.
			\do_action( 'lscp_pro_webhook_' . str_replace( '.', '_', $type ), $event );
			return 'forwarded:' . $type;
		}

		switch ( $type ) {
			case 'customer.subscription.created':
				$msg = self::handle_subscription_created( $event );
				break;
			case 'customer.subscription.updated':
				$msg = self::handle_subscription_updated( $event );
				break;
			case 'customer.subscription.deleted':
				$msg = self::handle_subscription_deleted( $event );
				break;
			case 'invoice.paid':
				$msg = 'invoice_paid';
				break;
			case 'invoice.payment_failed':
				$msg = self::handle_invoice_payment_failed( $event );
				break;
			default:
				$msg = 'unhandled';
		}

		\do_action( 'lscp_pro_webhook_' . str_replace( '.', '_', $type ), $event );
		return $msg;
	}

	/** @return array<int,string> */
	public static function enabled_events(): array {
		$raw = \get_option( self::OPTION_EVENTS_ENABLED, '' );
		if ( '' === $raw ) {
			// Default: every supported event enabled.
			return self::SUPPORTED_EVENTS;
		}
		$decoded = json_decode( (string) $raw, true );
		return is_array( $decoded ) ? array_values( array_map( 'strval', $decoded ) ) : self::SUPPORTED_EVENTS;
	}

	public static function is_event_enabled( string $type ): bool {
		return in_array( $type, self::enabled_events(), true );
	}

	// --------------------------------------------------------------------
	// Per-event handlers
	// --------------------------------------------------------------------

	private static function handle_subscription_created( array $event ): string {
		$role = trim( (string) \get_option( self::OPTION_ROLE_ON_CREATED, '' ) );
		if ( '' === $role ) {
			return 'no_role_configured';
		}
		$user = self::lookup_user_for_event( $event );
		if ( null === $user ) {
			return 'no_user';
		}
		self::add_role_if_missing( $user, $role );
		return 'role_added:' . $role;
	}

	private static function handle_subscription_updated( array $event ): string {
		$status = isset( $event['data']['object']['status'] ) ? (string) $event['data']['object']['status'] : '';
		switch ( $status ) {
			case 'active':
			case 'trialing':
				return self::handle_subscription_created( $event );
			case 'past_due':
			case 'unpaid':
				return self::handle_subscription_past_due( $event );
			case 'canceled':
			case 'incomplete_expired':
				return self::handle_subscription_deleted( $event );
		}
		return 'noop:status_' . $status;
	}

	private static function handle_subscription_deleted( array $event ): string {
		$role = trim( (string) \get_option( self::OPTION_ROLE_ON_CREATED, '' ) );
		if ( '' === $role ) {
			return 'no_role_configured';
		}
		$user = self::lookup_user_for_event( $event );
		if ( null === $user ) {
			return 'no_user';
		}
		$downgrade = trim( (string) \get_option( self::OPTION_ROLE_ON_DELETED, '' ) );
		self::remove_role_if_present( $user, $role );
		if ( '' !== $downgrade ) {
			self::add_role_if_missing( $user, $downgrade );
			return 'role_removed:' . $role . ',role_added:' . $downgrade;
		}
		return 'role_removed:' . $role;
	}

	private static function handle_invoice_payment_failed( array $event ): string {
		$role = trim( (string) \get_option( self::OPTION_ROLE_WHEN_PAST_DUE, '' ) );
		if ( '' === $role ) {
			return 'no_past_due_role';
		}
		$user = self::lookup_user_for_event( $event );
		if ( null === $user ) {
			return 'no_user';
		}
		self::add_role_if_missing( $user, $role );
		return 'role_added:' . $role;
	}

	private static function handle_subscription_past_due( array $event ): string {
		return self::handle_invoice_payment_failed( $event );
	}

	// --------------------------------------------------------------------
	// User lookup + role mutation helpers
	// --------------------------------------------------------------------

	/**
	 * Resolve the WP user for a Stripe event. Tries (in order):
	 *   1. Look up user with _lscp_stripe_customer_id meta matching the event's customer.
	 *   2. If event carries a recipient email, fall back to get_user_by('email').
	 */
	public static function lookup_user_for_event( array $event ): ?object {
		$customer_id = self::extract_customer_id( $event );
		if ( '' !== $customer_id ) {
			$users = self::find_users_by_meta( UserBridge::META_CUSTOMER_ID, $customer_id );
			if ( ! empty( $users ) ) {
				return $users[0];
			}
		}
		$email = self::extract_email( $event );
		if ( '' !== $email ) {
			$user = \get_user_by( 'email', $email );
			if ( $user && is_object( $user ) ) {
				return $user;
			}
		}
		return null;
	}

	public static function extract_customer_id( array $event ): string {
		$obj = $event['data']['object'] ?? array();
		if ( ! is_array( $obj ) ) {
			return '';
		}
		// invoice events carry the customer id directly; subscription events too.
		if ( isset( $obj['customer'] ) && is_string( $obj['customer'] ) ) {
			return $obj['customer'];
		}
		return '';
	}

	public static function extract_email( array $event ): string {
		$obj = $event['data']['object'] ?? array();
		if ( ! is_array( $obj ) ) {
			return '';
		}
		if ( isset( $obj['customer_email'] ) && is_string( $obj['customer_email'] ) ) {
			return $obj['customer_email'];
		}
		return '';
	}

	/**
	 * Lightweight wrapper around get_users() that returns matching users
	 * by meta key + value. Returns array of user objects.
	 *
	 * @return array<int,object>
	 */
	private static function find_users_by_meta( string $key, string $value ): array {
		if ( ! function_exists( '\get_users' ) ) {
			return array();
		}
		$users = \get_users(
			array(
				'meta_key'   => $key,
				'meta_value' => $value,
				'number'     => 1,
				'fields'     => 'all',
			)
		);
		return is_array( $users ) ? $users : array();
	}

	private static function add_role_if_missing( object $user, string $role ): void {
		if ( ! isset( $user->roles ) || ! is_array( $user->roles ) ) {
			return;
		}
		if ( in_array( $role, $user->roles, true ) ) {
			return;
		}
		if ( method_exists( $user, 'add_role' ) ) {
			$user->add_role( $role );
		}
	}

	private static function remove_role_if_present( object $user, string $role ): void {
		if ( ! isset( $user->roles ) || ! is_array( $user->roles ) ) {
			return;
		}
		if ( ! in_array( $role, $user->roles, true ) ) {
			return;
		}
		if ( method_exists( $user, 'remove_role' ) ) {
			$user->remove_role( $role );
		}
	}
}
