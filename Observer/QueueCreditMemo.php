<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Observer;

use Envoice\Einvoice\Model\Config;
use Envoice\Einvoice\Model\DocumentService;
use Envoice\Einvoice\Model\Queue;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Psr\Log\LoggerInterface;

/**
 * Queues a credit note for a refunded credit memo. The job decides whether the order has an
 * e-invoice to credit, since the order's own submit job may still be in the queue.
 */
class QueueCreditMemo implements ObserverInterface
{
    /**
     * @param Config $config
     * @param DocumentService $service
     * @param Queue $queue
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly DocumentService $service,
        private readonly Queue $queue,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            /** @var Creditmemo $creditmemo */
            $creditmemo = $observer->getEvent()->getData('creditmemo');
            if (!$creditmemo || (int) $creditmemo->getState() !== Creditmemo::STATE_REFUNDED) {
                return;
            }
            $storeId = (int) $creditmemo->getStoreId();
            if (!$this->config->isAutoCreditNote($storeId) || $this->service->tokenForStore($storeId) === '') {
                return;
            }
            $this->queue->enqueue(
                Queue::KIND_CREDITNOTE,
                (int) $creditmemo->getOrderId(),
                (int) $creditmemo->getEntityId()
            );
        } catch (\Throwable $e) {
            $this->logger->error('envoice could not queue the credit memo: ' . $e->getMessage());
        }
    }
}
