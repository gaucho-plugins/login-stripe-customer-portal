<?php
namespace LSCP\Stripe\Exception\OAuth;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Implements properties and methods common to all (non-SPL) Stripe OAuth
 * exceptions.
 */
abstract class OAuthErrorException extends \LSCP\Stripe\Exception\ApiErrorException
{
    protected function constructErrorObject()
    {
        if (null === $this->jsonBody) {
            return null;
        }
        return \LSCP\Stripe\OAuthErrorObject::constructFrom($this->jsonBody);
    }
}