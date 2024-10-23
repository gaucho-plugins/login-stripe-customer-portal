<?php
namespace LSCP\Stripe\Exception;

if ( ! defined( 'ABSPATH' ) ) exit;

class UnexpectedValueException extends \UnexpectedValueException implements ExceptionInterface
{
}