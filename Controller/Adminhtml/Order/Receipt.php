<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Controller\Adminhtml\Order;

use Envoice\Einvoice\Model\DocumentService;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class Receipt extends AbstractOrder implements HttpGetActionInterface
{
    /**
     * @param Context $context
     * @param DocumentService $service
     * @param OrderRepositoryInterface $orders
     * @param FileFactory $fileFactory
     */
    public function __construct(
        Context $context,
        private readonly DocumentService $service,
        private readonly OrderRepositoryInterface $orders,
        private readonly FileFactory $fileFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        /** @var Order $order */
        $order = $this->orders->get($this->orderId());
        $pdf = $this->service->receipt($order);
        if ($pdf === null) {
            $this->messageManager->addErrorMessage(__('The envoice receipt is not available yet.'));
            return $this->backToOrder();
        }
        return $this->fileFactory->create(
            'envoice-receipt-' . $order->getIncrementId() . '.pdf',
            $pdf,
            DirectoryList::VAR_DIR,
            'application/pdf'
        );
    }
}
