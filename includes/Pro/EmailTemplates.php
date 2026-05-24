<?php
/**
 * Wires the LSCP PRO email-templates feature into the FREE plugin's
 * extension surface. Reads the saved brand options and substitutes them
 * into the chosen template at send time, replacing the plain-HTML body
 * the FREE Mailer ships with.
 *
 * Hooks bound here:
 *   filter `lscp_email_subject`     — branded subject override.
 *   filter `lscp_email_html_body`   — rendered branded body.
 *   filter `lscp_email_headers`     — adds From: when configured.
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

final class EmailTemplates {

	public const OPTION_TEMPLATE      = 'lscp_pro_email_template';
	public const OPTION_LOGO_URL      = 'lscp_pro_email_logo_url';
	public const OPTION_PRIMARY_COLOR = 'lscp_pro_email_primary_color';
	public const OPTION_SUBJECT       = 'lscp_pro_email_subject';
	public const OPTION_HEADING       = 'lscp_pro_email_heading';
	public const OPTION_CTA_TEXT      = 'lscp_pro_email_cta_text';
	public const OPTION_FOOTER_TEXT   = 'lscp_pro_email_footer_text';
	public const OPTION_FROM_NAME     = 'lscp_pro_email_from_name';
	public const OPTION_FROM_EMAIL    = 'lscp_pro_email_from_email';

	/**
	 * Register the WP filter handlers. Idempotent — calling it twice will
	 * register the same filters twice (caller's job to avoid that; the
	 * Loader does).
	 */
	public static function register(): void {
		\add_filter( 'lscp_email_subject', array( __CLASS__, 'filter_subject' ), 10, 2 );
		\add_filter( 'lscp_email_html_body', array( __CLASS__, 'filter_body' ), 10, 3 );
		\add_filter( 'lscp_email_headers', array( __CLASS__, 'filter_headers' ), 10, 2 );
	}

	/**
	 * Read the branding option set as an associative array. Pure read — no
	 * side effects.
	 *
	 * @return array<string,string>
	 */
	public static function branding(): array {
		return array(
			'template'      => (string) \get_option( self::OPTION_TEMPLATE, EmailRenderer::DEFAULT_TEMPLATE ),
			'logo_url'      => (string) \get_option( self::OPTION_LOGO_URL, '' ),
			'primary_color' => (string) \get_option( self::OPTION_PRIMARY_COLOR, EmailRenderer::DEFAULT_PRIMARY_COLOR ),
			'subject'       => (string) \get_option( self::OPTION_SUBJECT, '' ),
			'heading'       => (string) \get_option( self::OPTION_HEADING, '' ),
			'cta_text'      => (string) \get_option( self::OPTION_CTA_TEXT, '' ),
			'footer_text'   => (string) \get_option( self::OPTION_FOOTER_TEXT, '' ),
			'from_name'     => (string) \get_option( self::OPTION_FROM_NAME, '' ),
			'from_email'    => (string) \get_option( self::OPTION_FROM_EMAIL, '' ),
		);
	}

	/**
	 * @param string               $subject Default subject built by Mailer.
	 * @param array<string,string> $context [email, login_url, site_name].
	 */
	public static function filter_subject( $subject, $context ): string {
		$branding = self::branding();
		$override = $branding['subject'];
		return '' !== $override ? $override : (string) $subject;
	}

	/**
	 * @param string               $body      Default body from Mailer.
	 * @param string               $login_url Magic-link URL.
	 * @param array<string,string> $context   [email, login_url, site_name].
	 */
	public static function filter_body( $body, $login_url, $context ): string {
		$branding = self::branding();
		$context  = is_array( $context ) ? $context : array();

		$request = array(
			'email'     => (string) ( $context['email'] ?? '' ),
			'login_url' => (string) $login_url,
			'site_name' => (string) ( $context['site_name'] ?? '' ),
		);

		$ctx = EmailRenderer::build_context( $request, $branding );
		return EmailRenderer::render( $branding['template'], $ctx );
	}

	/**
	 * Append `From:` header when admin has configured a From email +
	 * optional name. Preserves any other headers the FREE Mailer or
	 * earlier filters added.
	 *
	 * @param array<int,string>    $headers
	 * @param array<string,string> $context
	 */
	public static function filter_headers( $headers, $context ): array {
		$headers  = is_array( $headers ) ? $headers : array();
		$branding = self::branding();
		$email    = $branding['from_email'];

		if ( '' === $email ) {
			return $headers;
		}

		$name = $branding['from_name'];
		$from = '' !== $name ? sprintf( '%s <%s>', $name, $email ) : $email;

		// Remove any existing From: header to avoid duplicates.
		$out = array();
		foreach ( $headers as $h ) {
			if ( 0 !== stripos( (string) $h, 'From:' ) ) {
				$out[] = $h;
			}
		}
		$out[] = 'From: ' . $from;
		return $out;
	}
}
