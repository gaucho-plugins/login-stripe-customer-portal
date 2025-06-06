<?php
namespace LSCP\Stripe\V2\Billing;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * @property string $id The unique id of this auth session.
 * @property string $object String representing the object's type. Objects of the same type share the same value of the object field.
 * @property string $authentication_token The authentication token for this session.  Use this token when calling the high-throughput meter event API.
 * @property int $created The creation time of this session.
 * @property int $expires_at The time at which this session will expire.
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 */
class MeterEventSession extends \LSCP\Stripe\ApiResource
{
    const OBJECT_NAME = 'billing.meter_event_session';
}