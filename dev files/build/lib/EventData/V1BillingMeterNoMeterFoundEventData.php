<?php
namespace LSCP\Stripe\EventData;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * @property string $developer_message_summary Extra field included in the event's <code>data</code> when fetched from /v2/events.
 * @property \Stripe\StripeObject $reason This contains information about why meter error happens.
 * @property int $validation_end The end of the window that is encapsulated by this summary.
 * @property int $validation_start The start of the window that is encapsulated by this summary.
 */
class V1BillingMeterNoMeterFoundEventData extends \LSCP\Stripe\StripeObject
{
}