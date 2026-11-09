<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Controller\Adminhtml\Order;

use Envoice\Einvoice\Model\Queue;
use Envoice\Einvoice\Model\QueueProcessor;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;

/**
 * Submit, Add to consolidated, File credit note and Retry. Each goes through the same queue row
 * the observers use, then runs it at once so the admin sees the outcome.
 */
class Run extends AbstractOrder implements HttpPostActionInterface
{
    /**
     * @param Context $context
     * @param Queue $queue
     * @param QueueProcessor $processor
     */
    public function __construct(
        Context $context,
        private readonly Queue $queue,
        private readonly QueueProcessor $processor
    ) {
        parent::__construct($context);
    }

    /**
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $request = $this->getRequest();
        $kind = $request->getParam('kind') === Queue::KIND_CREDITNOTE ? Queue::KIND_CREDITNOTE : Queue::KIND_SUBMIT;
        $mode = (string) $request->getParam('mode');
        if (!in_array($mode, ['individual', 'consolidated', 'auto'], true)) {
            $mode = 'individual';
        }
        $creditmemoId = $kind === Queue::KIND_CREDITNOTE ? (int) $request->getParam('creditmemo_id') : 0;
        $jobId = $this->queue->enqueue($kind, $this->orderId(), $creditmemoId, $mode, true);
        $job = $this->processor->processNow($jobId);
        $note = (string) ($job['last_error'] ?? '');
        if (($job['status'] ?? '') === 'done') {
            $this->messageManager->addSuccessMessage(__('envoice: %1', $note !== '' ? $note : __('Done.')));
        } elseif (($job['status'] ?? '') === 'failed') {
            $this->messageManager->addErrorMessage(__('envoice: %1', $note));
        } else {
            $this->messageManager->addWarningMessage(__('envoice: %1 It will retry automatically.', $note));
        }
        return $this->backToOrder();
    }
}
