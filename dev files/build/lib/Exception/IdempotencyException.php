<?php
namespace LSCP\Stripe\Exception;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * IdempotencyException is thrown in cases where an idempotency key was used
 * improperly.
 */
class IdempotencyException extends ApiErrorException
{
}