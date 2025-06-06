<?php
namespace LSCP\Stripe\Billing;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * A billing meter event represents a customer's usage of a product. Meter events are used to bill a customer based on their usage.
 * Meter events are associated with billing meters, which define the shape of the event's payload and how those events are aggregated for billing.
 *
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property int $created Time at which the object was created. Measured in seconds since the Unix epoch.
 * @property string $event_name The name of the meter event. Corresponds with the <code>event_name</code> field on a meter.
 * @property string $identifier A unique identifier for the event.
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @property \Stripe\StripeObject $payload The payload of the event. This contains the fields corresponding to a meter's <code>customer_mapping.event_payload_key</code> (default is <code>stripe_customer_id</code>) and <code>value_settings.event_payload_key</code> (default is <code>value</code>). Read more about the <a href="https://stripe.com/docs/billing/subscriptions/usage-based/recording-usage#payload-key-overrides">payload</a>.
 * @property int $timestamp The timestamp passed in when creating the event. Measured in seconds since the Unix epoch.
 */
class MeterEvent extends \LSCP\Stripe\ApiResource
{
    const OBJECT_NAME = 'billing.meter_event';
    /**
     * Creates a billing meter event.
     *
     * @param null|array $params
     * @param null|array|string $options
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Billing\MeterEvent the created resource
     */
    public static function create($params = null, $options = null)
    {
        self::_validateParams($params);
        $url = static::classUrl();
        list($response, $opts) = static::_staticRequest('post', $url, $params, $options);
        $obj = \LSCP\Stripe\Util\Util::convertToStripeObject($response->json, $opts);
        $obj->setLastResponse($response);
        return $obj;
    }
}