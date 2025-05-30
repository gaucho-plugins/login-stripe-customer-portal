<?php
namespace LSCP\Stripe;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * @property string $id Unique identifier for the event.
 * @property string $idempotency_key
 */
class Reason
{
    public $id;
    public $idempotency_key;
}