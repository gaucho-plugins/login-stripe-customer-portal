<?php
namespace LSCP\Stripe\Exception\OAuth;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * UnsupportedGrantTypeException is thrown when an unuspported grant type
 * parameter is specified.
 */
class UnsupportedGrantTypeException extends OAuthErrorException
{
}