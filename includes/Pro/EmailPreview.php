<?php
/**
 * REST endpoint that renders a live preview of the branded magic-link
 * email using values posted from the Email Templates settings tab.
 *
 * Lives at GET /wp-json/lscp/v1/email-preview. Requires manage_options
 * — admins only. The Email Templates settings form embeds an iframe
 * pointing at this route and updates the iframe `src` (with query
 * params from each input) on every field change, so the admin can see
 * exactly what their customers will receive without sending a test.
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

final class EmailPreview {

	public const REST_NAMESPACE = 'lscp/v1';
	public const REST_ROUTE     = '/email-preview';

	public static function register(): void {
		\add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
	}

	public static function register_route(): void {
		\register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => 'GET',
				'permission_callback' => array( __CLASS__, 'permission_check' ),
				'callback'            => array( __CLASS__, 'handle_request' ),
			)
		);
	}

	public static function permission_check(): bool {
		return function_exists( '\current_user_can' ) && \current_user_can( 'manage_options' );
	}

	/**
	 * Render the email and return it as HTML (Content-Type: text/html)
	 * so the iframe renders it directly.
	 *
	 * @param mixed $request WP_REST_Request.
	 */
	public static function handle_request( $request ) {
		$template      = self::param( $request, 'template', EmailRenderer::DEFAULT_TEMPLATE );
		$primary_color = self::param( $request, 'primary_color', EmailRenderer::DEFAULT_PRIMARY_COLOR );
		$heading       = self::param( $request, 'heading', '' );
		$cta_text      = self::param( $request, 'cta_text', '' );
		$footer_text   = self::param( $request, 'footer_text', '' );
		$logo_url      = self::param( $request, 'logo_url', '' );

		$site_name = function_exists( '\get_bloginfo' ) ? (string) \get_bloginfo( 'name' ) : 'Your Site';

		$ctx = EmailRenderer::build_context(
			array(
				'email'     => 'customer@example.com',
				'login_url' => function_exists( '\home_url' ) ? \home_url( '/customer-portal/?token=preview' ) : 'https://example.com/customer-portal/?token=preview',
				'site_name' => $site_name,
			),
			array(
				'logo_url'      => $logo_url,
				'primary_color' => $primary_color,
				'heading'       => $heading,
				'cta_text'      => $cta_text,
				'footer_text'   => $footer_text,
			)
		);

		$html = EmailRenderer::render( $template, $ctx );

		if ( class_exists( '\WP_REST_Response' ) ) {
			$response = new \WP_REST_Response( $html, 200 );
			$response->header( 'Content-Type', 'text/html; charset=utf-8' );
			$response->header( 'X-Robots-Tag', 'noindex,nofollow' );
			return $response;
		}
		return $html;
	}

	private static function param( $request, string $name, string $fallback ): string {
		if ( is_object( $request ) && method_exists( $request, 'get_param' ) ) {
			$value = $request->get_param( $name );
			if ( is_string( $value ) && '' !== $value ) {
				return $value;
			}
		}
		return $fallback;
	}
}
