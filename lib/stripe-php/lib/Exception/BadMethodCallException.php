<?php
namespace LSCP\Stripe\Exception;

if ( ! defined( 'ABSPATH' ) ) exit;

class BadMethodCallException extends \BadMethodCallException implements ExceptionInterface
{
}