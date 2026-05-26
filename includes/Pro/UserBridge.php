<?php
/**
 * Bridges WordPress users and Stripe customers. The two halves:
 *
 * 1. **Pre-fill** — when a logged-in WP user lands on a magic-link form,
 *    `lscp_form_default_email` fills the email input with their address so
 *    they only click submit. Suppressible via the admin toggle.
 *
 * 2. **Auto-link on redeem** — when a magic-link is redeemed, the matching
 *    Stripe customer id is stored as user meta on the WP user with the
 *    same email (creating that user first if the admin opted in). This
 *    lets integrations later look up "what Stripe customer is this WP
 *    user?" via `UserBridge::get_stripe_customer_id_for_user()`.
 *
 * The bridge is opt-in: nothing happens unless `lscp_pro_bridge_*` options
 * are configured. The defaults are conservative (pre-fill on, auto-create
 * off) so PRO activation alone doesn't mutate the user table.
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

final class UserBridge {

	public const OPTION_PREFILL_EMAIL    = 'lscp_pro_bridge_prefill_email';
	public const OPTION_AUTO_CREATE_USER = 'lscp_pro_bridge_auto_create_user';
	public const OPTION_DEFAULT_ROLE     = 'lscp_pro_bridge_default_role';

	/** Meta key the Stripe customer id is stored under. */
	public const META_CUSTOMER_ID = '_lscp_stripe_customer_id';

	public static function register(): void {
		\add_filter( 'lscp_form_default_email', array( __CLASS__, 'maybe_prefill_email' ), 10, 2 );
		\add_action( 'lscp_magic_link_redeemed', array( __CLASS__, 'on_redeem' ), 10, 3 );
	}

	/**
	 * Pre-fill the form's email input with the logged-in user's address
	 * when the admin opt-in is on. Other filters at higher priority can
	 * still override.
	 *
	 * @param string $current_default Existing default (empty in FREE).
	 * @param array  $args            Form render args.
	 */
	public static function maybe_prefill_email( $current_default, $args = array() ): string {
		if ( '' !== (string) $current_default ) {
			return (string) $current_default;
		}
		if ( ! self::prefill_enabled() ) {
			return '';
		}
		if ( ! \is_user_logged_in() ) {
			return '';
		}
		$user = \wp_get_current_user();
		if ( ! is_object( $user ) || empty( $user->user_email ) ) {
			return '';
		}
		return (string) $user->user_email;
	}

	/**
	 * Fires on magic-link redeem. Looks up (or optionally creates) the WP
	 * user, then stores the Stripe customer id on their user meta.
	 *
	 * @param string $email       Redeemer email.
	 * @param string $customer_id Stripe customer id (returned by gateway).
	 * @param string $portal_url  Final portal session URL.
	 */
	public static function on_redeem( $email, $customer_id, $portal_url = '' ): void {
		$email       = (string) $email;
		$customer_id = (string) $customer_id;
		if ( '' === $email || '' === $customer_id ) {
			return;
		}

		$user_id = self::resolve_user_id_for_email( $email );
		if ( 0 === $user_id ) {
			return;
		}

		\update_user_meta( $user_id, self::META_CUSTOMER_ID, $customer_id );

		/**
		 * Fires after the bridge has linked a Stripe customer to a WP user.
		 *
		 * @param int    $user_id     WP user id.
		 * @param string $customer_id Stripe customer id.
		 * @param string $email       Email used for the link.
		 */
		\do_action( 'lscp_pro_bridge_linked', $user_id, $customer_id, $email );
	}

	/**
	 * Reverse lookup — given a WP user, return their stored Stripe customer
	 * id (or null if no link). Used by the Phase 3 integrations to render
	 * a Manage Billing button only for users who have a portal account.
	 */
	public static function get_stripe_customer_id_for_user( int $user_id ): ?string {
		if ( $user_id <= 0 ) {
			return null;
		}
		$value = \get_user_meta( $user_id, self::META_CUSTOMER_ID, true );
		$value = is_string( $value ) ? trim( $value ) : '';
		return '' === $value ? null : $value;
	}

	public static function prefill_enabled(): bool {
		return '1' === (string) \get_option( self::OPTION_PREFILL_EMAIL, '1' );
	}

	public static function auto_create_enabled(): bool {
		return '1' === (string) \get_option( self::OPTION_AUTO_CREATE_USER, '0' );
	}

	public static function default_role(): string {
		$role = (string) \get_option( self::OPTION_DEFAULT_ROLE, 'subscriber' );
		return '' === $role ? 'subscriber' : $role;
	}

	/**
	 * Find the WP user id for an email; optionally create one when the
	 * admin opt-in is on. Returns 0 when no link should be made.
	 */
	private static function resolve_user_id_for_email( string $email ): int {
		$existing = \email_exists( $email );
		if ( false !== $existing && (int) $existing > 0 ) {
			return (int) $existing;
		}
		if ( ! self::auto_create_enabled() ) {
			return 0;
		}
		$login = self::derive_login( $email );
		if ( '' === $login ) {
			return 0;
		}
		$created = \wp_insert_user(
			array(
				'user_login' => $login,
				'user_email' => $email,
				'user_pass'  => function_exists( '\wp_generate_password' ) ? \wp_generate_password( 24 ) : bin2hex( random_bytes( 12 ) ),
				'role'       => self::default_role(),
			)
		);
		if ( is_object( $created ) && method_exists( $created, 'get_error_message' ) ) {
			return 0; // WP_Error returned
		}
		return (int) $created;
	}

	/**
	 * Derive a sane login from an email. Uniqueness-suffixed if taken.
	 */
	private static function derive_login( string $email ): string {
		$local = strtolower( strstr( $email, '@', true ) ?: $email );
		$local = preg_replace( '/[^a-z0-9._-]/', '', $local ) ?? '';
		if ( '' === $local ) {
			$local = 'user';
		}
		$candidate = $local;
		$i         = 1;
		while ( false !== \username_exists( $candidate ) && $i < 100 ) {
			++$i;
			$candidate = $local . $i;
		}
		return $candidate;
	}
}
