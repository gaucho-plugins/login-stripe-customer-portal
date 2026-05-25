<?php
/**
 * Boots LSCP PRO units. Only registers when the Freemius runtime confirms
 * the user is on a premium plan. The PRO classes themselves are also
 * wrapped in `is__premium_only()` guards / `@fs_premium_only` markers so
 * Freemius's preprocessor strips them from the FREE build.
 *
 * Each phase of LSCP 1.1 adds one or more `register_*` calls below. Phase
 * 0 keeps the loader empty (no PRO features yet); subsequent phases append.
 *
 * @package LSCP\Pro
 *
 * @fs_premium_only
 */

declare( strict_types = 1 );

namespace LSCP\Pro;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class Loader {

	/**
	 * Called from the Plugin orchestrator. No-op unless premium is active.
	 */
	public static function register(): void {
		if ( ! UpgradeCTA::is_premium() ) {
			return;
		}

		// Phase 1: branded magic-link email templates.
		EmailTemplates::register();
		EmailTemplateSettings::register();
		EmailPreview::register();

		// Phase 2: login-form styler.
		FormStyler::register();
		FormStylerSettings::register();
		FormStylePreview::register();

		// Phase 3: WP user ↔ Stripe customer bridge + integrations.
		UserBridge::register();
		IntegrationsSettings::register();
		// Integrations defer to plugins_loaded — class_exists('WooCommerce')
		// etc. returns false at bootstrap-time because LSCP loads BEFORE
		// the third-party plugins. By plugins_loaded priority 20 every
		// active plugin has loaded its classes and detection is reliable.
		\add_action(
			'plugins_loaded',
			static function () {
				Integrations\WooCommerce::register();
				Integrations\MemberPress::register();
				Integrations\LearnDash::register();
			},
			20
		);

		// Phase 4: Stripe webhook listener + WP role automation.
		WebhookEndpoint::register();
		WebhookSettings::register();

		// Phase 5: multi-Stripe-account + white-label.
		AccountRouter::register();
		AccountsSettings::register();
		WhiteLabel::register();
	}
}
