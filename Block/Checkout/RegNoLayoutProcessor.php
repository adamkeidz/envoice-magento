<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Block\Checkout;

use Envoice\Einvoice\Model\CheckoutRegNo;
use Envoice\Einvoice\Model\Config;
use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Adds the optional BRN or IC field above the payment methods for store views that show it.
 */
class RegNoLayoutProcessor implements LayoutProcessorInterface
{
    /**
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param array $jsLayout
     * @return array
     */
    public function process($jsLayout): array
    {
        if (!$this->config->showCheckoutRegNo((int) $this->storeManager->getStore()->getId())) {
            return $jsLayout;
        }
        $jsLayout['components']['checkout']['children']['steps']['children']['billing-step']['children']
            ['payment']['children']['beforeMethods']['children']['envoice-reg-no'] = [
                'component' => 'Envoice_Einvoice/js/view/reg-no',
                'provider' => 'checkoutProvider',
                'dataScope' => 'envoice.' . CheckoutRegNo::FIELD,
                'label' => __('Business registration no. (BRN) or IC for e-invoice'),
                'sortOrder' => 10,
                'validation' => [
                    'max_text_length' => CheckoutRegNo::MAX_LENGTH,
                    'pattern' => CheckoutRegNo::PATTERN,
                ],
            ];
        return $jsLayout;
    }
}
