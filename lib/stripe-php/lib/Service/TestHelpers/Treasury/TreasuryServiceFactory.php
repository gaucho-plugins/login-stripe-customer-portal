<?php
namespace LSCP\Stripe\Service\TestHelpers\Treasury;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Service factory class for API resources in the Treasury namespace.
 *
 * @property InboundTransferService $inboundTransfers
 * @property OutboundPaymentService $outboundPayments
 * @property OutboundTransferService $outboundTransfers
 * @property ReceivedCreditService $receivedCredits
 * @property ReceivedDebitService $receivedDebits
 */
class TreasuryServiceFactory extends \LSCP\Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = ['inboundTransfers' => InboundTransferService::class, 'outboundPayments' => OutboundPaymentService::class, 'outboundTransfers' => OutboundTransferService::class, 'receivedCredits' => ReceivedCreditService::class, 'receivedDebits' => ReceivedDebitService::class];
    protected function getServiceClass($name)
    {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}