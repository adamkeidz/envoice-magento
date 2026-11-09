<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;

abstract class AbstractOrder extends Action
{
    public const ADMIN_RESOURCE = 'Envoice_Einvoice::documents';

    /**
     * @return int
     */
    protected function orderId(): int
    {
        return (int) $this->getRequest()->getParam('order_id');
    }

    /**
     * @return Redirect
     */
    protected function backToOrder(): Redirect
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $redirect->setPath('sales/order/view', ['order_id' => $this->orderId()]);
    }
}
