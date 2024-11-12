<?php
namespace LSCP\Stripe\Service\TestHelpers\Terminal;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Service factory class for API resources in the Terminal namespace.
 *
 * @property ReaderService $readers
 */
class TerminalServiceFactory extends \LSCP\Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = ['readers' => ReaderService::class];
    protected function getServiceClass($name)
    {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}