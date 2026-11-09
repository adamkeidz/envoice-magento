<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Controller\Adminhtml\Order;

use Envoice\Einvoice\Model\DocumentService;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class Refresh extends AbstractOrder implements HttpPostActionInterface
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
        $order = $this->orders->get($this->orderId());
        $updated = $this->service->refreshStore((int) $order->getStoreId());
        $this->messageManager->addSuccessMessage(__('envoice statuses refreshed (%1 updated).', $updated));
        return $this->backToOrder();
    }
}
