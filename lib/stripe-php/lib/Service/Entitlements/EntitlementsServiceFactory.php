<?php
namespace LSCP\Stripe\Service\Entitlements;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Service factory class for API resources in the Entitlements namespace.
 *
 * @property ActiveEntitlementService $activeEntitlements
 * @property FeatureService $features
 */
class EntitlementsServiceFactory extends \LSCP\Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = ['activeEntitlements' => ActiveEntitlementService::class, 'features' => FeatureService::class];
    protected function getServiceClass($name)
    {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}