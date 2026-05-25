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

		$preview_base = function_exists( '\rest_url' )
			? \rest_url( FormStylePreview::REST_NAMESPACE . FormStylePreview::REST_ROUTE )
			: '/wp-json/' . FormStylePreview::REST_NAMESPACE . FormStylePreview::REST_ROUTE;
		?>
		<h2><?php \esc_html_e( 'Login form styler', 'login-stripe-customer-portal' ); ?></h2>
		<p class="description"><?php \esc_html_e( 'Pick a template, set your brand color, and customize the heading / subheading / button label. The chosen styling is applied to the rewrite-endpoint page and every [login-stripe-customer-portal] shortcode render.', 'login-stripe-customer-portal' ); ?></p>

		<div class="lscp-pro-preview-grid" style="display:grid;grid-template-columns:minmax(0,1fr) minmax(280px,420px);gap:24px;align-items:start;margin-top:16px;">
			<div>
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
			</div>
			<aside class="lscp-pro-preview-pane" style="position:sticky;top:48px;">
				<h3 style="margin:0 0 8px;font-size:13px;text-transform:uppercase;letter-spacing:0.5px;color:#646970;"><?php \esc_html_e( 'Live preview', 'login-stripe-customer-portal' ); ?></h3>
				<p class="description" style="margin:0 0 8px;"><?php \esc_html_e( 'Updates as you type. This is what visitors see on the customer-portal page and every shortcode embed.', 'login-stripe-customer-portal' ); ?></p>
				<iframe
					id="lscp-pro-form-preview"
					title="<?php \esc_attr_e( 'Form preview', 'login-stripe-customer-portal' ); ?>"
					sandbox="allow-same-origin"
					style="width:100%;height:560px;border:1px solid #c3c4c7;border-radius:4px;background:#fff;"
					src="<?php echo \esc_url( $preview_base ); ?>"></iframe>
			</aside>
		</div>

		<script>
		(function(){
			var iframe = document.getElementById('lscp-pro-form-preview');
			if (!iframe) return;
			var base = <?php echo \wp_json_encode( $preview_base ); ?>;
			var fieldMap = {
				'<?php echo \esc_js( FormStyler::OPTION_TEMPLATE ); ?>'      : 'template',
				'<?php echo \esc_js( FormStyler::OPTION_PRIMARY_COLOR ); ?>' : 'primary_color',
				'<?php echo \esc_js( FormStyler::OPTION_HEADING ); ?>'       : 'heading',
				'<?php echo \esc_js( FormStyler::OPTION_SUBHEADING ); ?>'    : 'subheading',
				'<?php echo \esc_js( FormStyler::OPTION_BUTTON_TEXT ); ?>'   : 'button_text',
				'<?php echo \esc_js( FormStyler::OPTION_PLACEHOLDER ); ?>'   : 'placeholder'
			};
			function currentParams() {
				var params = new URLSearchParams();
				Object.keys(fieldMap).forEach(function(optName){
					var el = document.querySelector('[name="' + optName + '"]');
					if (el && el.value !== '') params.set(fieldMap[optName], el.value);
				});
				return params.toString();
			}
			function updatePreview() {
				var qs = currentParams();
				iframe.src = base + (qs ? ('?' + qs) : '');
			}
			var debounce;
			document.querySelectorAll('input,select,textarea').forEach(function(el){
				el.addEventListener('input',  function(){ clearTimeout(debounce); debounce = setTimeout(updatePreview, 250); });
				el.addEventListener('change', updatePreview);
			});
			updatePreview();
		})();
		</script>
		<?php
	}
}
