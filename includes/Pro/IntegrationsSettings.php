<?php
/**
 * Settings registration + Integrations tab render for the LSCP PRO user
 * bridge + WC/MP/LD adapters.
 *
 * @package LSCP\Pro
 *
 * @fs_premium_only
 */

declare( strict_types = 1 );

namespace LSCP\Pro;

use LSCP\Pro\Integrations\IntegrationContext;
use LSCP\Sanitizer;
use LSCP\Settings;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class IntegrationsSettings {

	public const GROUP = 'lscp_pro_integrations_group';

	public static function register(): void {
		\add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		\add_filter( 'lscp_settings_tab_' . Settings::TAB_INTEGRATIONS . '_content', array( __CLASS__, 'render_tab' ), 10, 2 );
	}

	public static function register_settings(): void {
		\register_setting(
			self::GROUP,
			UserBridge::OPTION_PREFILL_EMAIL,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( Sanitizer::class, 'sanitize_checkbox' ),
				'default'           => '1',
			)
		);
		\register_setting(
			self::GROUP,
			UserBridge::OPTION_AUTO_CREATE_USER,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( Sanitizer::class, 'sanitize_checkbox' ),
				'default'           => '0',
			)
		);
		\register_setting(
			self::GROUP,
			UserBridge::OPTION_DEFAULT_ROLE,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_role' ),
				'default'           => 'subscriber',
			)
		);
		\register_setting(
			self::GROUP,
			IntegrationContext::OPTION_WOOCOMMERCE,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( Sanitizer::class, 'sanitize_checkbox' ),
				'default'           => '0',
			)
		);
		\register_setting(
			self::GROUP,
			IntegrationContext::OPTION_MEMBERPRESS,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( Sanitizer::class, 'sanitize_checkbox' ),
				'default'           => '0',
			)
		);
		\register_setting(
			self::GROUP,
			IntegrationContext::OPTION_LEARNDASH,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( Sanitizer::class, 'sanitize_checkbox' ),
				'default'           => '0',
			)
		);
		\register_setting(
			self::GROUP,
			IntegrationContext::OPTION_BUTTON_LABEL,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_button_label' ),
				'default'           => IntegrationContext::DEFAULT_BUTTON_LABEL,
			)
		);
	}

	public static function sanitize_role( $input ): string {
		$role = is_string( $input ) ? strtolower( trim( $input ) ) : '';
		if ( ! preg_match( '/^[a-z0-9_-]{1,40}$/', $role ) ) {
			return 'subscriber';
		}
		return $role;
	}

	public static function sanitize_button_label( $input ): string {
		$value = Sanitizer::sanitize_one_line_text( $input, 60 );
		return '' === $value ? IntegrationContext::DEFAULT_BUTTON_LABEL : $value;
	}

	public static function render_tab( $existing, $tab ): string {
		if ( '' !== (string) $existing ) {
			return (string) $existing;
		}
		if ( Settings::TAB_INTEGRATIONS !== (string) $tab ) {
			return (string) $existing;
		}
		ob_start();
		self::render_form();
		return (string) ob_get_clean();
	}

	private static function render_form(): void {
		$prefill        = (string) \get_option( UserBridge::OPTION_PREFILL_EMAIL, '1' );
		$auto_create    = (string) \get_option( UserBridge::OPTION_AUTO_CREATE_USER, '0' );
		$default_role   = (string) \get_option( UserBridge::OPTION_DEFAULT_ROLE, 'subscriber' );
		$wc_enabled     = (string) \get_option( IntegrationContext::OPTION_WOOCOMMERCE, '0' );
		$mp_enabled     = (string) \get_option( IntegrationContext::OPTION_MEMBERPRESS, '0' );
		$ld_enabled     = (string) \get_option( IntegrationContext::OPTION_LEARNDASH, '0' );
		$button_label   = (string) \get_option( IntegrationContext::OPTION_BUTTON_LABEL, IntegrationContext::DEFAULT_BUTTON_LABEL );
		$detected       = IntegrationContext::detected();

		?>
		<h2><?php \esc_html_e( 'Integrations + WP user bridge', 'login-stripe-customer-portal' ); ?></h2>
		<p class="description"><?php \esc_html_e( 'Link WP users to Stripe customers and add a Manage Billing button to your membership / e-commerce account pages.', 'login-stripe-customer-portal' ); ?></p>

		<form method="post" action="options.php">
			<?php \settings_fields( self::GROUP ); ?>

			<h3><?php \esc_html_e( 'WP user bridge', 'login-stripe-customer-portal' ); ?></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php \esc_html_e( 'Pre-fill email', 'login-stripe-customer-portal' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo \esc_attr( UserBridge::OPTION_PREFILL_EMAIL ); ?>" value="1" <?php \checked( '1', $prefill ); ?> />
							<?php \esc_html_e( 'When a logged-in WordPress user views the magic-link form, fill the email input with their address.', 'login-stripe-customer-portal' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php \esc_html_e( 'Auto-create WP user on redeem', 'login-stripe-customer-portal' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo \esc_attr( UserBridge::OPTION_AUTO_CREATE_USER ); ?>" value="1" <?php \checked( '1', $auto_create ); ?> />
							<?php \esc_html_e( 'When a magic-link is redeemed for an email that doesn\'t match an existing WP user, create one and link them to the Stripe customer.', 'login-stripe-customer-portal' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lscp_pro_bridge_default_role"><?php \esc_html_e( 'Default role for auto-created users', 'login-stripe-customer-portal' ); ?></label></th>
					<td>
						<input id="lscp_pro_bridge_default_role" type="text" name="<?php echo \esc_attr( UserBridge::OPTION_DEFAULT_ROLE ); ?>" value="<?php echo \esc_attr( $default_role ); ?>" placeholder="subscriber" class="regular-text" />
					</td>
				</tr>
			</table>

			<h3><?php \esc_html_e( 'Account-page integrations', 'login-stripe-customer-portal' ); ?></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php \esc_html_e( 'WooCommerce', 'login-stripe-customer-portal' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo \esc_attr( IntegrationContext::OPTION_WOOCOMMERCE ); ?>" value="1" <?php \checked( '1', $wc_enabled ); ?> <?php \disabled( ! $detected['woocommerce'] ); ?> />
							<?php
							echo \esc_html(
								$detected['woocommerce']
									? \__( 'Detected — add a Manage Billing button to the WC My Account dashboard.', 'login-stripe-customer-portal' )
									: \__( 'Not detected — install / activate WooCommerce to enable.', 'login-stripe-customer-portal' )
							);
							?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php \esc_html_e( 'MemberPress', 'login-stripe-customer-portal' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo \esc_attr( IntegrationContext::OPTION_MEMBERPRESS ); ?>" value="1" <?php \checked( '1', $mp_enabled ); ?> <?php \disabled( ! $detected['memberpress'] ); ?> />
							<?php
							echo \esc_html(
								$detected['memberpress']
									? \__( 'Detected — add a Manage Billing button to the MemberPress account page.', 'login-stripe-customer-portal' )
									: \__( 'Not detected — install / activate MemberPress to enable.', 'login-stripe-customer-portal' )
							);
							?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php \esc_html_e( 'LearnDash', 'login-stripe-customer-portal' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo \esc_attr( IntegrationContext::OPTION_LEARNDASH ); ?>" value="1" <?php \checked( '1', $ld_enabled ); ?> <?php \disabled( ! $detected['learndash'] ); ?> />
							<?php
							echo \esc_html(
								$detected['learndash']
									? \__( 'Detected — add a Manage Billing button to the LearnDash profile page.', 'login-stripe-customer-portal' )
									: \__( 'Not detected — install / activate LearnDash to enable.', 'login-stripe-customer-portal' )
							);
							?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lscp_pro_integration_button_label"><?php \esc_html_e( 'Button label', 'login-stripe-customer-portal' ); ?></label></th>
					<td>
						<input id="lscp_pro_integration_button_label" type="text" name="<?php echo \esc_attr( IntegrationContext::OPTION_BUTTON_LABEL ); ?>" value="<?php echo \esc_attr( $button_label ); ?>" placeholder="<?php echo \esc_attr( IntegrationContext::DEFAULT_BUTTON_LABEL ); ?>" class="regular-text" />
					</td>
				</tr>
			</table>

			<?php \submit_button(); ?>
		</form>
		<?php
	}
}
