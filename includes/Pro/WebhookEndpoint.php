<?php
/**
 * REST endpoint that receives Stripe webhook deliveries. Lives at
 * /wp-json/lscp/v1/webhook.
 *
 * Permission check IS the signature verification — the route's
 * permission_callback is __return_true (Stripe doesn't authenticate
 * via WP nonces or cookies) and the handler rejects unsigned bodies.
 *
 * Response codes:
 *   200 — event processed (or replayed; idempotency hit)
 *   400 — missing / malformed body or signature header
 *   401 — signature verification failed
 *   500 — handler threw (Stripe will retry)
 *
 * @package LSCP\Pro
 *
 * @fs_premium_only
 */

declare( strict_types = 1 );

namespace LSCP\Pro;

use LSCP\RateLimiter;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class WebhookEndpoint {

	public const OPTION_SECRET    = 'lscp_pro_webhook_secret';
	public const REST_NAMESPACE   = 'lscp/v1';
	public const REST_ROUTE       = '/webhook';

	public static function register(): void {
		\add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
	}

	public static function register_route(): void {
		\register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'handle_request' ),
			)
		);
	}

	/**
	 * Public REST handler. Accepts a WP_REST_Request and returns a
	 * WP_REST_Response (or WP_Error).
	 *
	 * @param mixed $request WP_REST_Request (typed loosely so the unit
	 *                       suite can call with any object exposing
	 *                       get_body() + get_header()).
	 */
	public static function handle_request( $request ) {
		$secret = (string) \get_option( self::OPTION_SECRET, '' );
		if ( '' === $secret ) {
			return self::respond( 503, array( 'error' => 'webhook_secret_not_configured' ) );
		}

		$body      = is_object( $request ) && method_exists( $request, 'get_body' ) ? (string) $request->get_body() : '';
		$signature = '';
		if ( is_object( $request ) && method_exists( $request, 'get_header' ) ) {
			$signature = (string) $request->get_header( 'stripe_signature' );
			if ( '' === $signature ) {
				$signature = (string) $request->get_header( 'Stripe-Signature' );
			}
		}

		if ( '' === $body ) {
			return self::respond( 400, array( 'error' => 'empty_body' ) );
		}
		if ( '' === $signature ) {
			return self::respond( 400, array( 'error' => 'missing_signature_header' ) );
		}

		if ( ! WebhookSignature::verify( $body, $signature, $secret ) ) {
			return self::respond( 401, array( 'error' => 'invalid_signature' ) );
		}

		$event = json_decode( $body, true );
		if ( ! is_array( $event ) ) {
			return self::respond( 400, array( 'error' => 'malformed_json' ) );
		}

		$event_id = isset( $event['id'] ) ? (string) $event['id'] : '';
		if ( '' === $event_id ) {
			return self::respond( 400, array( 'error' => 'missing_event_id' ) );
		}

		if ( ! WebhookIdempotency::mark_seen( $event_id ) ) {
			return self::respond(
				200,
				array(
					'replayed' => true,
					'event_id' => $event_id,
				)
			);
		}

		try {
			$summary = WebhookRules::dispatch( $event );
		} catch ( \Throwable $e ) {
			return self::respond(
				500,
				array(
					'error'   => 'handler_threw',
					'message' => $e->getMessage(),
				)
			);
		}

		return self::respond(
			200,
			array(
				'received' => true,
				'event_id' => $event_id,
				'type'     => isset( $event['type'] ) ? (string) $event['type'] : '',
				'result'   => $summary,
			)
		);
	}

	/**
	 * Build a REST response. Returns a WP_REST_Response when the class
	 * exists, otherwise a plain array (test environments).
	 *
	 * @param array<string,mixed> $payload
	 */
	private static function respond( int $status, array $payload ) {
		if ( class_exists( '\WP_REST_Response' ) ) {
			return new \WP_REST_Response( $payload, $status );
		}
		return array(
			'status' => $status,
			'body'   => $payload,
		);
	}
}
