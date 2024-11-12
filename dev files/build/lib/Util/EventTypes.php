<?php
namespace LSCP\Stripe\Util;

if ( ! defined( 'ABSPATH' ) ) exit;

class EventTypes
{
    const thinEventMapping = [
        // The beginning of the section generated from our OpenAPI spec
        \LSCP\Stripe\Events\V1BillingMeterErrorReportTriggeredEvent::LOOKUP_TYPE => \LSCP\Stripe\Events\V1BillingMeterErrorReportTriggeredEvent::class,
        \LSCP\Stripe\Events\V1BillingMeterNoMeterFoundEvent::LOOKUP_TYPE => \LSCP\Stripe\Events\V1BillingMeterNoMeterFoundEvent::class,
    ];
}