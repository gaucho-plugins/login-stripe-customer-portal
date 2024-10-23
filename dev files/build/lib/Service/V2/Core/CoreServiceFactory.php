<?php
namespace LSCP\Stripe\Service\V2\Core;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Service factory class for API resources in the root namespace.
 * // Doc: The beginning of the section generated from our OpenAPI spec.
 *
 * @property EventService $events
 * // Doc: The end of the section generated from our OpenAPI spec
 */
class CoreServiceFactory extends \LSCP\Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = [
        // Class Map: The beginning of the section generated from our OpenAPI spec
        'events' => EventService::class,
    ];
    protected function getServiceClass($name)
    {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}