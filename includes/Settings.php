<?php
/**
 * Settings registration + tabbed settings-page render.
 *
 * Sanitizer methods delegate to the pure-function Sanitizer class so they can
 * be unit-tested without booting WP.
 *
 * 1.1.0 introduces a tabbed layout. The General tab keeps the existing
 * settings UI verbatim (so 1.0.x users see no difference). Each PRO tab
 * defaults to an Upgrade-to-PRO CTA in the free build; PRO classes hook
 * `lscp_settings_tab_<slug>_content` to inject real content per phase.
 *
 * @package LSCP
 */

declare( strict_types = 1 );

namespace LSCP;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class Settings {

	public const GROUP                    = 'lscp_settings_group';
	public const OPTION_API_KEY           = 'lscp_stripe_api_key';
	public const OPTION_REDIRECT_URL      = 'lscp_stripe_redirect_url';
	public const OPTION_ENDPOINT_SLUG     = 'lscp_stripe_endpoint_slug';
	public const OPTION_VALIDATE_EXISTING = 'lscp_stripe_validate_existing_customers';
	public const DEFAULT_SLUG             = 'customer-portal';
	public const MENU_SLUG                = 'login-stripe-customer-portal';
	public const CAPABILITY               = 'manage_options';

	public const TAB_GENERAL       = 'general';
	public const TAB_EMAIL         = 'email-templates';
	public const TAB_FORM          = 'form-style';
	public const TAB_INTEGRATIONS  = 'integrations';
	public const TAB_WEBHOOKS      = 'webhooks';
	public const TAB_MULTI_ACCOUNT = 'multi-account';

	public function register_hooks(): void {
		\add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		\add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function add_settings_page(): void {
		\add_menu_page(
			\__( 'Embed Stripe Customer Portal', 'login-stripe-customer-portal' ),
			\__( 'Stripe Portal', 'login-stripe-customer-portal' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_settings_page' ),
			'dashicons-businessperson',
			100
		);
	}

	public function register_settings(): void {
		\register_setting(
			self::GROUP,
			self::OPTION_API_KEY,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_secret_key' ),
				'default'           => '',
			)
		);
		\register_setting(
			self::GROUP,
			self::OPTION_REDIRECT_URL,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_redirect_url' ),
				'default'           => '',
			)
		);
		\register_setting(
			self::GROUP,
			self::OPTION_ENDPOINT_SLUG,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_endpoint_slug' ),
				'default'           => self::DEFAULT_SLUG,
			)
		);
		\register_setting(
			self::GROUP,
			self::OPTION_VALIDATE_EXISTING,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => '0',
			)
		);
	}

	public function sanitize_secret_key( $input ) {
		$current = \get_option( self::OPTION_API_KEY, '' );
		return Sanitizer::sanitize_secret_key( $input, is_string( $current ) ? $current : '' );
	}

	public function sanitize_redirect_url( $input ) {
		return Sanitizer::sanitize_redirect_url( $input );
	}

	public function sanitize_endpoint_slug( $input ) {
		return Sanitizer::sanitize_endpoint_slug( $input );
	}

	public function sanitize_checkbox( $input ) {
		return Sanitizer::sanitize_checkbox( $input );
	}

	/**
	 * The tab list. Filterable so PRO phases can re-order or insert tabs.
	 *
	 * @return array<string,array{label:string,pro:bool}>
	 */
	public function tabs(): array {
		$tabs = array(
			self::TAB_GENERAL       => array(
				'label' => \__( 'General', 'login-stripe-customer-portal' ),
				'pro'   => false,
			),
			self::TAB_EMAIL         => array(
				'label' => \__( 'Email Templates', 'login-stripe-customer-portal' ),
				'pro'   => true,
			),
			self::TAB_FORM          => array(
				'label' => \__( 'Form Style', 'login-stripe-customer-portal' ),
				'pro'   => true,
			),
			self::TAB_INTEGRATIONS  => array(
				'label' => \__( 'Integrations', 'login-stripe-customer-portal' ),
				'pro'   => true,
			),
			self::TAB_WEBHOOKS      => array(
				'label' => \__( 'Webhooks', 'login-stripe-customer-portal' ),
				'pro'   => true,
			),
			self::TAB_MULTI_ACCOUNT => array(
				'label' => \__( 'Multi-Account', 'login-stripe-customer-portal' ),
				'pro'   => true,
			),
		);

		/**
		 * Filter the settings-page tab list.
		 *
		 * @param array $tabs Default tab list.
		 */
		$filtered = \apply_filters( 'lscp_settings_tabs', $tabs );
		return is_array( $filtered ) && ! empty( $filtered ) ? $filtered : $tabs;
	}

	public function current_tab(): string {
		$tabs = $this->tabs();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab nav.
		$req = isset( $_GET['tab'] ) ? \sanitize_key( \wp_unslash( (string) $_GET['tab'] ) ) : '';
		return isset( $tabs[ $req ] ) ? $req : self::TAB_GENERAL;
	}

	private function tab_url( string $tab ): string {
		return \add_query_arg(
			array(
				'page' => self::MENU_SLUG,
				'tab'  => $tab,
			),
			\admin_url( 'admin.php' )
		);
	}

	public function render_settings_page(): void {
		if ( ! \current_user_can( self::CAPABILITY ) ) {
			\wp_die( \esc_html__( 'You do not have permission to access this page.', 'login-stripe-customer-portal' ) );
		}

		$active_tab = $this->current_tab();
		$tabs       = $this->tabs();

		?>
		<div class="wrap">
			<h1><?php \esc_html_e( 'Login for Stripe Customer Portal Settings', 'login-stripe-customer-portal' ); ?></h1>

			<?php
			if ( class_exists( '\\LSCP\\Pro\\UpgradeCTA' ) ) {
				\LSCP\Pro\UpgradeCTA::render_banner();
			}
			?>

			<nav class="nav-tab-wrapper lscp-tabs" aria-label="<?php \esc_attr_e( 'Settings tabs', 'login-stripe-customer-portal' ); ?>">
				<?php foreach ( $tabs as $slug => $tab ) : ?>
					<?php
					$is_active = ( $slug === $active_tab );
					$classes   = $is_active ? 'nav-tab nav-tab-active' : 'nav-tab';
					$label     = (string) $tab['label'];
					if ( ! empty( $tab['pro'] ) && ( ! class_exists( '\\LSCP\\Pro\\UpgradeCTA' ) || ! \LSCP\Pro\UpgradeCTA::is_premium() ) ) {
						$label .= ' 🔓';
					}
					?>
					<a class="<?php echo \esc_attr( $classes ); ?>" href="<?php echo \esc_url( $this->tab_url( $slug ) ); ?>">
						<?php echo \esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="lscp-tab-content" style="margin-top:16px;">
				<?php $this->render_tab( $active_tab ); ?>
			</div>
		</div>
		<?php
	}

	private function render_tab( string $tab ): void {
		$tabs = $this->tabs();
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = self::TAB_GENERAL;
		}

		/**
		 * Filter the rendered content of a settings tab. Returning a non-empty
		 * string short-circuits the default render (used by PRO phases).
		 *
		 * @param string $html Default empty (delegates to the default render below).
		 * @param string $tab  Tab slug.
		 */
		$custom = (string) \apply_filters( "lscp_settings_tab_{$tab}_content", '', $tab );
		if ( '' !== $custom ) {
			echo $custom; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- PRO tabs escape internally.
			return;
		}

		switch ( $tab ) {
			case self::TAB_GENERAL:
				$this->render_general_tab();
				return;
			case self::TAB_EMAIL:
				$this->render_pro_tab_stub(
					\__( 'Branded magic-link emails', 'login-stripe-customer-portal' ),
					\__( '6 pre-built email templates, logo upload, brand colors, custom subject + CTA. Replace the plain HTML magic-link email with a branded version that matches your site.', 'login-stripe-customer-portal' )
				);
				return;
			case self::TAB_FORM:
				$this->render_pro_tab_stub(
					\__( 'Login-form styler', 'login-stripe-customer-portal' ),
					\__( 'Template library, color pickers, custom heading + helper text, additional collected fields. Match the form to your brand without writing CSS.', 'login-stripe-customer-portal' )
				);
				return;
			case self::TAB_INTEGRATIONS:
				$this->render_pro_tab_stub(
					\__( 'Integrations', 'login-stripe-customer-portal' ),
					\__( 'WooCommerce, MemberPress, LearnDash, Restrict Content Pro: "Manage Billing" button on the account page. WP user auto-create + Stripe-customer linkage so logged-in users skip the email step.', 'login-stripe-customer-portal' )
				);
				return;
			case self::TAB_WEBHOOKS:
				$this->render_pro_tab_stub(
					\__( 'Stripe webhooks → WP role automation', 'login-stripe-customer-portal' ),
					\__( 'Subscribe to Stripe events (subscription.created/updated/deleted, invoice.paid/payment_failed, …) and assign / remove WP roles or fire custom action hooks. Replaces a separate $99/yr SaaS membership tool.', 'login-stripe-customer-portal' )
				);
				return;
			case self::TAB_MULTI_ACCOUNT:
				$this->render_pro_tab_stub(
					\__( 'Multi-Stripe-account + agency white-label', 'login-stripe-customer-portal' ),
					\__( 'Run multiple Stripe accounts from one site (US + EU, multi-brand). Each gets its own endpoint slug + email config. White-label removes "Powered by Gaucho Plugins" branding.', 'login-stripe-customer-portal' )
				);
				return;
		}
	}

	private function render_pro_tab_stub( string $title, string $description ): void {
		if ( class_exists( '\\LSCP\\Pro\\UpgradeCTA' ) ) {
			\LSCP\Pro\UpgradeCTA::render_tab( $title, $description );
			return;
		}
		// Defensive fallback in the unlikely event the Pro\UpgradeCTA file is missing.
		?>
		<div class="notice notice-info"><p><?php echo \esc_html( $title ); ?></p></div>
		<?php
	}

	/**
	 * Render a documentation link.
	 */
	private function docs( string $path, string $text ): string {
		return DocsHelper::link( $path, $text );
	}

	private function render_general_tab(): void {
		$slug                        = (string) \get_option( self::OPTION_ENDPOINT_SLUG, self::DEFAULT_SLUG );
		$customer_portal_url         = \home_url( '/' . $slug . '/' );
		$validate_existing_customers = (string) \get_option( self::OPTION_VALIDATE_EXISTING, '0' );
		$secret_key                  = (string) \get_option( self::OPTION_API_KEY, '' );
		// Render a fixed-length mask so the input never leaks the real key length.
		$masked_key = '' !== $secret_key ? str_repeat( Sanitizer::MASK_CHAR, 12 ) : '';
		$redirect   = (string) \get_option( self::OPTION_REDIRECT_URL, $customer_portal_url );

		?>
		<p><?php \esc_html_e( 'Provide your Stripe Secret Key, Redirect URL, and Customer Portal Endpoint Slug below. After saving, the Secret Key will be hidden for security.', 'login-stripe-customer-portal' ); ?></p>
		<p class="description">
			<?php
			echo \wp_kses_post(
				sprintf(
					/* translators: 1: Quick Setup doc link, 2: Documentation home link */
					\esc_html__( 'Need help? See the %1$s or full %2$s.', 'login-stripe-customer-portal' ),
					$this->docs( 'getting-started/quick-setup', \__( 'Quick Setup guide', 'login-stripe-customer-portal' ) ),
					$this->docs( '', \__( 'documentation', 'login-stripe-customer-portal' ) )
				)
			);
			?>
		</p>
		<form method="post" action="options.php">
			<?php
			\settings_fields( self::GROUP );
			\do_settings_sections( self::GROUP );
			?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php \esc_html_e( 'Stripe Secret Key', 'login-stripe-customer-portal' ); ?></th>
					<td>
						<input type="password" name="<?php echo \esc_attr( self::OPTION_API_KEY ); ?>" value="<?php echo \esc_attr( $masked_key ); ?>" autocomplete="off" />
						<p class="description">
							<?php
							echo \wp_kses_post(
								sprintf(
									/* translators: %s: Documentation link */
									\esc_html__( 'Your Stripe Secret Key. After saving, it will be hidden. · %s', 'login-stripe-customer-portal' ),
									$this->docs( 'getting-started/stripe-prerequisites', \__( 'Learn how to get your API key', 'login-stripe-customer-portal' ) )
								)
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php \esc_html_e( 'Redirect URL', 'login-stripe-customer-portal' ); ?></th>
					<td>
						<input type="url" name="<?php echo \esc_attr( self::OPTION_REDIRECT_URL ); ?>" value="<?php echo \esc_url( $redirect ); ?>" />
						<p class="description">
							<?php
							echo \wp_kses_post(
								sprintf(
									/* translators: %s: Documentation link */
									\esc_html__( 'The URL to redirect the user back to after they exit the Stripe portal. Default is the customer portal page. · %s', 'login-stripe-customer-portal' ),
									$this->docs( 'configuration/settings-reference', \__( 'Settings reference', 'login-stripe-customer-portal' ) )
								)
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php \esc_html_e( 'Customer Portal Slug', 'login-stripe-customer-portal' ); ?></th>
					<td>
						<input type="text" name="<?php echo \esc_attr( self::OPTION_ENDPOINT_SLUG ); ?>" value="<?php echo \esc_attr( $slug ); ?>" />
						<p class="description">
							<?php
							echo \wp_kses_post(
								sprintf(
									/* translators: %s: Documentation link */
									\esc_html__( 'Customize the slug for the customer portal page. Leave empty to disable the page. · %s', 'login-stripe-customer-portal' ),
									$this->docs( 'usage/customer-portal-endpoint', \__( 'Customer portal endpoint', 'login-stripe-customer-portal' ) )
								)
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php \esc_html_e( 'Only allow existing Stripe customers to login', 'login-stripe-customer-portal' ); ?></th>
					<td>
						<input type="checkbox" name="<?php echo \esc_attr( self::OPTION_VALIDATE_EXISTING ); ?>" value="1" <?php \checked( '1', $validate_existing_customers ); ?> />
						<p class="description">
							<?php
							echo \wp_kses_post(
								sprintf(
									/* translators: %s: Documentation link */
									\esc_html__( 'If checked, only existing Stripe customers can log in. If unchecked (the default), any valid email that submits the form will receive a login link, and a new Stripe customer is created automatically when the link is redeemed. · %s', 'login-stripe-customer-portal' ),
									$this->docs( 'usage/login-flow', \__( 'Login flow', 'login-stripe-customer-portal' ) )
								)
							);
							?>
						</p>
					</td>
				</tr>
			</table>
			<?php \submit_button(); ?>
		</form>

		<?php if ( '' !== $slug ) : ?>
			<h2><?php \esc_html_e( 'Customer Portal URL', 'login-stripe-customer-portal' ); ?></h2>
			<p><?php \esc_html_e( 'Your customer portal is available at:', 'login-stripe-customer-portal' ); ?></p>
			<p>
				<a href="<?php echo \esc_url( $customer_portal_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php echo \esc_url( $customer_portal_url ); ?>
				</a>
			</p>
		<?php else : ?>
			<p><strong><?php \esc_html_e( 'Customer Portal is disabled. Please set a slug to enable it.', 'login-stripe-customer-portal' ); ?></strong></p>
		<?php endif; ?>

		<h2><?php \esc_html_e( 'Permalink Settings', 'login-stripe-customer-portal' ); ?></h2>
		<p>
			<?php
			echo \wp_kses_post(
				sprintf(
					/* translators: 1: Permalinks settings link, 2: Documentation link */
					\esc_html__( 'Make sure to resave your permalinks after making changes to the customer portal slug by going to %1$s. · %2$s', 'login-stripe-customer-portal' ),
					'<a href="' . \esc_url( \admin_url( 'options-permalink.php' ) ) . '" target="_blank" rel="noopener noreferrer">' . \esc_html__( 'Permalinks Settings', 'login-stripe-customer-portal' ) . '</a>',
					$this->docs( 'troubleshooting/troubleshooting', \__( 'Troubleshooting permalinks', 'login-stripe-customer-portal' ) )
				)
			);
			?>
		</p>
		<h2><?php \esc_html_e( 'Shortcode', 'login-stripe-customer-portal' ); ?></h2>
		<p>
			<?php
			echo \wp_kses_post(
				sprintf(
					/* translators: %s: Documentation link */
					\esc_html__( 'Use the following shortcode to display the Stripe portal login form on any page or post: · %s', 'login-stripe-customer-portal' ),
					$this->docs( 'usage/shortcode', \__( 'Shortcode documentation', 'login-stripe-customer-portal' ) )
				)
			);
			?>
		</p>
		<p><code>[login-stripe-customer-portal]</code></p>
		<p class="description">
			<?php \esc_html_e( 'New in 1.1.0:', 'login-stripe-customer-portal' ); ?>
			<code>[lscp-message]</code> —
			<?php \esc_html_e( 'shows the confirmation message inline on whatever page the form was submitted from (preserves your theme layout instead of a blank screen).', 'login-stripe-customer-portal' ); ?>
		</p>
		<?php
	}
}
