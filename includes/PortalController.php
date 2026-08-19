<?php
/**
 * Glue layer for the magic-link login flow.
 *
 * On every front-end request:
 *  1. If the request POSTs the form nonce, process an email submission:
 *     rate-limit → optionally check existence in Stripe → issue magic link.
 *     After processing, redirect back to the host page with a status query
 *     arg (`lscp_message=sent` or `lscp_error=...`) so the page chrome /
 *     theme is preserved instead of the user landing on a bare wp_die screen.
 *     The user always sees the same neutral confirmation (no enumeration
 *     oracle): every "the form was submitted" outcome redirects with
 *     `lscp_message=sent` regardless of whether mail was actually issued.
 *  2. If the rewrite query var is set:
 *     a. With ?token=... — redeem the token, look up / create the customer,
 *        and redirect to the Stripe Billing Portal session URL.
 *     b. With ?lscp_message=... or ?lscp_error=... — render the message
 *        page (after a form POST that landed back at the endpoint).
 *     c. Otherwise — render the email form full-page.
 *
 * Extension surface (Phase 0 of 1.1.0):
 *  - filter `lscp_post_redirect_url` — override the redirect target after POST.
 *  - filter `lscp_post_message` — override the confirmation message string.
 *  - filter `lscp_message_query_args` — override the query args appended.
 *  - action `lscp_magic_link_sent` — fires after a magic-link email is sent.
 *  - action `lscp_magic_link_redeemed` — fires after a token is consumed.
 *  - filter `lscp_login_url` — override the magic-link URL embedded in mail.
 *  - filter `lscp_return_url` — override the post-portal return URL.
 *
 * @package LSCP
 */

declare( strict_types = 1 );

namespace LSCP;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

class PortalController {

	/** Query arg used on redirect back to host page after successful submission. */
	public const QUERY_MESSAGE = 'lscp_message';

	/** Query arg used on redirect back to host page after a recoverable error. */
	public const QUERY_ERROR = 'lscp_error';

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

		// 2c. Post-redirect landing — show the inline message instead of the form.
		if ( isset( $_GET[ self::QUERY_MESSAGE ] ) || isset( $_GET[ self::QUERY_ERROR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display of own-redirect query args.
			self::render_message_page();
			exit;
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

		// Nonce failure is the one outcome that does NOT redirect back: this
		// is a genuine security stop, not a user-facing flow. Show the bare
		// wp_die page so the failure is obvious and not silently consumed.
		if ( ! \wp_verify_nonce( $nonce, FormRenderer::NONCE_ACTION ) ) {
			\wp_die( \esc_html__( 'Security check failed', 'login-stripe-customer-portal' ) );
		}

		if ( ! isset( $_POST['email'] ) ) {
			// Same neutral redirect as success — no enumeration oracle on
			// "field was missing" either.
			$this->redirect_with_status( array( self::QUERY_MESSAGE => 'sent' ) );
			return;
		}

		$email = \sanitize_email( \wp_unslash( (string) $_POST['email'] ) );

		// Rate-limit by hashed identity (email + IP). Always redirect with the
		// neutral "sent" status even on throttle.
		$identity = $email . '|' . self::client_ip();
		if ( ! $this->limiter->check_and_hit( $identity ) ) {
			$this->redirect_with_status( array( self::QUERY_MESSAGE => 'sent' ) );
			return;
		}

		if ( ! \is_email( $email ) ) {
			// Same neutral redirect — never tell the caller their input was malformed.
			$this->redirect_with_status( array( self::QUERY_MESSAGE => 'sent' ) );
			return;
		}

		$this->maybe_send_login_email( $email );

		$this->redirect_with_status( array( self::QUERY_MESSAGE => 'sent' ) );
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

		$sent = $this->mailer->send_magic_link( $email, $login_url );

		/**
		 * Fires after a magic-link email send attempt.
		 *
		 * Subscribers can log, mirror to a CRM, or trigger a follow-up.
		 *
		 * @param string $email     The recipient email.
		 * @param string $token     The issued token (do NOT log raw — it's an auth secret).
		 * @param string $login_url The full magic-link URL embedded in the mail.
		 * @param bool   $sent      The wp_mail return value.
		 */
		\do_action( 'lscp_magic_link_sent', $email, $token, $login_url, $sent );

		return $sent;
	}

	private function handle_token_redemption( string $token ): void {
		$email = $this->tokens->consume( $token );
		if ( '' === $email ) {
			\wp_die( \esc_html__( 'Invalid or expired token.', 'login-stripe-customer-portal' ), '', array( 'response' => 403 ) );
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

		/**
		 * Fires immediately before the redirect to the Stripe Billing Portal.
		 *
		 * @param string $email       The redeemed email.
		 * @param string $customer_id The Stripe customer id.
		 * @param string $portal_url  The Stripe Billing Portal session URL.
		 */
		\do_action( 'lscp_magic_link_redeemed', $email, $customer_id, $portal_url );

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
		$url  = \add_query_arg( array( 'token' => $token ), \home_url( $path ) );

		/**
		 * Filter the magic-link URL embedded in outbound mail.
		 *
		 * @param string $url   The default URL.
		 * @param string $token The issued token.
		 * @param string $slug  The configured endpoint slug.
		 */
		$filtered = \apply_filters( 'lscp_login_url', $url, $token, $slug );
		return is_string( $filtered ) && '' !== $filtered ? $filtered : $url;
	}

	private function return_url(): string {
		$configured = (string) \get_option( Settings::OPTION_REDIRECT_URL, '' );
		if ( '' !== $configured ) {
			$url = $configured;
		} else {
			$slug = (string) \get_option( Settings::OPTION_ENDPOINT_SLUG, Settings::DEFAULT_SLUG );
			$slug = Sanitizer::sanitize_endpoint_slug( $slug );
			$url  = '' === $slug ? \home_url( '/' ) : \home_url( '/' . $slug . '/' );
		}

		/**
		 * Filter the post-portal return URL passed to Stripe.
		 *
		 * @param string $url The default return URL.
		 */
		$filtered = \apply_filters( 'lscp_return_url', $url );
		return is_string( $filtered ) && '' !== $filtered ? $filtered : $url;
	}

	/**
	 * Neutral, mode-aware confirmation message returned to the form submitter.
	 *
	 * Public so the legacy facade + tests can reach it without re-implementing
	 * the branching logic.
	 *
	 * Filterable via `lscp_post_message` so PRO / customizers can tweak.
	 */
	public static function confirmation_message(): string {
		$validate_existing = '1' === (string) \get_option( Settings::OPTION_VALIDATE_EXISTING, '0' );

		if ( $validate_existing ) {
			$msg = \__(
				'If your email address is associated with a Stripe customer, a login link is on its way. Please check your inbox.',
				'login-stripe-customer-portal'
			);
		} else {
			$msg = \__(
				'A login link is on its way. Please check your inbox for the link to access your Stripe Customer Portal.',
				'login-stripe-customer-portal'
			);
		}

		/**
		 * Filter the confirmation message shown after a form POST.
		 *
		 * @param string $msg              The default message.
		 * @param bool   $validate_existing Whether the gating toggle is on.
		 */
		$filtered = \apply_filters( 'lscp_post_message', $msg, $validate_existing );
		return is_string( $filtered ) && '' !== $filtered ? $filtered : $msg;
	}

	/**
	 * Render the inline message that replaces the form after a redirect-back.
	 *
	 * Reads QUERY_MESSAGE / QUERY_ERROR from the current request. Used by:
	 *   - PortalController::dispatch() when the rewrite endpoint is hit with
	 *     a query arg (full-page render).
	 *   - The `[lscp-message]` shortcode (inline on the host page).
	 *
	 * Returns the rendered HTML; pass `$echo = true` to echo + return.
	 */
	public static function render_message_html(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display of own redirect query args.
		$message_code = isset( $_GET[ self::QUERY_MESSAGE ] )
			? \sanitize_key( \wp_unslash( (string) $_GET[ self::QUERY_MESSAGE ] ) )
			: '';
		$error_code   = isset( $_GET[ self::QUERY_ERROR ] )
			? \sanitize_key( \wp_unslash( (string) $_GET[ self::QUERY_ERROR ] ) )
			: '';
		// phpcs:enable

		if ( '' === $message_code && '' === $error_code ) {
			return '';
		}

		$is_error = '' !== $error_code;
		$class    = $is_error ? 'lscp-message lscp-message--error' : 'lscp-message lscp-message--success';
		$text     = $is_error ? self::error_message_for_code( $error_code ) : self::confirmation_message();

		return sprintf(
			'<div class="%1$s" role="status" style="padding:16px;margin:16px 0;border-radius:6px;background:%2$s;border:1px solid %3$s;color:%4$s;">%5$s</div>',
			\esc_attr( $class ),
			$is_error ? '#fef1f1' : '#f0f6fc',
			$is_error ? '#d63638' : '#2271b1',
			$is_error ? '#a02b2b' : '#1d4670',
			\esc_html( $text )
		);
	}

	private static function render_message_page(): void {
		// Minimal HTML scaffold — preserves the original "centered card" layout
		// the rewrite endpoint used for the form.
		$html = self::render_message_html();
		?>
		<!doctype html>
		<html lang="<?php echo \esc_attr( \get_bloginfo( 'language' ) ); ?>">
		<head>
			<meta charset="<?php echo \esc_attr( \get_bloginfo( 'charset' ) ); ?>">
			<meta name="viewport" content="width=device-width,initial-scale=1">
			<title><?php echo \esc_html__( 'Login Message', 'login-stripe-customer-portal' ); ?></title>
		</head>
		<body style="font-family:system-ui,sans-serif;background:#f4f4f4;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;">
			<main style="max-width:500px;width:90%;background:#fff;padding:32px;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,0.08);text-align:center;">
				<?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_message_html escapes its own output. ?>
			</main>
		</body>
		</html>
		<?php
	}

	private static function error_message_for_code( string $code ): string {
		switch ( $code ) {
			case 'nonce_failed':
				return \__( 'Security check failed. Please refresh and try again.', 'login-stripe-customer-portal' );
			case 'invalid_token':
				return \__( 'Invalid or expired token.', 'login-stripe-customer-portal' );
			default:
				return \__( 'Something went wrong. Please try again.', 'login-stripe-customer-portal' );
		}
	}

	private function redirect_with_status( array $query_args ): void {
		// Phase 0 fix for sc-7015: never leave the user on a blank wp_die screen
		// after POST. Redirect back to the host page they submitted from with
		// a status query arg so the page chrome / theme is preserved and
		// `[lscp-message]` (or our own /endpoint/ message renderer) can show
		// the message inline.
		//
		// Preference order:
		//   1. wp_get_referer() — but WP returns false when REFERER matches
		//      REQUEST_URI (very common: form POSTs back to itself), so this
		//      misses the self-referential case.
		//   2. REQUEST_URI on the current host — handles the self-referential
		//      case correctly: user stays on the page they submitted from.
		//   3. home_url() — last resort if even REQUEST_URI is unavailable.
		$default_target = \wp_get_referer();
		if ( ! is_string( $default_target ) || '' === $default_target ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
			if ( '' !== $request_uri ) {
				// Strip any existing query string — we'll re-append our own status args below.
				$path           = (string) parse_url( $request_uri, PHP_URL_PATH );
				$default_target = \home_url( '' !== $path ? $path : '/' );
			} else {
				$default_target = \home_url( '/' );
			}
		}

		/**
		 * Filter the per-request query args appended to the redirect URL.
		 *
		 * @param array $query_args Defaults to ['lscp_message' => 'sent'].
		 */
		$query_args = \apply_filters( 'lscp_message_query_args', $query_args );
		if ( ! is_array( $query_args ) || array() === $query_args ) {
			$query_args = array( self::QUERY_MESSAGE => 'sent' );
		}

		$url = \add_query_arg( $query_args, $default_target );

		/**
		 * Filter the final redirect URL after a form POST.
		 *
		 * @param string $url        The default redirect URL.
		 * @param array  $query_args The status args being appended.
		 */
		$url = (string) \apply_filters( 'lscp_post_redirect_url', $url, $query_args );

		\wp_safe_redirect( $url );
		exit;
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
