<?php
/**
 * Registers the 9 LSCP PRO email-templates options + renders the Email
 * Templates settings tab. Hooks `lscp_settings_tab_email-templates_content`
 * to replace the FREE upgrade-CTA stub with the real settings form.
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

final class EmailTemplateSettings {

	public const GROUP = 'lscp_pro_email_templates_group';

	public static function register(): void {
		\add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		\add_filter( 'lscp_settings_tab_' . Settings::TAB_EMAIL . '_content', array( __CLASS__, 'render_tab' ), 10, 2 );
	}

	public static function register_settings(): void {
		\register_setting(
			self::GROUP,
			EmailTemplates::OPTION_TEMPLATE,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_template' ),
				'default'           => EmailRenderer::DEFAULT_TEMPLATE,
			)
		);
		\register_setting(
			self::GROUP,
			EmailTemplates::OPTION_LOGO_URL,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( Sanitizer::class, 'sanitize_logo_url' ),
				'default'           => '',
			)
		);
		\register_setting(
			self::GROUP,
			EmailTemplates::OPTION_PRIMARY_COLOR,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( Sanitizer::class, 'sanitize_hex_color' ),
				'default'           => EmailRenderer::DEFAULT_PRIMARY_COLOR,
			)
		);
		\register_setting(
			self::GROUP,
			EmailTemplates::OPTION_SUBJECT,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_subject' ),
				'default'           => '',
			)
		);
		\register_setting(
			self::GROUP,
			EmailTemplates::OPTION_HEADING,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_short_text' ),
				'default'           => '',
			)
		);
		\register_setting(
			self::GROUP,
			EmailTemplates::OPTION_CTA_TEXT,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_cta' ),
				'default'           => '',
			)
		);
		\register_setting(
			self::GROUP,
			EmailTemplates::OPTION_FOOTER_TEXT,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( Sanitizer::class, 'sanitize_multi_line_text' ),
				'default'           => '',
			)
		);
		\register_setting(
			self::GROUP,
			EmailTemplates::OPTION_FROM_NAME,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( Sanitizer::class, 'sanitize_from_name' ),
				'default'           => '',
			)
		);
		\register_setting(
			self::GROUP,
			EmailTemplates::OPTION_FROM_EMAIL,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( Sanitizer::class, 'sanitize_from_email' ),
				'default'           => '',
			)
		);
	}

	public static function sanitize_template( $input ): string {
		return Sanitizer::sanitize_whitelisted_slug(
			$input,
			EmailRenderer::templates(),
			EmailRenderer::DEFAULT_TEMPLATE
		);
	}

	public static function sanitize_subject( $input ): string {
		return Sanitizer::sanitize_one_line_text( $input, 200 );
	}

	public static function sanitize_short_text( $input ): string {
		return Sanitizer::sanitize_one_line_text( $input, 120 );
	}

	public static function sanitize_cta( $input ): string {
		return Sanitizer::sanitize_one_line_text( $input, 40 );
	}

	/**
	 * Capture the tab HTML so the Settings::render_tab filter can echo it
	 * via the filter return value.
	 *
	 * @param string $existing Existing tab html (empty in the default case).
	 * @param string $tab      Tab slug.
	 */
	public static function render_tab( $existing, $tab ): string {
		if ( '' !== (string) $existing ) {
			return (string) $existing;
		}
		if ( Settings::TAB_EMAIL !== (string) $tab ) {
			return (string) $existing;
		}

		ob_start();
		self::render_form();
		return (string) ob_get_clean();
	}

	private static function render_form(): void {
		$current_template = (string) \get_option( EmailTemplates::OPTION_TEMPLATE, EmailRenderer::DEFAULT_TEMPLATE );
		$logo_url         = (string) \get_option( EmailTemplates::OPTION_LOGO_URL, '' );
		$primary_color    = (string) \get_option( EmailTemplates::OPTION_PRIMARY_COLOR, EmailRenderer::DEFAULT_PRIMARY_COLOR );
		$subject          = (string) \get_option( EmailTemplates::OPTION_SUBJECT, '' );
		$heading          = (string) \get_option( EmailTemplates::OPTION_HEADING, '' );
		$cta_text         = (string) \get_option( EmailTemplates::OPTION_CTA_TEXT, '' );
		$footer_text      = (string) \get_option( EmailTemplates::OPTION_FOOTER_TEXT, '' );
		$from_name        = (string) \get_option( EmailTemplates::OPTION_FROM_NAME, '' );
		$from_email       = (string) \get_option( EmailTemplates::OPTION_FROM_EMAIL, '' );

		?>
		<h2><?php \esc_html_e( 'Branded magic-link emails', 'login-stripe-customer-portal' ); ?></h2>
		<p class="description"><?php \esc_html_e( 'Pick a template, set your brand color, and (optionally) upload a logo URL. The chosen template is rendered for every magic-link email sent to your customers.', 'login-stripe-customer-portal' ); ?></p>

		<form method="post" action="options.php">
			<?php \settings_fields( self::GROUP ); ?>
			<table class="form-table" role="presentation">

				<tr>
					<th scope="row"><label for="lscp_pro_email_template"><?php \esc_html_e( 'Template', 'login-stripe-customer-portal' ); ?></label></th>
					<td>
						<select id="lscp_pro_email_template" name="<?php echo \esc_attr( EmailTemplates::OPTION_TEMPLATE ); ?>">
							<?php foreach ( EmailRenderer::templates() as $slug => $label ) : ?>
								<option value="<?php echo \esc_attr( $slug ); ?>" <?php \selected( $current_template, $slug ); ?>><?php echo \esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php \esc_html_e( 'Six pre-built templates included. Switch any time — your other settings are preserved.', 'login-stripe-customer-portal' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="lscp_pro_email_primary_color"><?php \esc_html_e( 'Brand color', 'login-stripe-customer-portal' ); ?></label></th>
					<td>
						<input id="lscp_pro_email_primary_color" type="text" name="<?php echo \esc_attr( EmailTemplates::OPTION_PRIMARY_COLOR ); ?>" value="<?php echo \esc_attr( $primary_color ); ?>" placeholder="<?php echo \esc_attr( EmailRenderer::DEFAULT_PRIMARY_COLOR ); ?>" class="regular-text" />
						<p class="description"><?php \esc_html_e( 'Hex color (e.g., #2271b1). Used for the CTA button and template accents.', 'login-stripe-customer-portal' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="lscp_pro_email_logo_url"><?php \esc_html_e( 'Logo URL', 'login-stripe-customer-portal' ); ?></label></th>
					<td>
						<input id="lscp_pro_email_logo_url" type="url" name="<?php echo \esc_attr( EmailTemplates::OPTION_LOGO_URL ); ?>" value="<?php echo \esc_attr( $logo_url ); ?>" placeholder="https://example.com/logo.png" class="regular-text" />
						<p class="description"><?php \esc_html_e( 'Optional. Used by the "Card with logo" template; upload the image to your Media Library and paste the URL here.', 'login-stripe-customer-portal' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="lscp_pro_email_subject"><?php \esc_html_e( 'Custom subject', 'login-stripe-customer-portal' ); ?></label></th>
					<td>
						<input id="lscp_pro_email_subject" type="text" name="<?php echo \esc_attr( EmailTemplates::OPTION_SUBJECT ); ?>" value="<?php echo \esc_attr( $subject ); ?>" placeholder="<?php \esc_attr_e( 'Login to Stripe Customer Portal', 'login-stripe-customer-portal' ); ?>" class="regular-text" />
						<p class="description"><?php \esc_html_e( 'Leave blank to use the default subject.', 'login-stripe-customer-portal' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="lscp_pro_email_heading"><?php \esc_html_e( 'Heading', 'login-stripe-customer-portal' ); ?></label></th>
					<td>
						<input id="lscp_pro_email_heading" type="text" name="<?php echo \esc_attr( EmailTemplates::OPTION_HEADING ); ?>" value="<?php echo \esc_attr( $heading ); ?>" placeholder="<?php echo \esc_attr( EmailRenderer::DEFAULT_HEADING ); ?>" class="regular-text" />
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="lscp_pro_email_cta_text"><?php \esc_html_e( 'CTA button text', 'login-stripe-customer-portal' ); ?></label></th>
					<td>
						<input id="lscp_pro_email_cta_text" type="text" name="<?php echo \esc_attr( EmailTemplates::OPTION_CTA_TEXT ); ?>" value="<?php echo \esc_attr( $cta_text ); ?>" placeholder="<?php echo \esc_attr( EmailRenderer::DEFAULT_CTA_TEXT ); ?>" class="regular-text" />
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="lscp_pro_email_footer_text"><?php \esc_html_e( 'Footer text', 'login-stripe-customer-portal' ); ?></label></th>
					<td>
						<textarea id="lscp_pro_email_footer_text" name="<?php echo \esc_attr( EmailTemplates::OPTION_FOOTER_TEXT ); ?>" rows="3" class="large-text"><?php echo \esc_textarea( $footer_text ); ?></textarea>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="lscp_pro_email_from_name"><?php \esc_html_e( 'From name', 'login-stripe-customer-portal' ); ?></label></th>
					<td>
						<input id="lscp_pro_email_from_name" type="text" name="<?php echo \esc_attr( EmailTemplates::OPTION_FROM_NAME ); ?>" value="<?php echo \esc_attr( $from_name ); ?>" placeholder="<?php \esc_attr_e( 'Your Site Name', 'login-stripe-customer-portal' ); ?>" class="regular-text" />
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="lscp_pro_email_from_email"><?php \esc_html_e( 'From email', 'login-stripe-customer-portal' ); ?></label></th>
					<td>
						<input id="lscp_pro_email_from_email" type="email" name="<?php echo \esc_attr( EmailTemplates::OPTION_FROM_EMAIL ); ?>" value="<?php echo \esc_attr( $from_email ); ?>" placeholder="noreply@example.com" class="regular-text" />
						<p class="description"><?php \esc_html_e( 'Leave blank to use the WordPress default from address.', 'login-stripe-customer-portal' ); ?></p>
					</td>
				</tr>

			</table>
			<?php \submit_button(); ?>
		</form>
		<?php
	}
}
