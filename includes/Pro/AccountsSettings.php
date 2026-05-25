<?php
/**
 * Multi-Account settings tab: list of accounts with add / edit / remove.
 *
 * The form POSTs to options.php like every other LSCP tab. We register
 * one combined option (lscp_pro_accounts) whose sanitize_callback parses
 * the nested-array POST and re-serializes as JSON.
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

final class AccountsSettings {

	public const GROUP = 'lscp_pro_accounts_group';

	public static function register(): void {
		\add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		\add_filter( 'lscp_settings_tab_' . Settings::TAB_MULTI_ACCOUNT . '_content', array( __CLASS__, 'render_tab' ), 10, 2 );
	}

	public static function register_settings(): void {
		\register_setting(
			self::GROUP,
			Accounts::OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_accounts_post' ),
				'default'           => '',
			)
		);
	}

	/**
	 * Sanitize a $_POST submission for the accounts table. The form posts
	 * `lscp_pro_accounts[N][field]` per row; we collapse to a list, drop
	 * empty rows, and preserve API keys for rows that POSTed a mask.
	 */
	public static function sanitize_accounts_post( $input ): string {
		$existing  = Accounts::all();
		$by_id_old = array();
		foreach ( $existing as $row ) {
			$by_id_old[ $row['id'] ] = $row;
		}

		// $input may be an array (real WP) or a JSON string (test environments).
		$rows = array();
		if ( is_array( $input ) ) {
			$rows = $input;
		} elseif ( is_string( $input ) && '' !== $input ) {
			$decoded = json_decode( $input, true );
			$rows    = is_array( $decoded ) ? $decoded : array();
		}

		$clean = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$norm = Accounts::normalize_row( $row );
			if ( '' === $norm['slug'] ) {
				continue;
			}
			// API-key mask substitution: if the POSTed key is a pure mask,
			// keep the previously-stored key for this account id.
			if ( '' !== $norm['api_key'] && Sanitizer::is_pure_mask( $norm['api_key'] ) ) {
				$prev             = $by_id_old[ $norm['id'] ] ?? null;
				$norm['api_key']  = is_array( $prev ) ? (string) ( $prev['api_key'] ?? '' ) : '';
			}
			$clean[] = $norm;
		}
		return (string) \wp_json_encode( $clean );
	}

	public static function render_tab( $existing, $tab ): string {
		if ( '' !== (string) $existing ) {
			return (string) $existing;
		}
		if ( Settings::TAB_MULTI_ACCOUNT !== (string) $tab ) {
			return (string) $existing;
		}
		ob_start();
		self::render_form();
		return (string) ob_get_clean();
	}

	private static function render_form(): void {
		$accounts = Accounts::all();
		// Always render at least one empty row so the admin can add the first account.
		if ( empty( $accounts ) ) {
			$accounts[] = array(
				'id'                => Accounts::sanitize_id( '' ),
				'label'             => '',
				'api_key'           => '',
				'slug'              => '',
				'validate_existing' => '0',
				'redirect_url'      => '',
				'from_email'        => '',
				'from_name'         => '',
			);
		}
		?>
		<h2><?php \esc_html_e( 'Multi-Stripe-account', 'login-stripe-customer-portal' ); ?></h2>
		<p class="description"><?php \esc_html_e( 'Run multiple Stripe accounts from one site. Each account binds to a unique URL slug and overrides the General-tab settings when its slug is hit.', 'login-stripe-customer-portal' ); ?></p>

		<form method="post" action="options.php">
			<?php \settings_fields( self::GROUP ); ?>

			<table class="widefat striped" style="margin-top:16px;">
				<thead>
					<tr>
						<th><?php \esc_html_e( 'Label', 'login-stripe-customer-portal' ); ?></th>
						<th><?php \esc_html_e( 'Slug', 'login-stripe-customer-portal' ); ?></th>
						<th><?php \esc_html_e( 'Stripe Secret Key', 'login-stripe-customer-portal' ); ?></th>
						<th><?php \esc_html_e( 'Redirect URL', 'login-stripe-customer-portal' ); ?></th>
						<th><?php \esc_html_e( 'Validate existing', 'login-stripe-customer-portal' ); ?></th>
						<th><?php \esc_html_e( 'From email', 'login-stripe-customer-portal' ); ?></th>
						<th><?php \esc_html_e( 'From name', 'login-stripe-customer-portal' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $accounts as $i => $a ) :
						$masked_key = '' !== $a['api_key'] ? str_repeat( Sanitizer::MASK_CHAR, 12 ) : '';
						?>
						<tr>
							<td>
								<input type="hidden" name="<?php echo \esc_attr( Accounts::OPTION . '[' . $i . '][id]' ); ?>" value="<?php echo \esc_attr( $a['id'] ); ?>" />
								<input type="text" name="<?php echo \esc_attr( Accounts::OPTION . '[' . $i . '][label]' ); ?>" value="<?php echo \esc_attr( $a['label'] ); ?>" placeholder="<?php \esc_attr_e( 'EU Stripe', 'login-stripe-customer-portal' ); ?>" />
							</td>
							<td>
								<input type="text" name="<?php echo \esc_attr( Accounts::OPTION . '[' . $i . '][slug]' ); ?>" value="<?php echo \esc_attr( $a['slug'] ); ?>" placeholder="billing-eu" />
							</td>
							<td>
								<input type="password" name="<?php echo \esc_attr( Accounts::OPTION . '[' . $i . '][api_key]' ); ?>" value="<?php echo \esc_attr( $masked_key ); ?>" autocomplete="off" placeholder="sk_live_…" />
							</td>
							<td>
								<input type="url" name="<?php echo \esc_attr( Accounts::OPTION . '[' . $i . '][redirect_url]' ); ?>" value="<?php echo \esc_url( $a['redirect_url'] ); ?>" placeholder="https://example.com/thanks" />
							</td>
							<td style="text-align:center;">
								<input type="checkbox" name="<?php echo \esc_attr( Accounts::OPTION . '[' . $i . '][validate_existing]' ); ?>" value="1" <?php \checked( '1', $a['validate_existing'] ); ?> />
							</td>
							<td>
								<input type="email" name="<?php echo \esc_attr( Accounts::OPTION . '[' . $i . '][from_email]' ); ?>" value="<?php echo \esc_attr( $a['from_email'] ); ?>" placeholder="billing@example.com" />
							</td>
							<td>
								<input type="text" name="<?php echo \esc_attr( Accounts::OPTION . '[' . $i . '][from_name]' ); ?>" value="<?php echo \esc_attr( $a['from_name'] ); ?>" placeholder="Acme Billing" />
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p class="description" style="margin-top:8px;">
				<?php \esc_html_e( 'To add another account, save the current row first; an empty row will appear on the next render. Leave the slug blank and save to remove a row.', 'login-stripe-customer-portal' ); ?>
				<?php \esc_html_e( 'Remember to flush permalinks (Settings → Permalinks → Save) after adding a new slug.', 'login-stripe-customer-portal' ); ?>
			</p>

			<?php \submit_button(); ?>
		</form>
		<?php
	}
}
