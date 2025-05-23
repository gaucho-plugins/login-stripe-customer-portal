<?php
namespace LSCP\Stripe\Service\Tax;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Service factory class for API resources in the Tax namespace.
 *
 * @property CalculationService $calculations
 * @property RegistrationService $registrations
 * @property SettingsService $settings
 * @property TransactionService $transactions
 */
class TaxServiceFactory extends \LSCP\Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = ['calculations' => CalculationService::class, 'registrations' => RegistrationService::class, 'settings' => SettingsService::class, 'transactions' => TransactionService::class];
    protected function getServiceClass($name)
    {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}