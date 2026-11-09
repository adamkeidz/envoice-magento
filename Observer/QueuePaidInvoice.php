<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Observer;

use Envoice\Einvoice\Model\Config;
use Envoice\Einvoice\Model\DocumentService;
use Envoice\Einvoice\Model\Queue;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Invoice;
use Psr\Log\LoggerInterface;

/**
 * Queues auto-submit once an invoice is paid. Runs after the save commits and only writes a
 * queue row, so checkout and the admin never wait on envoice.
 */
class QueuePaidInvoice implements ObserverInterface
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
            /** @var Invoice $invoice */
            $invoice = $observer->getEvent()->getData('invoice');
            if (!$invoice || (int) $invoice->getState() !== Invoice::STATE_PAID) {
                return;
            }
            $order = $invoice->getOrder();
            $storeId = (int) $order->getStoreId();
            if (!$this->config->isAutoSubmit($storeId) || $this->service->tokenForStore($storeId) === '') {
                return;
            }
            $this->queue->enqueue(Queue::KIND_SUBMIT, (int) $order->getEntityId());
        } catch (\Throwable $e) {
            $this->logger->error('envoice could not queue the paid invoice: ' . $e->getMessage());
        }
    }
}
