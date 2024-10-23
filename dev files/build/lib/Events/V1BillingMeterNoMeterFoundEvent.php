<?php
namespace LSCP\Stripe\Events;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * @property \Stripe\EventData\V1BillingMeterNoMeterFoundEventData $data data associated with the event
 */
class V1BillingMeterNoMeterFoundEvent extends \LSCP\Stripe\V2\Event
{
    const LOOKUP_TYPE = 'v1.billing.meter.no_meter_found';
}