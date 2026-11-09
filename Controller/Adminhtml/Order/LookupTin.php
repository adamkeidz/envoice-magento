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

/**
 * Looks up the TIN for the ID in the buyer form and saves the form with that TIN, so the next
 * Submit or Retry sends it.
 */
class LookupTin extends AbstractOrder implements HttpPostActionInterface
{
    private const ID_TYPES = ['BRN', 'NRIC', 'PASSPORT', 'ARMY'];

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
        $request = $this->getRequest();
        $idType = strtoupper((string) $request->getParam('id_type'));
        $idType = in_array($idType, self::ID_TYPES, true) ? $idType : 'BRN';
        $idValue = trim((string) $request->getParam('id_value'));
        if ($idValue === '') {
            $this->messageManager->addErrorMessage(__('Enter an ID number first.'));
            return $this->backToOrder();
        }
        /** @var Order $order */
        $order = $this->orders->get($this->orderId());
        try {
            $tin = $this->service->lookupTin($order, $idType, $idValue);
        } catch (JobFailure $e) {
            $this->messageManager->addErrorMessage(__('TIN lookup: %1', $e->getMessage()));
            return $this->backToOrder();
        }
        $this->service->saveBuyer($this->orderId(), [
            'tin' => $tin,
            'id_type' => $idType,
            'id_value' => $idValue,
            'name' => (string) $request->getParam('name'),
        ]);
        $this->messageManager->addSuccessMessage(__('Found TIN %1 and saved it for the e-invoice.', $tin));
        return $this->backToOrder();
    }
}
