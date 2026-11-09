<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Block\Adminhtml\Order;

use Envoice\Einvoice\Model\CheckoutRegNo;
use Envoice\Einvoice\Model\DocumentService;
use Envoice\Einvoice\Model\Documents;
use Envoice\Einvoice\Model\PayloadBuilder;
use Envoice\Einvoice\Model\Queue;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class Panel extends Template
{
    /**
     * @var Order|null
     */
    private ?Order $order = null;

    /**
     * @param Context $context
     * @param OrderRepositoryInterface $orders
     * @param Documents $documents
     * @param Queue $queue
     * @param DocumentService $service
     * @param PayloadBuilder $payload
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly OrderRepositoryInterface $orders,
        private readonly Documents $documents,
        private readonly Queue $queue,
        private readonly DocumentService $service,
        private readonly PayloadBuilder $payload,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return Order
     */
    public function getOrder(): Order
    {
        if ($this->order === null) {
            /** @var Order $order */
            $order = $this->orders->get((int) $this->getRequest()->getParam('order_id'));
            $this->order = $order;
        }
        return $this->order;
    }

    /**
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->service->tokenForStore((int) $this->getOrder()->getStoreId()) !== '';
    }

    /**
     * @return array envoice_document row, empty before anything was filed
     */
    public function getDocument(): array
    {
        return $this->documents->get((int) $this->getOrder()->getEntityId());
    }

    /**
     * @return array the buyer the next submission sends, to prefill the form
     */
    public function getBuyer(): array
    {
        return $this->payload->buyer($this->getOrder(), $this->getDocument()) ?? [];
    }

    /**
     * @return string the BRN or IC the buyer typed at checkout
     */
    public function getCheckoutRegNo(): string
    {
        return (string) $this->getOrder()->getData(CheckoutRegNo::FIELD);
    }

    /**
     * Whether the Cancel e-invoice action applies: open, credit_note (one is filed against the
     * e-invoice), expired (past LHDN's 72 hours), or null when the e-invoice is not validated. An
     * unknown validation time counts as open; envoice enforces the window either way.
     *
     * @return string|null
     */
    public function getCancelState(): ?string
    {
        $document = $this->getDocument();
        if (($document['status'] ?? '') !== 'VALIDATED') {
            return null;
        }
        foreach ($this->getCreditMemos() as $memo) {
            if (in_array($memo['status'], ['SUBMITTED', 'VALIDATED'], true)) {
                return 'credit_note';
            }
        }
        $validatedAt = (string) ($document['validated_at'] ?? '');
        if ($validatedAt !== '' && strtotime($validatedAt . ' UTC') < time() - 72 * 3600) {
            return 'expired';
        }
        return 'open';
    }

    /**
     * @return bool
     */
    public function isFiled(): bool
    {
        return in_array((string) ($this->getDocument()['status'] ?? ''), DocumentService::LIVE_STATUSES, true);
    }

    /**
     * @return array[] one row per credit memo with its credit note and job state
     */
    public function getCreditMemos(): array
    {
        $order = $this->getOrder();
        $notes = $this->documents->creditNotes((int) $order->getEntityId());
        $jobs = [];
        foreach ($this->queue->forOrder((int) $order->getEntityId()) as $job) {
            if ($job['kind'] === Queue::KIND_CREDITNOTE) {
                $jobs[(int) $job['creditmemo_id']] = $job;
            }
        }
        $rows = [];
        foreach ($order->getCreditmemosCollection() as $creditmemo) {
            $id = (int) $creditmemo->getEntityId();
            $rows[] = [
                'id' => $id,
                'number' => (string) $creditmemo->getIncrementId(),
                'amount' => $order->formatPriceTxt((float) $creditmemo->getGrandTotal()),
                'status' => (string) ($notes[$id]['status'] ?? ''),
                'uuid' => (string) ($notes[$id]['uuid'] ?? ''),
                'job' => $jobs[$id] ?? [],
            ];
        }
        return $rows;
    }

    /**
     * @return array the order's submit job, empty when none was queued
     */
    public function getSubmitJob(): array
    {
        foreach ($this->queue->forOrder((int) $this->getOrder()->getEntityId()) as $job) {
            if ($job['kind'] === Queue::KIND_SUBMIT) {
                return $job;
            }
        }
        return [];
    }

    /**
     * Open the buyer form when an unfiled order needs it: the last attempt failed on a buyer field,
     * or the order has a TIN but no ID number to pair with it.
     *
     * @return bool
     */
    public function isBuyerFormOpen(): bool
    {
        if ($this->isFiled()) {
            return false;
        }
        $job = $this->getSubmitJob();
        $error = $job && $job['status'] !== 'done' ? (string) $job['last_error'] : '';
        if ($error !== '' && preg_match('/(buyer|tin|brn|nric|passport|registration|id (number|type))/i', $error)) {
            return true;
        }
        $buyer = $this->getBuyer();
        return ($buyer['tin'] ?? '') !== '' && ($buyer['idValue'] ?? '') === '';
    }

    /**
     * @param string $action
     * @return string
     */
    public function getActionUrl(string $action): string
    {
        return $this->getUrl('envoice/order/' . $action, ['order_id' => (int) $this->getOrder()->getEntityId()]);
    }

    /**
     * @return string
     */
    public function getConfigUrl(): string
    {
        return $this->getUrl('adminhtml/system_config/edit', ['section' => 'envoice']);
    }

    /**
     * @return array<string, string>
     */
    public function getIdTypes(): array
    {
        return ['BRN' => 'BRN', 'NRIC' => 'NRIC', 'PASSPORT' => 'Passport', 'ARMY' => 'Army ID'];
    }
}
