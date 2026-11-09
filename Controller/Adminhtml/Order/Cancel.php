<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Controller\Adminhtml\Order;

use Envoice\Einvoice\Model\DocumentService;
use Envoice\Einvoice\Model\JobFailure;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class Cancel extends AbstractOrder implements HttpPostActionInterface
{
    /**
     * @param Context $context
     * @param DocumentService $service
     * @param OrderRepositoryInterface $orders
     */
    public function __construct(
        Context $context,
        private readonly DocumentService $service,
        private readonly OrderRepositoryInterface $orders
    ) {
        parent::__construct($context);
    }

    /**
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $reason = trim((string) $this->getRequest()->getParam('reason'));
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 300) {
            $this->messageManager->addErrorMessage(__('Give a cancellation reason of 5 to 300 characters.'));
            return $this->backToOrder();
        }
        /** @var Order $order */
        $order = $this->orders->get($this->orderId());
        try {
            $this->service->cancel($order, $reason);
        } catch (JobFailure $e) {
            $this->messageManager->addErrorMessage(__('Cancel e-invoice: %1', $e->getMessage()));
            return $this->backToOrder();
        }
        $this->messageManager->addSuccessMessage(__('e-Invoice cancelled on MyInvois.'));
        return $this->backToOrder();
    }
}
