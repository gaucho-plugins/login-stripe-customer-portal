<?php
/**
 * Builds GitBook documentation URLs for the settings page.
 *
 * @package LSCP
 */

declare( strict_types = 1 );

namespace LSCP;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class DocsHelper {

	public const BASE = 'https://gauchoplugins.gitbook.io/login-for-stripe-customer-portal-wordpress-plugin';

	public static function url( string $path = '' ): string {
		$path = ltrim( $path, '/' );
		return '' === $path ? self::BASE : self::BASE . '/' . $path;
	}

	public static function link( string $path, string $text ): string {
		$href = function_exists( 'esc_url' ) ? \esc_url( self::url( $path ) ) : self::url( $path );
		$txt  = function_exists( 'esc_html' ) ? \esc_html( $text ) : htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
		return sprintf( '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>', $href, $txt );
	}
}
