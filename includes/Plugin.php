<?php
/**
 * Plugin orchestrator. Wires the units together and registers WP hooks.
 *
 * The legacy entry point (new LSCP\Plugin()) and its public method names
 * remain in place via the legacy shim in the bootstrap file so that any
 * external code referencing them does not break.
 *
 * @package LSCP
 */

declare( strict_types = 1 );

namespace LSCP;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class Plugin {

	/** @var Settings */
	public $settings;

	/** @var RewriteEndpoint */
	public $rewrite;

	/** @var Shortcode */
	public $shortcode;

	/** @var PortalController */
	public $controller;

	/** @var TokenGC */
	public $gc;

	/** @var Privacy */
	public $privacy;

	public function __construct() {
		$this->settings   = new Settings();
		$this->rewrite    = new RewriteEndpoint();
		$this->shortcode  = new Shortcode();
		$this->controller = new PortalController();
		$this->gc         = new TokenGC();
		$this->privacy    = new Privacy();

		$this->settings->register_hooks();
		$this->rewrite->register_hooks();
		$this->shortcode->register_hooks();
		$this->controller->register_hooks();
		$this->gc->register_hooks();
		$this->privacy->register_hooks();

		Cli::register();

		// Bootstrap LSCP PRO units when the Freemius runtime confirms premium
		// is active. The Loader is a no-op when premium is not in use, so
		// loading it unconditionally is safe (and keeps the bootstrap simple).
		if ( class_exists( '\\LSCP\\Pro\\Loader' ) ) {
			\LSCP\Pro\Loader::register();
		}
	}

	// --------------------------------------------------------------------
	// Backwards-compatible shims for the 1.0.x public method surface.
	// --------------------------------------------------------------------

	public function add_settings_page(): void {
		$this->settings->add_settings_page();
	}

	public function register_settings(): void {
		$this->settings->register_settings();
	}

	public function render_settings_page(): void {
		$this->settings->render_settings_page();
	}

	public function add_customer_portal_endpoint(): void {
		$this->rewrite->register();
	}

	public function register_shortcodes(): void {
		$this->shortcode->register();
	}

	public function handle_customer_portal(): void {
		$this->controller->dispatch();
	}

	public function render_shortcode_form(): string {
		return $this->shortcode->render( array() );
	}

	public function render_email_form(): void {
		FormRenderer::render();
	}

	public function send_login_email( string $email ): bool {
		return $this->controller->maybe_send_login_email( $email );
	}

	public function check_if_customer_exists( string $email ): bool {
		$key = (string) \get_option( Settings::OPTION_API_KEY, '' );
		if ( '' === trim( $key ) ) {
			return false;
		}
		try {
			$gateway = new StripeGateway( $key );
			return null !== $gateway->find_customer_id( $email );
		} catch ( StripeGatewayException $e ) {
			return false;
		}
	}

	public function sanitize_secret_key( $input ) {
		return $this->settings->sanitize_secret_key( $input );
	}

	public function sanitize_redirect_url( $input ) {
		return $this->settings->sanitize_redirect_url( $input );
	}

	public function sanitize_checkbox( $input ) {
		return $this->settings->sanitize_checkbox( $input );
	}
}
