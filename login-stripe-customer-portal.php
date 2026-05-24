<?php
/**
 * Plugin Name: Login for Stripe Customer Portal
 * Description: Allow merchants to connect Stripe and provide a customer login endpoint for the Stripe Customer Portal.
 * Version: 1.0.6
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
	define( 'LSCP_PLUGIN_VERSION', '1.0.6' );
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
					'type'           => 'plugin',
					'public_key'     => 'pk_816f55d4825ad20415edb31060db5',
					'is_premium'     => false,
					'has_addons'     => false,
					'has_paid_plans' => false,
					'menu'           => array(
						'slug'    => 'login-stripe-customer-portal',
						'account' => false,
					),
				)
			);
		}

		return $lscp_fs;
	}

	lscp_fs();
	do_action( 'lscp_fs_loaded' );
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
