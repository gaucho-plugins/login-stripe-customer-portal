<?php
/**
 * WordPress invokes this file when the user deletes the plugin from the
 * Plugins screen. We don't load the main plugin bootstrap (Freemius, Stripe
 * SDK, etc.); we just clean up our own options and transients.
 *
 * @package LSCP
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/Uninstall.php';
require_once __DIR__ . '/includes/TokenGC.php';

LSCP\Uninstall::run();
