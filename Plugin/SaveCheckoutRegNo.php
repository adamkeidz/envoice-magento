<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Plugin;

use Envoice\Einvoice\Model\CheckoutRegNo;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;

/**
 * Copies the checkout field from the payment extension attributes onto the quote.
 */
class SaveCheckoutRegNo
{
    /**
     * @param CartRepositoryInterface $carts
     */
    public function __construct(private readonly CartRepositoryInterface $carts)
    {
    }

    /**
     * @param PaymentInformationManagementInterface $subject
     * @param int $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSavePaymentInformation(
        PaymentInformationManagementInterface $subject,
        $cartId,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ) {
        $this->toQuote((int) $cartId, $paymentMethod);
        return null;
    }

    /**
     * Sets the value on the cart repository's cached quote, which the core flow saves right after.
     *
     * @param int $quoteId
     * @param PaymentInterface $paymentMethod
     * @return void
     */
    public function toQuote(int $quoteId, PaymentInterface $paymentMethod): void
    {
        $attributes = $paymentMethod->getExtensionAttributes();
        if ($attributes === null) {
            return;
        }
        $value = CheckoutRegNo::normalize($attributes->getEnvoiceBuyerRegNo());
        $this->carts->getActive($quoteId)->setData(CheckoutRegNo::FIELD, $value !== '' ? $value : null);
    }
}
