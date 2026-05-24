<?php
/**
 * Renders the magic-link email form. Shared between the rewrite endpoint
 * (full-page render) and the shortcode (embedded render).
 *
 * Centralizes the nonce action name and the form markup so security audits
 * have a single template to review.
 *
 * Extension surface (added in 1.1.0 Phase 0):
 *  - filter `lscp_form_template` — return alternative HTML for the form
 *    body (PRO templates plug in here).
 *  - filter `lscp_form_default_email` — pre-fill the email input (PRO's
 *    WP-user bridge uses this).
 *  - action `lscp_form_before` — fires inside the wrapper, before <form>.
 *  - action `lscp_form_after` — fires inside the wrapper, after </form>.
 *
 * Class is intentionally NOT `final` so PRO units can subclass for
 * richer rendering. (Filters are the primary extension mechanism; this
 * is the backup.)
 *
 * @package LSCP
 */

declare( strict_types = 1 );

namespace LSCP;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

class FormRenderer {

	public const NONCE_ACTION = 'lscp_stripe_portal_login_action';
	public const NONCE_NAME   = 'lscp_stripe_portal_nonce';

	/**
	 * Render the form. By default emits a full-viewport centered layout;
	 * pass ['embedded' => true] to skip the centered wrapper for inline use.
	 *
	 * @param array $args
	 */
	public static function render( array $args = array() ): void {
		$embedded      = ! empty( $args['embedded'] );
		$wrapper_style = $embedded
			? 'display:flex;justify-content:center;align-items:center;'
			: 'display:flex;justify-content:center;align-items:center;height:100vh;background-color:#f4f4f4;';

		$form_style = 'background-color:white;padding:30px;border-radius:8px;box-shadow:0 4px 8px rgba(0,0,0,0.1);max-width:400px;width:100%;text-align:center;';

		// Per-render unique ID so multiple shortcode instances on a single page
		// don't collide on `id="lscp-email"` (breaks <label for>, fails HTML5
		// validation, confuses screen readers).
		$email_id = self::unique_id( 'lscp-email' );

		// Default email value — empty in free, may be prefilled by PRO
		// (e.g. with the logged-in WP user's email).
		/**
		 * Filter the default value for the email input. Useful for PRO
		 * integrations that pre-fill from a logged-in WP user account.
		 *
		 * @param string $default Default empty string.
		 * @param array  $args    Render args.
		 */
		$default_email = (string) \apply_filters( 'lscp_form_default_email', '', $args );

		/**
		 * Filter the form body HTML. Returning a non-empty string short-
		 * circuits the default form markup.
		 *
		 * @param string $html Default empty (renders below).
		 * @param array  $args Render args.
		 */
		$custom_html = (string) \apply_filters( 'lscp_form_template', '', $args );

		?>
		<div style="<?php echo \esc_attr( $wrapper_style ); ?>">
			<?php
			/**
			 * Fires inside the form wrapper, BEFORE the <form>.
			 *
			 * @param array $args Render args.
			 */
			\do_action( 'lscp_form_before', $args );
			?>

			<?php if ( '' !== $custom_html ) : ?>
				<?php echo $custom_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- PRO templates pre-escape internally; trust the filter contract. ?>
			<?php else : ?>
				<form method="post" action="" style="<?php echo \esc_attr( $form_style ); ?>" class="lscp-portal-form" novalidate>
					<?php \wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
					<label for="<?php echo \esc_attr( $email_id ); ?>" style="display:block;margin-bottom:10px;font-weight:bold;">
						<?php \esc_html_e( 'Enter your email address:', 'login-stripe-customer-portal' ); ?>
					</label>
					<input type="email" name="email" id="<?php echo \esc_attr( $email_id ); ?>" required
						autocomplete="email"
						value="<?php echo \esc_attr( $default_email ); ?>"
						style="width:100%;padding:10px;margin-bottom:20px;border-radius:6px;border:1px solid #ccc;" />
					<input type="submit"
						value="<?php \esc_attr_e( 'Continue to Stripe Portal', 'login-stripe-customer-portal' ); ?>"
						style="background-color:#0073aa;color:white;border:none;padding:10px 20px;border-radius:6px;cursor:pointer;font-size:16px;" />
				</form>
			<?php endif; ?>

			<?php
			/**
			 * Fires inside the form wrapper, AFTER the <form>.
			 *
			 * @param array $args Render args.
			 */
			\do_action( 'lscp_form_after', $args );
			?>
		</div>
		<?php
	}

	/**
	 * Produce a per-render unique HTML id.
	 *
	 * Prefers WP's wp_unique_id() (5.0.3+); falls back to a manual counter for
	 * older WP and for the unit-test runtime where wp_unique_id is not stubbed.
	 */
	private static function unique_id( string $prefix ): string {
		if ( function_exists( 'wp_unique_id' ) ) {
			return \wp_unique_id( $prefix . '-' );
		}
		static $counter = 0;
		return $prefix . '-' . ( ++$counter );
	}
}
