<?php
/**
 * Exception thrown by the Stripe gateway wrapper. Lives in its own file so
 * PHPCS (Generic.Files.OneObjectStructurePerFile) stays clean.
 *
 * @package LSCP
 */

declare( strict_types = 1 );

namespace LSCP;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

class StripeGatewayException extends \RuntimeException {}
