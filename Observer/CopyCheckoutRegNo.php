<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Observer;

use Envoice\Einvoice\Model\CheckoutRegNo;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CopyCheckoutRegNo implements ObserverInterface
{
    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $value = (string) $observer->getEvent()->getQuote()->getData(CheckoutRegNo::FIELD);
        if ($value !== '') {
            $observer->getEvent()->getOrder()->setData(CheckoutRegNo::FIELD, $value);
        }
    }
}
