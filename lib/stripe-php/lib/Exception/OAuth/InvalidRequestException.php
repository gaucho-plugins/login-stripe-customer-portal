<?php
namespace LSCP\Stripe\Exception\OAuth;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * InvalidRequestException is thrown when a code, refresh token, or grant
 * type parameter is not provided, but was required.
 */
class InvalidRequestException extends OAuthErrorException
{
}