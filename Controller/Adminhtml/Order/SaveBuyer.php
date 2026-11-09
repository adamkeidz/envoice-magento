<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Controller\Adminhtml\Order;

use Envoice\Einvoice\Model\DocumentService;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;

class SaveBuyer extends AbstractOrder implements HttpPostActionInterface
{
    /**
     * @param Context $context
     * @param DocumentService $service
     */
    public function __construct(Context $context, private readonly DocumentService $service)
    {
        parent::__construct($context);
    }

    /**
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $request = $this->getRequest();
        $this->service->saveBuyer($this->orderId(), [
            'tin' => (string) $request->getParam('tin'),
            'id_type' => (string) $request->getParam('id_type'),
            'id_value' => (string) $request->getParam('id_value'),
            'name' => (string) $request->getParam('name'),
        ]);
        $this->messageManager->addSuccessMessage(__('Buyer details saved for the e-invoice.'));
        return $this->backToOrder();
    }
}
