<?php
/**
 * Settings registration + Form Style tab render for the LSCP PRO form-styler.
 * Hooks `lscp_settings_tab_form-style_content` so the FREE upgrade-CTA stub
 * is replaced with the real form when premium is active.
 *
 * @package LSCP\Pro
 *
 * @fs_premium_only
 */

declare( strict_types = 1 );

namespace LSCP\Pro;

use LSCP\Sanitizer;
use LSCP\Settings;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class FormStylerSettings {

	public const GROUP = 'lscp_pro_form_styler_group';

	public static function register(): void {
		\add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		\add_filter( 'lscp_settings_tab_' . Settings::TAB_FORM . '_content', array( __CLASS__, 'render_tab' ), 10, 2 );
	}

	public static function register_settings(): void {
		\register_setting(
			self::GROUP,
			FormStyler::OPTION_TEMPLATE,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_template' ),
				'default'           => FormTemplateRenderer::DEFAULT_TEMPLATE,
			)
		);
		\register_setting(
			self::GROUP,
			FormStyler::OPTION_PRIMARY_COLOR,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( Sanitizer::class, 'sanitize_hex_color' ),
				'default'           => FormTemplateRenderer::DEFAULT_PRIMARY_COLOR,
			)
		);
		\register_setting(
			self::GROUP,
			FormStyler::OPTION_HEADING,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_short_text' ),
				'default'           => '',
			)
		);
		\register_setting(
			self::GROUP,
			FormStyler::OPTION_SUBHEADING,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_subheading' ),
				'default'           => '',
			)
		);
		\register_setting(
			self::GROUP,
			FormStyler::OPTION_BUTTON_TEXT,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_button_text' ),
				'default'           => '',
			)
		);
		\register_setting(
			self::GROUP,
			FormStyler::OPTION_PLACEHOLDER,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_short_text' ),
				'default'           => '',
			)
		);
	}

	public static function sanitize_template( $input ): string {
		return Sanitizer::sanitize_whitelisted_slug(
			$input,
			FormTemplateRenderer::templates(),
			FormTemplateRenderer::DEFAULT_TEMPLATE
		);
	}

	public static function sanitize_short_text( $input ): string {
		return Sanitizer::sanitize_one_line_text( $input, 120 );
	}

	public static function sanitize_subheading( $input ): string {
		return Sanitizer::sanitize_one_line_text( $input, 240 );
	}

	public static function sanitize_button_text( $input ): string {
		return Sanitizer::sanitize_one_line_text( $input, 40 );
	}

	/**
	 * Hooked to lscp_settings_tab_form-style_content.
	 *
	 * @param string $existing
	 * @param string $tab
	 */
	public static function render_tab( $existing, $tab ): string {
		if ( '' !== (string) $existing ) {
			return (string) $existing;
		}
		if ( Settings::TAB_FORM !== (string) $tab ) {
			return (string) $existing;
		}
		ob_start();
		self::render_form();
		return (string) ob_get_clean();
	}

	private static function render_form(): void {
		$template      = (string) \get_option( FormStyler::OPTION_TEMPLATE, FormTemplateRenderer::DEFAULT_TEMPLATE );
		$primary_color = (string) \get_option( FormStyler::OPTION_PRIMARY_COLOR, FormTemplateRenderer::DEFAULT_PRIMARY_COLOR );
		$heading       = (string) \get_option( FormStyler::OPTION_HEADING, '' );
		$subheading    = (string) \get_option( FormStyler::OPTION_SUBHEADING, '' );
		$button_text   = (string) \get_option( FormStyler::OPTION_BUTTON_TEXT, '' );
		$placeholder   = (string) \get_option( FormStyler::OPTION_PLACEHOLDER, '' );

		?>
		<h2><?php \esc_html_e( 'Login form styler', 'login-stripe-customer-portal' ); ?></h2>
		<p class="description"><?php \esc_html_e( 'Pick a template, set your brand color, and customize the heading / subheading / button label. The chosen styling is applied to the rewrite-endpoint page and every [login-stripe-customer-portal] shortcode render.', 'login-stripe-customer-portal' ); ?></p>

		<form method="post" action="options.php">
			<?php \settings_fields( self::GROUP ); ?>
			<table class="form-table" role="presentation">

				<tr>
					<th scope="row"><label for="lscp_pro_form_template"><?php \esc_html_e( 'Template', 'login-stripe-customer-portal' ); ?></label></th>
					<td>
						<select id="lscp_pro_form_template" name="<?php echo \esc_attr( FormStyler::OPTION_TEMPLATE ); ?>">
							<?php foreach ( FormTemplateRenderer::templates() as $slug => $label ) : ?>
								<option value="<?php echo \esc_attr( $slug ); ?>" <?php \selected( $template, $slug ); ?>><?php echo \esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php \esc_html_e( 'Six pre-built form templates included. Your other settings are preserved when you switch.', 'login-stripe-customer-portal' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="lscp_pro_form_primary_color"><?php \esc_html_e( 'Brand color', 'login-stripe-customer-portal' ); ?></label></th>
					<td>
						<input id="lscp_pro_form_primary_color" type="text" name="<?php echo \esc_attr( FormStyler::OPTION_PRIMARY_COLOR ); ?>" value="<?php echo \esc_attr( $primary_color ); ?>" placeholder="<?php echo \esc_attr( FormTemplateRenderer::DEFAULT_PRIMARY_COLOR ); ?>" class="regular-text" />
						<p class="description"><?php \esc_html_e( 'Hex color (e.g., #0073aa). Used for the CTA button and template accents.', 'login-stripe-customer-portal' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="lscp_pro_form_heading"><?php \esc_html_e( 'Heading', 'login-stripe-customer-portal' ); ?></label></th>
					<td>
						<input id="lscp_pro_form_heading" type="text" name="<?php echo \esc_attr( FormStyler::OPTION_HEADING ); ?>" value="<?php echo \esc_attr( $heading ); ?>" placeholder="<?php echo \esc_attr( FormTemplateRenderer::DEFAULT_HEADING ); ?>" class="regular-text" />
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="lscp_pro_form_subheading"><?php \esc_html_e( 'Subheading', 'login-stripe-customer-portal' ); ?></label></th>
					<td>
						<input id="lscp_pro_form_subheading" type="text" name="<?php echo \esc_attr( FormStyler::OPTION_SUBHEADING ); ?>" value="<?php echo \esc_attr( $subheading ); ?>" placeholder="<?php echo \esc_attr( FormTemplateRenderer::DEFAULT_SUBHEADING ); ?>" class="regular-text" />
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="lscp_pro_form_button_text"><?php \esc_html_e( 'Button text', 'login-stripe-customer-portal' ); ?></label></th>
					<td>
						<input id="lscp_pro_form_button_text" type="text" name="<?php echo \esc_attr( FormStyler::OPTION_BUTTON_TEXT ); ?>" value="<?php echo \esc_attr( $button_text ); ?>" placeholder="<?php echo \esc_attr( FormTemplateRenderer::DEFAULT_BUTTON_TEXT ); ?>" class="regular-text" />
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="lscp_pro_form_placeholder"><?php \esc_html_e( 'Email placeholder', 'login-stripe-customer-portal' ); ?></label></th>
					<td>
						<input id="lscp_pro_form_placeholder" type="text" name="<?php echo \esc_attr( FormStyler::OPTION_PLACEHOLDER ); ?>" value="<?php echo \esc_attr( $placeholder ); ?>" placeholder="<?php echo \esc_attr( FormTemplateRenderer::DEFAULT_PLACEHOLDER ); ?>" class="regular-text" />
					</td>
				</tr>

			</table>
			<?php \submit_button(); ?>
		</form>
		<?php
	}
}
