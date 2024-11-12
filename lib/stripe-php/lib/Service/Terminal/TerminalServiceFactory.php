<?php
namespace LSCP\Stripe\Service\Terminal;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Service factory class for API resources in the Terminal namespace.
 *
 * @property ConfigurationService $configurations
 * @property ConnectionTokenService $connectionTokens
 * @property LocationService $locations
 * @property ReaderService $readers
 */
class TerminalServiceFactory extends \LSCP\Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = ['configurations' => ConfigurationService::class, 'connectionTokens' => ConnectionTokenService::class, 'locations' => LocationService::class, 'readers' => ReaderService::class];
    protected function getServiceClass($name)
    {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}