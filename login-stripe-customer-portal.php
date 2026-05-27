<?php
/**
 * Plugin Name: Login for Stripe Customer Portal
 * Description: Allow merchants to connect Stripe and provide a customer login endpoint for the Stripe Customer Portal.
 * Version: 1.1.0
 * Author: Gaucho Plugins
 * Author URI:      https://gauchoplugins.com/
 * License: GPLv3
 * Text Domain: login-stripe-customer-portal
 *
 * @package LSCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LSCP_PLUGIN_FILE' ) ) {
	define( 'LSCP_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'LSCP_PLUGIN_DIR' ) ) {
	define( 'LSCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'LSCP_PLUGIN_VERSION' ) ) {
	define( 'LSCP_PLUGIN_VERSION', '1.1.0' );
}

if ( ! function_exists( 'lscp_fs' ) ) {
	/**
	 * Helper for Freemius SDK access.
	 */
	function lscp_fs() {
		global $lscp_fs;

		if ( ! isset( $lscp_fs ) ) {
			require_once LSCP_PLUGIN_DIR . 'freemius/start.php';

			$lscp_fs = fs_dynamic_init(
				array(
					'id'             => '16814',
					'slug'           => 'login-stripe-customer-portal',
					'premium_slug'   => 'login-stripe-customer-portal-pro',
					'type'           => 'plugin',
					'public_key'     => 'pk_816f55d4825ad20415edb31060db5',
					'is_premium'     => true,
					'premium_suffix' => 'PRO',
					'has_addons'     => false,
					'has_paid_plans' => true,
					'is_live'        => true,
					'menu'           => array(
						'slug'       => 'login-stripe-customer-portal',
						'first-path' => 'admin.php?page=login-stripe-customer-portal',
						'account'    => false,
						'support'    => false,
					),
				)
			);
		}

		return $lscp_fs;
	}

	lscp_fs();
	do_action( 'lscp_fs_loaded' );

	// Freemius requires uninstall logic to be registered via its after_uninstall
	// hook (NOT via a standalone uninstall.php) so the SDK can capture the
	// uninstall reason / feedback survey before WordPress tears the plugin
	// down. The cleanup logic itself lives in LSCP\Uninstall::run().
	lscp_fs()->add_action(
		'after_uninstall',
		function () {
			if ( ! class_exists( 'LSCP\\Uninstall' ) ) {
				$path = LSCP_PLUGIN_DIR . 'includes/Uninstall.php';
				if ( file_exists( $path ) ) {
					require_once $path;
				}
			}
			if ( class_exists( 'LSCP\\Uninstall' ) ) {
				LSCP\Uninstall::run();
			}
		}
	);
}

// Stripe SDK (vendored).
require_once LSCP_PLUGIN_DIR . 'lib/stripe-php/init.php';

// Composer autoloader (only present in dev / when committed).
if ( file_exists( LSCP_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once LSCP_PLUGIN_DIR . 'vendor/autoload.php';
}

// PSR-4 fallback autoloader for LSCP\ — keeps the plugin working in release
// builds that don't ship vendor/.
spl_autoload_register(
	function ( $class_name ) {
		if ( 0 !== strpos( $class_name, 'LSCP\\' ) ) {
			return;
		}
		if ( 0 === strpos( $class_name, 'LSCP\\Stripe\\' ) ) {
			// Stripe SDK has its own loader.
			return;
		}
		if ( 0 === strpos( $class_name, 'LSCP\\Tests\\' ) ) {
			return;
		}
		$rel  = substr( $class_name, strlen( 'LSCP\\' ) );
		$path = LSCP_PLUGIN_DIR . 'includes/' . str_replace( '\\', DIRECTORY_SEPARATOR, $rel ) . '.php';
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook(
	__FILE__,
	function () {
		( new LSCP\RewriteEndpoint() )->on_activate();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		( new LSCP\RewriteEndpoint() )->on_deactivate();
	}
);

// Boot the orchestrator. The class registers its own WP hooks.
new LSCP\Plugin();
