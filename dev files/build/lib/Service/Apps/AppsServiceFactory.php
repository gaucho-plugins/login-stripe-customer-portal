<?php
namespace LSCP\Stripe\Service\Apps;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Service factory class for API resources in the Apps namespace.
 *
 * @property SecretService $secrets
 */
class AppsServiceFactory extends \LSCP\Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = ['secrets' => SecretService::class];
    protected function getServiceClass($name)
    {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}