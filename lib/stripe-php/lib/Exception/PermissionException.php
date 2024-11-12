<?php
namespace LSCP\Stripe\Exception;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * PermissionException is thrown in cases where access was attempted on a
 * resource that wasn't allowed.
 */
class PermissionException extends ApiErrorException
{
}