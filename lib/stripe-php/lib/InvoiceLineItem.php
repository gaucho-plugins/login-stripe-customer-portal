<?php
namespace LSCP\Stripe;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Invoice Line Items represent the individual lines within an <a href="https://stripe.com/docs/api/invoices">invoice</a> and only exist within the context of an invoice.
 *
 * Each line item is backed by either an <a href="https://stripe.com/docs/api/invoiceitems">invoice item</a> or a <a href="https://stripe.com/docs/api/subscription_items">subscription item</a>.
 *
 * @property string $id Unique identifier for the object.
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property int $amount The amount, in cents (or local equivalent).
 * @property null|int $amount_excluding_tax The integer amount in cents (or local equivalent) representing the amount for this line item, excluding all tax and discounts.
 * @property string $currency Three-letter <a href="https://www.iso.org/iso-4217-currency-codes.html">ISO currency code</a>, in lowercase. Must be a <a href="https://stripe.com/docs/currencies">supported currency</a>.
 * @property null|string $description An arbitrary string attached to the object. Often useful for displaying to users.
 * @property null|\Stripe\StripeObject[] $discount_amounts The amount of discount calculated per discount for this line item.
 * @property bool $discountable If true, discounts will apply to this line item. Always false for prorations.
 * @property (string|\Stripe\Discount)[] $discounts The discounts applied to the invoice line item. Line item discounts are applied before invoice discounts. Use <code>expand[]=discounts</code> to expand each discount.
 * @property null|string $invoice The ID of the invoice that contains this line item.
 * @property null|string|\Stripe\InvoiceItem $invoice_item The ID of the <a href="https://stripe.com/docs/api/invoiceitems">invoice item</a> associated with this line item if any.
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @property \Stripe\StripeObject $metadata Set of <a href="https://stripe.com/docs/api/metadata">key-value pairs</a> that you can attach to an object. This can be useful for storing additional information about the object in a structured format. Note that for line items with <code>type=subscription</code>, <code>metadata</code> reflects the current metadata from the subscription associated with the line item, unless the invoice line was directly updated with different metadata after creation.
 * @property \Stripe\StripeObject $period
 * @property null|\Stripe\Plan $plan The plan of the subscription, if the line item is a subscription or a proration.
 * @property null|\Stripe\StripeObject[] $pretax_credit_amounts
 * @property null|\Stripe\Price $price The price of the line item.
 * @property bool $proration Whether this is a proration.
 * @property null|\Stripe\StripeObject $proration_details Additional details for proration line items
 * @property null|int $quantity The quantity of the subscription, if the line item is a subscription or a proration.
 * @property null|string|\Stripe\Subscription $subscription The subscription that the invoice item pertains to, if any.
 * @property null|string|\Stripe\SubscriptionItem $subscription_item The subscription item that generated this line item. Left empty if the line item is not an explicit result of a subscription.
 * @property \Stripe\StripeObject[] $tax_amounts The amount of tax calculated per tax rate for this line item
 * @property \Stripe\TaxRate[] $tax_rates The tax rates which apply to the line item.
 * @property string $type A string identifying the type of the source of this line item, either an <code>invoiceitem</code> or a <code>subscription</code>.
 * @property null|string $unit_amount_excluding_tax The amount in cents (or local equivalent) representing the unit amount for this line item, excluding all tax and discounts.
 */
class InvoiceLineItem extends ApiResource
{
    const OBJECT_NAME = 'line_item';
    use ApiOperations\Update;
    /**
     * Updates an invoice’s line item. Some fields, such as <code>tax_amounts</code>,
     * only live on the invoice line item, so they can only be updated through this
     * endpoint. Other fields, such as <code>amount</code>, live on both the invoice
     * item and the invoice line item, so updates on this endpoint will propagate to
     * the invoice item as well. Updating an invoice’s line item is only possible
     * before the invoice is finalized.
     *
     * @param string $id the ID of the resource to update
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \Stripe\InvoiceLineItem the updated resource
     */
    public static function update($id, $params = null, $opts = null)
    {
        self::_validateParams($params);
        $url = static::resourceUrl($id);
        list($response, $opts) = static::_staticRequest('post', $url, $params, $opts);
        $obj = \LSCP\Stripe\Util\Util::convertToStripeObject($response->json, $opts);
        $obj->setLastResponse($response);
        return $obj;
    }
}