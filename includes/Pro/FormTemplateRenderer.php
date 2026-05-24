<?php
/**
 * Pure helper for the LSCP PRO form-styler. Substitutes admin-configured
 * branding into the chosen form-template file and returns the HTML the
 * `lscp_form_template` filter feeds back to FormRenderer.
 *
 * Placeholders supported in templates:
 *   {{primary_color}}  — escaped 7-char hex (always begins with #)
 *   {{heading}}        — escaped heading text
 *   {{subheading}}     — escaped supporting text below heading
 *   {{button_text}}    — escaped submit-button label
 *   {{placeholder}}    — escaped email-input placeholder
 *   {{form_id}}        — escaped per-render unique id (for label-for binding)
 *   {{nonce_field}}    — pre-built hidden nonce input HTML (already safe)
 *   {{default_email}}  — escaped default email value
 *
 * Templates that omit `{{nonce_field}}` will fail the FREE PortalController's
 * nonce check — that's by design (security boundary).
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

final class FormTemplateRenderer {

	public const DEFAULT_TEMPLATE      = 'minimal';
	public const DEFAULT_PRIMARY_COLOR = '#0073aa';
	public const DEFAULT_HEADING       = 'Log in';
	public const DEFAULT_SUBHEADING    = 'Enter your email and we will send you a magic link.';
	public const DEFAULT_BUTTON_TEXT   = 'Send login link';
	public const DEFAULT_PLACEHOLDER   = 'you@example.com';

	/**
	 * Whitelist of form-template slugs. Every value must map to a file at
	 * assets/pro/form-templates/<slug>.php.
	 *
	 * @return array<string,string> slug => human label
	 */
	public static function templates(): array {
		return array(
			'minimal'   => 'Minimal',
			'card'      => 'Card',
			'inline'    => 'Inline',
			'fullwidth' => 'Full-width',
			'centered'  => 'Centered',
			'branded'   => 'Branded',
		);
	}

	public static function resolve_slug( string $slug ): string {
		$slug = strtolower( trim( $slug ) );
		return isset( self::templates()[ $slug ] ) ? $slug : self::DEFAULT_TEMPLATE;
	}

	/**
	 * Render the chosen template with the given context substituted in.
	 *
	 * @param string               $slug    Template slug (whitelisted).
	 * @param array<string,string> $context Substitution values.
	 */
	public static function render( string $slug, array $context ): string {
		$slug = self::resolve_slug( $slug );
		$path = self::template_dir() . $slug . '.php';
		if ( ! is_file( $path ) ) {
			$path = self::template_dir() . self::DEFAULT_TEMPLATE . '.php';
		}
		$raw = (string) file_get_contents( $path );
		return self::substitute( $raw, $context );
	}

	/**
	 * Build the substitution context from the form-render args + stored
	 * branding options. Caller is responsible for passing a $form_id that's
	 * unique per render (FormRenderer's existing helper already does this).
	 *
	 * @param array<string,string> $request  Keys: form_id, default_email, nonce_field
	 * @param array<string,string> $branding Keys: primary_color, heading, subheading, button_text, placeholder
	 * @return array<string,string>
	 */
	public static function build_context( array $request, array $branding ): array {
		return array(
			'form_id'       => (string) ( $request['form_id'] ?? 'lscp-email' ),
			'default_email' => (string) ( $request['default_email'] ?? '' ),
			'nonce_field'   => (string) ( $request['nonce_field'] ?? '' ),
			'primary_color' => self::normalize_color( (string) ( $branding['primary_color'] ?? self::DEFAULT_PRIMARY_COLOR ) ),
			'heading'       => '' !== (string) ( $branding['heading'] ?? '' ) ? (string) $branding['heading'] : self::DEFAULT_HEADING,
			'subheading'    => '' !== (string) ( $branding['subheading'] ?? '' ) ? (string) $branding['subheading'] : self::DEFAULT_SUBHEADING,
			'button_text'   => '' !== (string) ( $branding['button_text'] ?? '' ) ? (string) $branding['button_text'] : self::DEFAULT_BUTTON_TEXT,
			'placeholder'   => '' !== (string) ( $branding['placeholder'] ?? '' ) ? (string) $branding['placeholder'] : self::DEFAULT_PLACEHOLDER,
		);
	}

	public static function normalize_color( string $color ): string {
		$color = trim( $color );
		if ( '' === $color ) {
			return self::DEFAULT_PRIMARY_COLOR;
		}
		if ( '#' !== $color[0] ) {
			$color = '#' . $color;
		}
		if ( preg_match( '/^#[0-9a-fA-F]{3}$/', $color ) ) {
			$color = '#' . str_repeat( $color[1], 2 ) . str_repeat( $color[2], 2 ) . str_repeat( $color[3], 2 );
		}
		return preg_match( '/^#[0-9a-fA-F]{6}$/', $color ) ? strtolower( $color ) : self::DEFAULT_PRIMARY_COLOR;
	}

	/**
	 * Substitute placeholders. Per-context escaping:
	 *   nonce_field is trusted HTML (built by wp_nonce_field).
	 *   form_id, default_email, primary_color → esc_attr
	 *   heading, subheading, button_text, placeholder → esc_html
	 *
	 * @param array<string,string> $context
	 */
	public static function substitute( string $template, array $context ): string {
		$esc_attr = function ( $v ) {
			return function_exists( 'esc_attr' ) ? \esc_attr( (string) $v ) : (string) $v;
		};
		$esc_html = function ( $v ) {
			return function_exists( 'esc_html' ) ? \esc_html( (string) $v ) : (string) $v;
		};

		$replacements = array(
			'{{form_id}}'       => $esc_attr( $context['form_id'] ?? 'lscp-email' ),
			'{{default_email}}' => $esc_attr( $context['default_email'] ?? '' ),
			'{{nonce_field}}'   => (string) ( $context['nonce_field'] ?? '' ),
			'{{primary_color}}' => $esc_attr( self::normalize_color( (string) ( $context['primary_color'] ?? self::DEFAULT_PRIMARY_COLOR ) ) ),
			'{{heading}}'       => $esc_html( $context['heading'] ?? self::DEFAULT_HEADING ),
			'{{subheading}}'    => $esc_html( $context['subheading'] ?? self::DEFAULT_SUBHEADING ),
			'{{button_text}}'   => $esc_attr( $context['button_text'] ?? self::DEFAULT_BUTTON_TEXT ),
			'{{placeholder}}'   => $esc_attr( $context['placeholder'] ?? self::DEFAULT_PLACEHOLDER ),
		);

		return strtr( $template, $replacements );
	}

	public static function template_dir(): string {
		$base = defined( 'LSCP_PRO_FORM_TEMPLATES_DIR' )
			? (string) constant( 'LSCP_PRO_FORM_TEMPLATES_DIR' )
			: ( defined( 'LSCP_PLUGIN_DIR' ) ? (string) constant( 'LSCP_PLUGIN_DIR' ) . 'assets/pro/form-templates/' : __DIR__ . '/../../assets/pro/form-templates/' );
		return rtrim( $base, '/' ) . '/';
	}
}
