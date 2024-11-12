<?php
namespace LSCP\Stripe\Exception;

if ( ! defined( 'ABSPATH' ) ) exit;

class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface
{
}