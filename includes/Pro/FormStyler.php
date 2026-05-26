<?php
/**
 * Wires the PRO form-styler into the FREE plugin's `lscp_form_template`
 * filter. When premium is active and an admin has configured branding,
 * every shortcode / rewrite-endpoint render produces the chosen styled
 * template instead of the FREE default markup.
 *
 * The FREE PortalController still receives the same POST: name="email",
 * the nonce hidden field built by wp_nonce_field(NONCE_ACTION, NONCE_NAME),
 * and a submit input. PRO templates wrap branding around those required
 * fields — they don't replace them.
 *
 * @package LSCP\Pro
 *
 * @fs_premium_only
 */

declare( strict_types = 1 );

namespace LSCP\Pro;

use LSCP\FormRenderer;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class FormStyler {

	public const OPTION_TEMPLATE      = 'lscp_pro_form_template';
	public const OPTION_PRIMARY_COLOR = 'lscp_pro_form_primary_color';
	public const OPTION_HEADING       = 'lscp_pro_form_heading';
	public const OPTION_SUBHEADING    = 'lscp_pro_form_subheading';
	public const OPTION_BUTTON_TEXT   = 'lscp_pro_form_button_text';
	public const OPTION_PLACEHOLDER   = 'lscp_pro_form_placeholder';

	public static function register(): void {
		\add_filter( 'lscp_form_template', array( __CLASS__, 'filter_template' ), 10, 2 );
	}

	/**
	 * @return array<string,string>
	 */
	public static function branding(): array {
		return array(
			'template'      => (string) \get_option( self::OPTION_TEMPLATE, FormTemplateRenderer::DEFAULT_TEMPLATE ),
			'primary_color' => (string) \get_option( self::OPTION_PRIMARY_COLOR, FormTemplateRenderer::DEFAULT_PRIMARY_COLOR ),
			'heading'       => (string) \get_option( self::OPTION_HEADING, '' ),
			'subheading'    => (string) \get_option( self::OPTION_SUBHEADING, '' ),
			'button_text'   => (string) \get_option( self::OPTION_BUTTON_TEXT, '' ),
			'placeholder'   => (string) \get_option( self::OPTION_PLACEHOLDER, '' ),
		);
	}

	/**
	 * @param string $existing  Empty by default; non-empty short-circuits.
	 * @param array  $args      FormRenderer::render() args.
	 */
	public static function filter_template( $existing, $args = array() ): string {
		if ( '' !== (string) $existing ) {
			return (string) $existing;
		}

		$args = (array) $args;

		$nonce_field = '';
		if ( function_exists( 'wp_nonce_field' ) ) {
			$nonce_field = (string) \wp_nonce_field(
				FormRenderer::NONCE_ACTION,
				FormRenderer::NONCE_NAME,
				true,  // include the referer field
				false  // return rather than echo
			);
		}

		$default_email = '';
		if ( function_exists( 'apply_filters' ) ) {
			$default_email = (string) \apply_filters( 'lscp_form_default_email', '', $args );
		}

		// FormRenderer already computes a per-render unique id internally for
		// the default template; for PRO we generate our own with the same prefix
		// so multiple shortcode instances on one page still produce unique ids.
		$form_id = function_exists( 'wp_unique_id' )
			? (string) \wp_unique_id( 'lscp-email-' )
			: 'lscp-email-' . uniqid();

		$branding = self::branding();
		$ctx      = FormTemplateRenderer::build_context(
			array(
				'form_id'       => $form_id,
				'default_email' => $default_email,
				'nonce_field'   => $nonce_field,
			),
			$branding
		);

		return FormTemplateRenderer::render( $branding['template'], $ctx );
	}
}
