<?php
namespace LSCP\Stripe\Service\Billing;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * @phpstan-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 */
class CreditBalanceSummaryService extends \LSCP\Stripe\Service\AbstractService
{
    /**
     * Retrieves the credit balance summary for a customer.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\Billing\CreditBalanceSummary
     */
    public function retrieve($params = null, $opts = null)
    {
        return $this->request('get', '/v1/billing/credit_balance_summary', $params, $opts);
    }
}