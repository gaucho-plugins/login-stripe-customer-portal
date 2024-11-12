<?php
namespace LSCP\Stripe\Service\Reporting;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Service factory class for API resources in the Reporting namespace.
 *
 * @property ReportRunService $reportRuns
 * @property ReportTypeService $reportTypes
 */
class ReportingServiceFactory extends \LSCP\Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = ['reportRuns' => ReportRunService::class, 'reportTypes' => ReportTypeService::class];
    protected function getServiceClass($name)
    {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}