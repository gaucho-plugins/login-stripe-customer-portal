<?php
/**
 * Substitutes placeholders into a chosen email-template file and returns
 * the rendered HTML. Pure helper — no WP hooks, no I/O beyond reading the
 * template partial. Lives in PRO so it ships only in the premium build.
 *
 * Placeholders supported in every template (templates may use a subset):
 *   {{magic_link}}     — escaped URL of the login link
 *   {{login_url}}      — alias for {{magic_link}}
 *   {{customer_email}} — escaped recipient address
 *   {{site_name}}      — escaped site name
 *   {{logo_url}}       — escaped logo URL (empty string if not configured)
 *   {{primary_color}}  — escaped hex color, always 7 chars including leading #
 *   {{heading}}        — escaped heading text
 *   {{cta_text}}       — escaped CTA button label
 *   {{footer_text}}    — escaped footer paragraph
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

final class EmailRenderer {

	public const DEFAULT_TEMPLATE      = 'minimal';
	public const DEFAULT_PRIMARY_COLOR = '#2271b1';
	public const DEFAULT_HEADING       = 'Your login link';
	public const DEFAULT_CTA_TEXT      = 'Log in';
	public const DEFAULT_FOOTER_TEXT   = 'If you did not request this email, you can safely ignore it. The link expires in one hour.';

	/**
	 * Whitelist of template slugs. Every value must be a file at
	 * assets/pro/email-templates/<slug>.php. Lookup is whitelist-only so a
	 * user-controlled option cannot path-traverse to load arbitrary PHP.
	 *
	 * @return array<string,string> slug => human label
	 */
	public static function templates(): array {
		return array(
			'minimal'        => 'Minimal',
			'card'           => 'Card',
			'bold'           => 'Bold',
			'stripe-like'    => 'Stripe-like',
			'newsletter'     => 'Newsletter',
			'card-with-logo' => 'Card with logo',
		);
	}

	/**
	 * Resolve a slug against the whitelist; falls back to DEFAULT_TEMPLATE
	 * when the slug is unknown.
	 */
	public static function resolve_slug( string $slug ): string {
		$slug = strtolower( trim( $slug ) );
		return isset( self::templates()[ $slug ] ) ? $slug : self::DEFAULT_TEMPLATE;
	}

	/**
	 * Render the chosen template with the given context substituted in.
	 *
	 * @param string               $slug     Template slug (whitelisted).
	 * @param array<string,string> $context  Substitution values (already raw).
	 */
	public static function render( string $slug, array $context ): string {
		$slug          = self::resolve_slug( $slug );
		$template_path = self::template_dir() . $slug . '.php';
		if ( ! is_file( $template_path ) ) {
			$template_path = self::template_dir() . self::DEFAULT_TEMPLATE . '.php';
		}

		$raw = (string) file_get_contents( $template_path );

		return self::substitute( $raw, $context );
	}

	/**
	 * Build the default substitution context from a magic-link request +
	 * stored brand options. Values returned here are pre-escape; the
	 * substitute() step does the escaping.
	 *
	 * @param array<string,string> $request  Keys: email, login_url, site_name.
	 * @param array<string,string> $branding Keys: logo_url, primary_color, heading, cta_text, footer_text.
	 * @return array<string,string>
	 */
	public static function build_context( array $request, array $branding ): array {
		return array(
			'magic_link'     => (string) ( $request['login_url'] ?? '' ),
			'login_url'      => (string) ( $request['login_url'] ?? '' ),
			'customer_email' => (string) ( $request['email'] ?? '' ),
			'site_name'      => (string) ( $request['site_name'] ?? '' ),
			'logo_url'       => (string) ( $branding['logo_url'] ?? '' ),
			'primary_color'  => self::normalize_color( (string) ( $branding['primary_color'] ?? self::DEFAULT_PRIMARY_COLOR ) ),
			'heading'        => '' !== (string) ( $branding['heading'] ?? '' ) ? (string) $branding['heading'] : self::DEFAULT_HEADING,
			'cta_text'       => '' !== (string) ( $branding['cta_text'] ?? '' ) ? (string) $branding['cta_text'] : self::DEFAULT_CTA_TEXT,
			'footer_text'    => '' !== (string) ( $branding['footer_text'] ?? '' ) ? (string) $branding['footer_text'] : self::DEFAULT_FOOTER_TEXT,
		);
	}

	/**
	 * Normalize a color value to a 7-character `#rrggbb` string. Unknown
	 * inputs fall back to DEFAULT_PRIMARY_COLOR.
	 */
	public static function normalize_color( string $color ): string {
		$color = trim( $color );
		if ( '' === $color ) {
			return self::DEFAULT_PRIMARY_COLOR;
		}
		if ( '#' !== $color[0] ) {
			$color = '#' . $color;
		}
		if ( preg_match( '/^#[0-9a-fA-F]{3}$/', $color ) ) {
			// Expand 3-digit hex to 6-digit.
			$color = '#' . str_repeat( $color[1], 2 ) . str_repeat( $color[2], 2 ) . str_repeat( $color[3], 2 );
		}
		return preg_match( '/^#[0-9a-fA-F]{6}$/', $color ) ? strtolower( $color ) : self::DEFAULT_PRIMARY_COLOR;
	}

	/**
	 * Substitute placeholders in $template. Every value is escaped per its
	 * surrounding context: URLs via esc_url, the color via esc_attr (always
	 * safe — already validated hex), text via esc_html.
	 *
	 * @param array<string,string> $context Substitution map.
	 */
	public static function substitute( string $template, array $context ): string {
		$esc_url  = function ( $v ) {
			return function_exists( 'esc_url' ) ? \esc_url( (string) $v ) : (string) $v;
		};
		$esc_attr = function ( $v ) {
			return function_exists( 'esc_attr' ) ? \esc_attr( (string) $v ) : (string) $v;
		};
		$esc_html = function ( $v ) {
			return function_exists( 'esc_html' ) ? \esc_html( (string) $v ) : (string) $v;
		};

		$replacements = array(
			'{{magic_link}}'     => $esc_url( $context['magic_link'] ?? '' ),
			'{{login_url}}'      => $esc_url( $context['login_url'] ?? ( $context['magic_link'] ?? '' ) ),
			'{{customer_email}}' => $esc_html( $context['customer_email'] ?? '' ),
			'{{site_name}}'      => $esc_html( $context['site_name'] ?? '' ),
			'{{logo_url}}'       => $esc_url( $context['logo_url'] ?? '' ),
			'{{primary_color}}'  => $esc_attr( self::normalize_color( (string) ( $context['primary_color'] ?? self::DEFAULT_PRIMARY_COLOR ) ) ),
			'{{heading}}'        => $esc_html( $context['heading'] ?? self::DEFAULT_HEADING ),
			'{{cta_text}}'       => $esc_html( $context['cta_text'] ?? self::DEFAULT_CTA_TEXT ),
			'{{footer_text}}'    => $esc_html( $context['footer_text'] ?? self::DEFAULT_FOOTER_TEXT ),
		);

		return strtr( $template, $replacements );
	}

	/**
	 * Resolve the template directory. Constant LSCP_PLUGIN_DIR is set by
	 * the bootstrap file; we accept an override path for tests.
	 */
	public static function template_dir(): string {
		$base = defined( 'LSCP_PRO_TEMPLATES_DIR' )
			? (string) constant( 'LSCP_PRO_TEMPLATES_DIR' )
			: ( defined( 'LSCP_PLUGIN_DIR' ) ? (string) constant( 'LSCP_PLUGIN_DIR' ) . 'assets/pro/email-templates/' : __DIR__ . '/../../assets/pro/email-templates/' );
		return rtrim( $base, '/' ) . '/';
	}
}
