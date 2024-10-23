<?php
namespace LSCP\Stripe\Exception\OAuth;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * InvalidScopeException is thrown when an invalid scope parameter is provided.
 */
class InvalidScopeException extends OAuthErrorException
{
}