<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Plugin;

use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;

/**
 * Guest checkout saves the payment through its own service, not the logged-in one.
 */
class SaveGuestCheckoutRegNo
{
    /**
     * @param SaveCheckoutRegNo $save
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIds
     */
    public function __construct(
        private readonly SaveCheckoutRegNo $save,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteIds
    ) {
    }

    /**
     * @param GuestPaymentInformationManagementInterface $subject
     * @param string $cartId
     * @param string $email
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSavePaymentInformation(
        GuestPaymentInformationManagementInterface $subject,
        $cartId,
        $email,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ) {
        $this->save->toQuote($this->maskedQuoteIds->execute((string) $cartId), $paymentMethod);
        return null;
    }
}
