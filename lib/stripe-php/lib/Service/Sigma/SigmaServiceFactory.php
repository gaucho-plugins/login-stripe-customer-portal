<?php
namespace LSCP\Stripe\Service\Sigma;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Service factory class for API resources in the Sigma namespace.
 *
 * @property ScheduledQueryRunService $scheduledQueryRuns
 */
class SigmaServiceFactory extends \LSCP\Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = ['scheduledQueryRuns' => ScheduledQueryRunService::class];
    protected function getServiceClass($name)
    {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}