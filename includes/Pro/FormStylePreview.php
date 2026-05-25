<?php
/**
 * REST endpoint that renders a live preview of the styled login form
 * using values posted from the Form Style settings tab. Mirrors
 * EmailPreview — same iframe-on-form-change UX.
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

final class FormStylePreview {

	public const REST_NAMESPACE = 'lscp/v1';
	public const REST_ROUTE     = '/form-preview';

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

	public static function handle_request( $request ) {
		$template      = self::param( $request, 'template', FormTemplateRenderer::DEFAULT_TEMPLATE );
		$primary_color = self::param( $request, 'primary_color', FormTemplateRenderer::DEFAULT_PRIMARY_COLOR );
		$heading       = self::param( $request, 'heading', '' );
		$subheading    = self::param( $request, 'subheading', '' );
		$button_text   = self::param( $request, 'button_text', '' );
		$placeholder   = self::param( $request, 'placeholder', '' );

		// The preview form intentionally OMITS the real nonce_field — we don't
		// want preview forms to be functional. Posting them will fail the
		// nonce check (correct: the preview is for visual review only).
		$ctx = FormTemplateRenderer::build_context(
			array(
				'form_id'       => 'lscp-preview-input',
				'default_email' => '',
				'nonce_field'   => '',
			),
			array(
				'primary_color' => $primary_color,
				'heading'       => $heading,
				'subheading'    => $subheading,
				'button_text'   => $button_text,
				'placeholder'   => $placeholder,
			)
		);

		$form_html = FormTemplateRenderer::render( $template, $ctx );

		// Wrap in a minimal HTML doc so the iframe renders with a background
		// color matching what a real page wrapper would provide.
		$doc = '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Preview</title></head>'
			. '<body style="margin:0;padding:32px;background:#f4f5f7;display:flex;justify-content:center;align-items:flex-start;min-height:100vh;">'
			. $form_html
			. '</body></html>';

		if ( class_exists( '\WP_REST_Response' ) ) {
			$response = new \WP_REST_Response( $doc, 200 );
			$response->header( 'Content-Type', 'text/html; charset=utf-8' );
			$response->header( 'X-Robots-Tag', 'noindex,nofollow' );
			return $response;
		}
		return $doc;
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
