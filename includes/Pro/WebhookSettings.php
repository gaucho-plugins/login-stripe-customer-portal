<?php
/**
 * Settings registration + Webhooks tab render for the LSCP PRO webhook
 * listener.
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

final class WebhookSettings {

	public const GROUP = 'lscp_pro_webhook_group';

	public static function register(): void {
		\add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		\add_filter( 'lscp_settings_tab_' . Settings::TAB_WEBHOOKS . '_content', array( __CLASS__, 'render_tab' ), 10, 2 );
	}

	public static function register_settings(): void {
		\register_setting(
			self::GROUP,
			WebhookEndpoint::OPTION_SECRET,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_secret' ),
				'default'           => '',
			)
		);
		\register_setting(
			self::GROUP,
			WebhookRules::OPTION_ROLE_ON_CREATED,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_role' ),
				'default'           => '',
			)
		);
		\register_setting(
			self::GROUP,
			WebhookRules::OPTION_ROLE_ON_DELETED,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_role' ),
				'default'           => '',
			)
		);
		\register_setting(
			self::GROUP,
			WebhookRules::OPTION_ROLE_WHEN_PAST_DUE,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_role' ),
				'default'           => '',
			)
		);
		\register_setting(
			self::GROUP,
			WebhookRules::OPTION_EVENTS_ENABLED,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_events' ),
				'default'           => '',
			)
		);
	}

	public static function sanitize_secret( $input ): string {
		$current = (string) \get_option( WebhookEndpoint::OPTION_SECRET, '' );
		// Treat all-mask input as "no change" (mirrors API-key behavior).
		$value = is_string( $input ) ? trim( $input ) : '';
		if ( '' === $value ) {
			return $current;
		}
		if ( Sanitizer::is_pure_mask( $value ) ) {
			return $current;
		}
		// Stripe webhook secrets are `whsec_<base64-ish>`; trim to keep
		// header-injection vectors out without enforcing the prefix
		// (Stripe may rotate the prefix in the future).
		$value = preg_replace( '/[\r\n\t\0\x0B\s]+/', '', $value ) ?? '';
		return (string) $value;
	}

	public static function sanitize_role( $input ): string {
		$role = is_string( $input ) ? strtolower( trim( $input ) ) : '';
		if ( '' === $role ) {
			return '';
		}
		if ( ! preg_match( '/^[a-z0-9_-]{1,40}$/', $role ) ) {
			return '';
		}
		return $role;
	}

	public static function sanitize_events( $input ): string {
		// Accept POSTed checkbox array OR a single comma-list string.
		$incoming = is_array( $input ) ? $input : ( is_string( $input ) && '' !== $input ? explode( ',', $input ) : array() );
		$valid    = array();
		foreach ( $incoming as $type ) {
			$type = is_string( $type ) ? trim( $type ) : '';
			if ( in_array( $type, WebhookRules::SUPPORTED_EVENTS, true ) ) {
				$valid[] = $type;
			}
		}
		// Storage format is JSON so the option round-trips cleanly.
		return (string) \wp_json_encode( array_values( array_unique( $valid ) ) );
	}

	public static function render_tab( $existing, $tab ): string {
		if ( '' !== (string) $existing ) {
			return (string) $existing;
		}
		if ( Settings::TAB_WEBHOOKS !== (string) $tab ) {
			return (string) $existing;
		}
		ob_start();
		self::render_form();
		return (string) ob_get_clean();
	}

	private static function render_form(): void {
		$secret_raw = (string) \get_option( WebhookEndpoint::OPTION_SECRET, '' );
		$masked     = '' === $secret_raw ? '' : str_repeat( Sanitizer::MASK_CHAR, 12 );
		$role_create = (string) \get_option( WebhookRules::OPTION_ROLE_ON_CREATED, '' );
		$role_delete = (string) \get_option( WebhookRules::OPTION_ROLE_ON_DELETED, '' );
		$role_past   = (string) \get_option( WebhookRules::OPTION_ROLE_WHEN_PAST_DUE, '' );
		$enabled     = WebhookRules::enabled_events();

		$webhook_url = function_exists( '\rest_url' )
			? \rest_url( WebhookEndpoint::REST_NAMESPACE . WebhookEndpoint::REST_ROUTE )
			: '/wp-json/' . WebhookEndpoint::REST_NAMESPACE . WebhookEndpoint::REST_ROUTE;

		?>
		<h2><?php \esc_html_e( 'Stripe webhooks → WP role automation', 'login-stripe-customer-portal' ); ?></h2>
		<p class="description"><?php \esc_html_e( 'Subscribe to Stripe events and have the plugin assign / remove WP roles based on subscription state.', 'login-stripe-customer-portal' ); ?></p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php \esc_html_e( 'Webhook endpoint URL', 'login-stripe-customer-portal' ); ?></th>
				<td>
					<input type="text" readonly value="<?php echo \esc_attr( $webhook_url ); ?>" class="large-text code" onfocus="this.select();" />
					<p class="description"><?php \esc_html_e( 'Paste this URL into Stripe Dashboard → Developers → Webhooks.', 'login-stripe-customer-portal' ); ?></p>
				</td>
			</tr>
		</table>

		<form method="post" action="options.php">
			<?php \settings_fields( self::GROUP ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="lscp_pro_webhook_secret"><?php \esc_html_e( 'Webhook signing secret', 'login-stripe-customer-portal' ); ?></label></th>
					<td>
						<input id="lscp_pro_webhook_secret" type="password" name="<?php echo \esc_attr( WebhookEndpoint::OPTION_SECRET ); ?>" value="<?php echo \esc_attr( $masked ); ?>" autocomplete="off" class="regular-text" placeholder="whsec_..." />
						<p class="description"><?php \esc_html_e( 'Copy the signing secret from your Stripe webhook configuration. Required.', 'login-stripe-customer-portal' ); ?></p>
					</td>
				</tr>
			</table>

			<h3><?php \esc_html_e( 'Role automation', 'login-stripe-customer-portal' ); ?></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="lscp_pro_webhook_role_on_created"><?php \esc_html_e( 'Role on subscription created', 'login-stripe-customer-portal' ); ?></label></th>
					<td>
						<input id="lscp_pro_webhook_role_on_created" type="text" name="<?php echo \esc_attr( WebhookRules::OPTION_ROLE_ON_CREATED ); ?>" value="<?php echo \esc_attr( $role_create ); ?>" placeholder="subscriber" class="regular-text" />
						<p class="description"><?php \esc_html_e( 'Assigned when customer.subscription.created (or .updated with status=active|trialing) fires. Leave blank to disable.', 'login-stripe-customer-portal' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lscp_pro_webhook_role_on_deleted"><?php \esc_html_e( 'Downgrade role on cancellation', 'login-stripe-customer-portal' ); ?></label></th>
					<td>
						<input id="lscp_pro_webhook_role_on_deleted" type="text" name="<?php echo \esc_attr( WebhookRules::OPTION_ROLE_ON_DELETED ); ?>" value="<?php echo \esc_attr( $role_delete ); ?>" placeholder="" class="regular-text" />
						<p class="description"><?php \esc_html_e( 'Optional. Assigned when customer.subscription.deleted fires. Leave blank to only remove the "created" role.', 'login-stripe-customer-portal' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lscp_pro_webhook_role_when_past_due"><?php \esc_html_e( 'Role when payment fails', 'login-stripe-customer-portal' ); ?></label></th>
					<td>
						<input id="lscp_pro_webhook_role_when_past_due" type="text" name="<?php echo \esc_attr( WebhookRules::OPTION_ROLE_WHEN_PAST_DUE ); ?>" value="<?php echo \esc_attr( $role_past ); ?>" placeholder="" class="regular-text" />
						<p class="description"><?php \esc_html_e( 'Optional. Assigned when invoice.payment_failed (or subscription status past_due) fires.', 'login-stripe-customer-portal' ); ?></p>
					</td>
				</tr>
			</table>

			<h3><?php \esc_html_e( 'Enabled events', 'login-stripe-customer-portal' ); ?></h3>
			<p class="description"><?php \esc_html_e( 'Only checked events will trigger automation. All other events are ignored (200 response, no side-effects).', 'login-stripe-customer-portal' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php \esc_html_e( 'Events', 'login-stripe-customer-portal' ); ?></th>
					<td>
						<?php foreach ( WebhookRules::SUPPORTED_EVENTS as $event_type ) : ?>
							<label style="display:block;margin-bottom:6px;">
								<input type="checkbox" name="<?php echo \esc_attr( WebhookRules::OPTION_EVENTS_ENABLED ); ?>[]" value="<?php echo \esc_attr( $event_type ); ?>" <?php \checked( in_array( $event_type, $enabled, true ) ); ?> />
								<code><?php echo \esc_html( $event_type ); ?></code>
							</label>
						<?php endforeach; ?>
					</td>
				</tr>
			</table>

			<?php \submit_button(); ?>
		</form>
		<?php
	}
}
