<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Controller\Adminhtml\Order;

use Envoice\Einvoice\Model\AdminScope;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;

abstract class AbstractOrder extends Action
{
    public const ADMIN_RESOURCE = 'Envoice_Einvoice::documents';

    /**
     * @param Context $context
     * @param AdminScope $scope
     */
    public function __construct(Context $context, private readonly AdminScope $scope)
    {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    protected function _isAllowed()
    {
        return parent::_isAllowed() && $this->scope->allowsOrder($this->orderId());
    }

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
