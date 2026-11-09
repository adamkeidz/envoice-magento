<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Model;

use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;

/**
 * Builds the order payload envoice validates (WooOrderPayload in envoice's lib/woo/types.ts).
 * Amounts are in the order currency. Line prices are per unit, after discount, tax exclusive.
 */
class PayloadBuilder
{
    /**
     * @param Config $config
     */
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @param Order $order
     * @return array
     */
    public function order(Order $order): array
    {
        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            /** @var Item $item */
            $lines = $item->getHasChildren() && $item->isChildrenCalculated() ? $item->getChildrenItems() : [$item];
            foreach ($lines as $line) {
                $qty = (float) $line->getQtyOrdered();
                if ($qty <= 0) {
                    continue;
                }
                $net = (float) $line->getRowTotal() - (float) $line->getDiscountAmount();
                $items[] = [
                    'name' => mb_substr((string) $line->getName(), 0, 300),
                    'quantity' => $qty,
                    'unitPrice' => round(max(0.0, $net) / $qty, 4),
                    'taxRate' => round((float) $line->getTaxPercent(), 2),
                ];
            }
        }

        $shipping = (float) $order->getShippingAmount() - (float) $order->getShippingDiscountAmount();
        if ($shipping > 0) {
            $items[] = [
                'name' => mb_substr((string) ($order->getShippingDescription() ?: 'Shipping'), 0, 300),
                'quantity' => 1,
                'unitPrice' => round($shipping, 4),
                'taxRate' => round((float) $order->getShippingTaxAmount() / (float) $order->getShippingAmount() * 100, 2),
            ];
        }

        if (!$items) {
            $items[] = ['name' => 'Order', 'quantity' => 1, 'unitPrice' => round((float) $order->getGrandTotal(), 4), 'taxRate' => 0];
        }

        $billing = $order->getBillingAddress();
        $street = $billing ? (array) $billing->getStreet() : [];
        return [
            'id' => (int) $order->getEntityId(),
            'number' => (string) $order->getIncrementId(),
            'status' => self::statusSlug((string) $order->getState()),
            'currency' => (string) $order->getOrderCurrencyCode(),
            'total' => round((float) $order->getGrandTotal(), 2),
            'createdAt' => self::isoDate((string) $order->getCreatedAt()),
            'datePaid' => null,
            'email' => (string) $order->getCustomerEmail(),
            'phone' => $billing ? (string) $billing->getTelephone() : '',
            'billing' => [
                'name' => $billing ? trim($billing->getFirstname() . ' ' . $billing->getLastname()) : '',
                'address1' => (string) array_shift($street),
                'address2' => implode(' ', $street),
                'city' => $billing ? (string) $billing->getCity() : '',
                'postcode' => $billing ? (string) $billing->getPostcode() : '',
                'state' => $billing ? (string) $billing->getRegion() : '',
                'country' => $billing ? (string) $billing->getCountryId() : '',
            ],
            'items' => $items,
        ];
    }

    /**
     * Buyer identity. Each field from the admin Buyer details form wins; the TIN falls back to the
     * checkout VAT number, the ID number to the checkout BRN or IC field and the name to the billing
     * company.
     *
     * @param Order $order
     * @param array $document envoice_document row
     * @return array|null
     */
    public function buyer(Order $order, array $document): ?array
    {
        $billing = $order->getBillingAddress();
        $vat = $billing && $this->config->vatAsTin((int) $order->getStoreId()) ? trim((string) $billing->getVatId()) : '';
        $tin = (string) ($document['buyer_tin'] ?? '') ?: mb_substr($vat, 0, 30);
        $idValue = (string) ($document['buyer_id_value'] ?? '') ?: (string) $order->getData(CheckoutRegNo::FIELD);
        if ($tin === '' && $idValue === '') {
            return null;
        }
        return array_filter([
            'tin' => $tin,
            'idType' => $idValue !== '' ? (string) ($document['buyer_id_type'] ?? '') : '',
            'idValue' => $idValue,
            'name' => (string) ($document['buyer_name'] ?? '') ?: ($billing ? (string) $billing->getCompany() : ''),
        ]);
    }

    /**
     * @param CreditmemoInterface $creditmemo
     * @param Order $order
     * @return array
     */
    public function refund(CreditmemoInterface $creditmemo, Order $order): array
    {
        return [
            'id' => (int) $creditmemo->getEntityId(),
            'amount' => abs((float) $creditmemo->getGrandTotal()),
            'currency' => (string) $order->getOrderCurrencyCode(),
            'createdAt' => self::isoDate((string) $creditmemo->getCreatedAt()),
            'reason' => '',
        ];
    }

    /**
     * Magento order state to the WooCommerce-style status slug envoice checks before filing.
     *
     * @param string $state
     * @return string
     */
    public static function statusSlug(string $state): string
    {
        return match ($state) {
            Order::STATE_PROCESSING => 'processing',
            Order::STATE_COMPLETE => 'completed',
            Order::STATE_CANCELED => 'cancelled',
            Order::STATE_CLOSED => 'refunded',
            Order::STATE_HOLDED => 'on-hold',
            default => 'pending',
        };
    }

    /**
     * @param string $utc Magento stores timestamps in UTC
     * @return string|null
     */
    private static function isoDate(string $utc): ?string
    {
        if ($utc === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->format(DATE_ATOM);
        } catch (\Exception $e) {
            return null;
        }
    }
}
