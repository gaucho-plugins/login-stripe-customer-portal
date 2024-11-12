<?php
namespace LSCP\Stripe\Exception\OAuth;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * UnsupportedResponseTypeException is thrown when an unsupported response type
 * parameter is specified.
 */
class UnsupportedResponseTypeException extends OAuthErrorException
{
}