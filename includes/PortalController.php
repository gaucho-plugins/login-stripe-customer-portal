<?php
/**
 * Glue layer for the magic-link login flow.
 *
 * On every front-end request:
 *  1. If the request POSTs the form nonce, process an email submission:
 *     rate-limit → optionally check existence in Stripe → issue magic link.
 *     The user always sees the same neutral confirmation (no enumeration oracle).
 *  2. If the rewrite query var is set:
 *     a. With ?token=... — redeem the token, look up / create the customer,
 *        and redirect to the Stripe Billing Portal session URL.
 *     b. Without a token — render the email form full-page.
 *
 * @package LSCP
 */

declare( strict_types = 1 );

namespace LSCP;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class PortalController {

	/** @var TokenStore */
	private $tokens;

	/** @var Mailer */
	private $mailer;

	/** @var RateLimiter */
	private $limiter;

	/** @var callable|null Factory: (string $api_key) => StripeGateway */
	private $gateway_factory;

	public function __construct(
		?TokenStore $tokens = null,
		?Mailer $mailer = null,
		?RateLimiter $limiter = null,
		?callable $gateway_factory = null
	) {
		$this->tokens          = $tokens ?: new TokenStore();
		$this->mailer          = $mailer ?: new Mailer();
		$this->limiter         = $limiter ?: new RateLimiter();
		$this->gateway_factory = $gateway_factory ?: static function ( string $key ): StripeGateway {
			return new StripeGateway( $key );
		};
	}

	public function register_hooks(): void {
		\add_action( 'template_redirect', array( $this, 'dispatch' ) );
	}

	public function dispatch(): void {
		// 1. POST form submission. The dispatch must inspect REQUEST_METHOD and
		// the nonce field name before nonce verification (we only verify the
		// nonce *inside* handle_form_post, on a freshly-unslashed copy).
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if (
			isset( $_SERVER['REQUEST_METHOD'] )
			&& 'POST' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] )
			&& isset( $_POST[ FormRenderer::NONCE_NAME ] )
		) {
			$this->handle_form_post();
			return;
		}
		// phpcs:enable

		// 2. Endpoint hit.
		global $wp_query;
		if ( ! isset( $wp_query->query_vars[ RewriteEndpoint::QUERY_VAR ] ) ) {
			return;
		}

		if ( isset( $_GET['token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- token itself is the auth secret; consumed atomically.
			$this->handle_token_redemption( \sanitize_text_field( \wp_unslash( $_GET['token'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		FormRenderer::render();
		exit;
	}

	private function handle_form_post(): void {
		$nonce = isset( $_POST[ FormRenderer::NONCE_NAME ] )
			? \sanitize_text_field( \wp_unslash( $_POST[ FormRenderer::NONCE_NAME ] ) )
			: '';

		if ( ! \wp_verify_nonce( $nonce, FormRenderer::NONCE_ACTION ) ) {
			\wp_die( \esc_html__( 'Security check failed', 'login-stripe-customer-portal' ) );
		}

		$message = self::confirmation_message();
		$title   = \__( 'Login Message', 'login-stripe-customer-portal' );

		if ( ! isset( $_POST['email'] ) ) {
			\wp_die( \esc_html( $message ), \esc_html( $title ) );
		}

		$email = \sanitize_email( \wp_unslash( (string) $_POST['email'] ) );

		// Rate-limit by hashed identity (email + IP). Always emit the neutral
		// confirmation even on throttle, so the response is constant-time-ish.
		$identity = $email . '|' . self::client_ip();
		if ( ! $this->limiter->check_and_hit( $identity ) ) {
			\wp_die( \esc_html( $message ), \esc_html( $title ) );
		}

		if ( ! \is_email( $email ) ) {
			// Same neutral response — never tell the caller their input was malformed.
			\wp_die( \esc_html( $message ), \esc_html( $title ) );
		}

		$this->maybe_send_login_email( $email );

		\wp_die( \esc_html( $message ), \esc_html( $title ) );
	}

	/**
	 * Public so the legacy `send_login_email` shim on Plugin can delegate here.
	 */
	public function maybe_send_login_email( string $email ): bool {
		$validate_existing = '1' === (string) \get_option( Settings::OPTION_VALIDATE_EXISTING, '0' );

		if ( $validate_existing ) {
			$gateway = $this->build_gateway();
			if ( null === $gateway || ! $gateway->has_api_key() ) {
				return false;
			}
			try {
				$customer_id = $gateway->find_customer_id( $email );
			} catch ( StripeGatewayException $e ) {
				$this->log_error( 'find_customer_id', $e );
				return false;
			}
			if ( null === $customer_id ) {
				return false;
			}
		}

		$token     = $this->tokens->issue( $email );
		$login_url = $this->build_login_url( $token );

		return $this->mailer->send_magic_link( $email, $login_url );
	}

	private function handle_token_redemption( string $token ): void {
		$email = $this->tokens->consume( $token );
		if ( '' === $email ) {
			\wp_die( \esc_html__( 'Invalid or expired token.', 'login-stripe-customer-portal' ) );
		}

		$gateway = $this->build_gateway();
		if ( null === $gateway || ! $gateway->has_api_key() ) {
			\wp_die( \esc_html__( 'Stripe is not configured. Please contact the site administrator.', 'login-stripe-customer-portal' ) );
		}

		$validate_existing = '1' === (string) \get_option( Settings::OPTION_VALIDATE_EXISTING, '0' );

		try {
			$customer_id = $gateway->find_customer_id( $email );

			if ( null === $customer_id ) {
				if ( $validate_existing ) {
					\wp_die( \esc_html__( 'No matching customer found.', 'login-stripe-customer-portal' ) );
				}
				$customer_id = $gateway->create_customer( $email );
			}

			$portal_url = $gateway->create_portal_session( $customer_id, $this->return_url() );
		} catch ( StripeGatewayException $e ) {
			$this->log_error( 'token_redemption', $e );
			\wp_die( \esc_html__( 'Unable to open the Stripe Customer Portal. Please try again later.', 'login-stripe-customer-portal' ) );
		}

		// wp_safe_redirect would reject the Stripe-hosted URL because it's on a
		// different host. The destination IS Stripe; we intentionally leave the site.
		\wp_redirect( \esc_url_raw( $portal_url ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	private function build_gateway(): ?StripeGateway {
		$key = (string) \get_option( Settings::OPTION_API_KEY, '' );
		if ( '' === trim( $key ) ) {
			return null;
		}
		$factory = $this->gateway_factory;
		$gateway = $factory( $key );
		return $gateway instanceof StripeGateway ? $gateway : null;
	}

	public function build_login_url( string $token ): string {
		$slug = (string) \get_option( Settings::OPTION_ENDPOINT_SLUG, Settings::DEFAULT_SLUG );
		$slug = Sanitizer::sanitize_endpoint_slug( $slug );
		// If the slug is disabled, fall back to the home URL — the user will
		// still get a (broken) link rather than a fatal during email send.
		$path = '' === $slug ? '/' : '/' . $slug . '/';
		return \add_query_arg( array( 'token' => $token ), \home_url( $path ) );
	}

	private function return_url(): string {
		$configured = (string) \get_option( Settings::OPTION_REDIRECT_URL, '' );
		if ( '' !== $configured ) {
			return $configured;
		}
		$slug = (string) \get_option( Settings::OPTION_ENDPOINT_SLUG, Settings::DEFAULT_SLUG );
		$slug = Sanitizer::sanitize_endpoint_slug( $slug );
		return '' === $slug ? \home_url( '/' ) : \home_url( '/' . $slug . '/' );
	}

	/**
	 * Neutral, mode-aware confirmation message returned to the form submitter.
	 *
	 * Public so the legacy facade + tests can reach it without re-implementing
	 * the branching logic.
	 *
	 * When "Only allow existing Stripe customers to login" is ON, the message
	 * is conditional ("if your email matches an account…") — true to the
	 * gatekeeping behavior and safe against email-enumeration (the same string
	 * is returned for valid, invalid, and unknown emails).
	 *
	 * When the toggle is OFF (the default), there is no gatekeeping: ANY valid
	 * email triggers a magic-link send, and a Stripe customer is auto-created
	 * at redemption. The conditional "if registered" wording was misleading
	 * here, so we surface a direct "your login link is on the way" message
	 * instead.
	 */
	public static function confirmation_message(): string {
		$validate_existing = '1' === (string) \get_option( Settings::OPTION_VALIDATE_EXISTING, '0' );

		if ( $validate_existing ) {
			return \__(
				'If your email address is associated with a Stripe customer, a login link is on its way. Please check your inbox.',
				'login-stripe-customer-portal'
			);
		}

		return \__(
			'A login link is on its way. Please check your inbox for the link to access your Stripe Customer Portal.',
			'login-stripe-customer-portal'
		);
	}

	private static function client_ip(): string {
		// $_SERVER['REMOTE_ADDR'] comes from the SAPI, not from user input; the
		// preg_replace below strips it to the hex+dot+colon character class that
		// covers both IPv4 and IPv6 — no SQL/HTML context, used only as part of
		// the rate-limit hash key. phpcs cannot reason about that constraint.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		return preg_replace( '/[^0-9a-f\.:]/i', '', $ip ) ?? '';
	}

	private function log_error( string $context, \Throwable $e ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[LSCP] %s: %s', $context, $e->getMessage() ) );
		}
	}
}
