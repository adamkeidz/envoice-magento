<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Model;

use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Talks to envoice for one order: file the e-invoice, file credit notes, refresh statuses.
 * Mirrors the WooCommerce plugin's rules (Envoice_Hooks, Envoice_Orders).
 */
class DocumentService
{
    /** A document in one of these states is filed or recorded; submitting again would duplicate it. */
    public const LIVE_STATUSES = ['SUBMITTED', 'VALIDATED', 'RECORDED', 'CONSOLIDATED', 'NOT_FILED'];

    private const CREDITABLE_STATUSES = ['VALIDATED', 'RECORDED', 'CONSOLIDATED', 'NOT_FILED'];

    /**
     * @param OrderRepositoryInterface $orders
     * @param CreditmemoRepositoryInterface $creditmemos
     * @param Documents $documents
     * @param Connections $connections
     * @param StoreSites $sites
     * @param ApiClient $api
     * @param PayloadBuilder $payload
     * @param Config $config
     * @param Queue $queue
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly CreditmemoRepositoryInterface $creditmemos,
        private readonly Documents $documents,
        private readonly Connections $connections,
        private readonly StoreSites $sites,
        private readonly ApiClient $api,
        private readonly PayloadBuilder $payload,
        private readonly Config $config,
        private readonly Queue $queue
    ) {
    }

    /**
     * @param int $storeId
     * @return string empty when the store's URL is not paired
     */
    public function tokenForStore(int $storeId): string
    {
        return $this->connections->token($this->sites->forStore($storeId));
    }

    /**
     * Run one queue job.
     *
     * @param array $job envoice_queue row
     * @return string outcome note shown in the order panel
     * @throws JobFailure
     */
    public function runJob(array $job): string
    {
        /** @var Order $order */
        $order = $this->orders->get((int) $job['order_id']);
        if ($job['kind'] === Queue::KIND_CREDITNOTE) {
            return $this->fileCreditNote($order, (int) $job['creditmemo_id']);
        }
        return $this->submit($order, (string) $job['mode']);
    }

    /**
     * @param Order $order
     * @param string $mode auto, individual or consolidated
     * @return string
     * @throws JobFailure
     */
    private function submit(Order $order, string $mode): string
    {
        $orderId = (int) $order->getEntityId();
        $document = $this->documents->get($orderId);
        $current = (string) ($document['status'] ?? '');
        if (in_array($current, self::LIVE_STATUSES, true)) {
            return 'Already ' . $current . '.';
        }
        if ($mode === 'auto') {
            $decision = $this->autoDecision($order, $document);
            if (isset($decision['skip'])) {
                return 'Skipped: ' . $decision['skip'];
            }
            $mode = $decision['mode'];
        }
        $token = $this->requireToken($order);

        $body = ['order' => $this->payload->order($order)];
        if ($mode === 'consolidated') {
            $res = $this->api->post('/consolidate', $body, $token);
        } else {
            $buyer = $this->payload->buyer($order, $document);
            if ($buyer) {
                $body['buyer'] = $buyer;
            }
            $res = $this->api->post('/submit', $body, $token);
        }
        if (!$res['ok']) {
            throw new JobFailure($res['error']);
        }
        $status = (string) ($res['body']['status'] ?? '');
        if ($status === 'PAID') {
            $status = 'RECORDED';
        }
        $this->documents->save($orderId, [
            'sale_id' => (string) ($res['body']['saleId'] ?? ''),
            'status' => $status,
            'uuid' => (string) ($res['body']['lhdnUuid'] ?? ''),
            'qr_link' => (string) ($res['body']['lhdnQrLink'] ?? ''),
        ]);
        return $status;
    }

    /**
     * The WooCommerce plugin's auto-submit rules, evaluated when the job runs.
     *
     * @param Order $order
     * @param array $document
     * @return array{mode?: string, skip?: string}
     */
    private function autoDecision(Order $order, array $document): array
    {
        $storeId = (int) $order->getStoreId();
        if (!$this->config->isAutoSubmit($storeId)) {
            return ['skip' => 'auto-submit is off.'];
        }
        if (in_array($order->getState(), [Order::STATE_CANCELED, Order::STATE_CLOSED], true)) {
            return ['skip' => 'the order is ' . $order->getState() . '.'];
        }
        if ($this->config->skipNonMyr($storeId) && strtoupper((string) $order->getOrderCurrencyCode()) !== 'MYR') {
            return ['skip' => 'the order is not in MYR.'];
        }
        $min = $this->config->minTotal($storeId);
        if ($min !== null && (float) $order->getGrandTotal() < $min) {
            return ['skip' => 'the order total is below the minimum.'];
        }
        $mode = $this->config->autoMode($storeId);
        if ($mode === 'consolidated' && $this->payload->buyer($order, $document)) {
            $mode = 'individual';
        }
        return ['mode' => $mode];
    }

    /**
     * @param Order $order
     * @param int $creditmemoId
     * @return string
     * @throws JobFailure
     */
    private function fileCreditNote(Order $order, int $creditmemoId): string
    {
        $orderId = (int) $order->getEntityId();
        $existing = (string) ($this->documents->creditNote($creditmemoId)['status'] ?? '');
        if (in_array($existing, ['SUBMITTED', 'VALIDATED'], true)) {
            return 'Already ' . $existing . '.';
        }
        $token = $this->requireToken($order);
        $status = (string) ($this->documents->get($orderId)['status'] ?? '');
        if ($status === 'SUBMITTED') {
            $this->refreshSite($this->sites->forStore((int) $order->getStoreId()));
            $status = (string) ($this->documents->get($orderId)['status'] ?? '');
        }
        if ($status === '' && $this->queue->hasPendingSubmit($orderId)) {
            throw new JobFailure('Waiting for the order e-invoice to be filed first.');
        }
        if ($status === 'SUBMITTED') {
            throw new JobFailure('Waiting for LHDN to validate the order e-invoice.');
        }
        if (!in_array($status, self::CREDITABLE_STATUSES, true)) {
            return 'Skipped: the order has no e-invoice to credit.';
        }

        $creditmemo = $this->creditmemos->get($creditmemoId);
        $res = $this->api->post('/creditnote', [
            'orderId' => $orderId,
            'orderNumber' => (string) $order->getIncrementId(),
            'refunds' => [$this->payload->refund($creditmemo, $order)],
        ], $token);
        if (!$res['ok']) {
            throw new JobFailure($res['error']);
        }
        $note = 'Filed.';
        foreach ((array) ($res['body']['filed'] ?? []) as $cn) {
            if ((int) ($cn['refundId'] ?? 0) !== $creditmemoId) {
                continue;
            }
            $note = (string) ($cn['status'] ?? 'Filed.');
            $this->documents->saveCreditNote($creditmemoId, $orderId, [
                'sale_id' => (string) ($cn['saleId'] ?? ''),
                'status' => (string) ($cn['status'] ?? ''),
                'uuid' => (string) ($cn['lhdnUuid'] ?? ''),
            ]);
        }
        return $note;
    }

    /**
     * Pull envoice's latest history for one paired site into the local tables.
     *
     * @param string $siteUrl
     * @return int rows updated
     */
    public function refreshSite(string $siteUrl): int
    {
        $token = $this->connections->token($siteUrl);
        if ($token === '') {
            return 0;
        }
        $this->api->post('/refresh-status', [], $token);
        $res = $this->api->get('/history', $token);
        if (!$res['ok']) {
            return 0;
        }
        $updated = 0;
        foreach ((array) ($res['body']['history'] ?? []) as $row) {
            if (!empty($row['isCreditNote'])) {
                $creditmemoId = (int) ($row['refundId'] ?? 0);
                $cn = $creditmemoId > 0 ? $this->documents->creditNote($creditmemoId) : [];
                if (!$cn) {
                    continue;
                }
                $this->documents->saveCreditNote($creditmemoId, (int) $cn['order_id'], [
                    'sale_id' => (string) ($row['saleId'] ?? $cn['sale_id']),
                    'status' => (string) ($row['status'] ?? $cn['status']),
                    'uuid' => (string) ($row['lhdnUuid'] ?? ''),
                ]);
                $updated++;
                continue;
            }
            $orderId = (int) ($row['orderId'] ?? 0);
            $document = $orderId > 0 ? $this->documents->get($orderId) : [];
            if (!$document) {
                continue;
            }
            $rowSale = (string) ($row['saleId'] ?? '');
            if ($document['sale_id'] !== '' && $rowSale !== '' && $document['sale_id'] !== $rowSale) {
                continue;
            }
            $this->documents->save($orderId, [
                'status' => (string) ($row['status'] ?? $document['status']),
                'sale_id' => $rowSale !== '' ? $rowSale : $document['sale_id'],
                'uuid' => (string) ($row['lhdnUuid'] ?? ''),
                'qr_link' => (string) ($row['lhdnQrLink'] ?? ''),
                'validated_at' => self::utcDateTime((string) ($row['validatedAt'] ?? '')),
            ]);
            $updated++;
        }
        return $updated;
    }

    /**
     * @param int $storeId
     * @return int
     */
    public function refreshStore(int $storeId): int
    {
        return $this->refreshSite($this->sites->forStore($storeId));
    }

    /**
     * @param int $orderId
     * @param array $buyer tin, id_type, id_value, name
     * @return void
     */
    public function saveBuyer(int $orderId, array $buyer): void
    {
        $this->documents->save($orderId, [
            'buyer_tin' => mb_substr(trim((string) ($buyer['tin'] ?? '')), 0, 30),
            'buyer_id_type' => mb_substr(trim((string) ($buyer['id_type'] ?? '')), 0, 20),
            'buyer_id_value' => mb_substr(trim((string) ($buyer['id_value'] ?? '')), 0, 50),
            'buyer_name' => mb_substr(trim((string) ($buyer['name'] ?? '')), 0, 255),
        ]);
    }

    /**
     * LHDN Search TIN through envoice. envoice allows one lookup per shop every 5 seconds.
     *
     * @param Order $order
     * @param string $idType BRN, NRIC, PASSPORT or ARMY
     * @param string $idValue
     * @return string the TIN
     * @throws JobFailure with envoice's message when LHDN finds no TIN or the call fails
     */
    public function lookupTin(Order $order, string $idType, string $idValue): string
    {
        $res = $this->api->post('/lookup-tin', ['idType' => $idType, 'idValue' => $idValue], $this->requireToken($order));
        $tin = (string) ($res['body']['tin'] ?? '');
        if (!$res['ok'] || $tin === '') {
            throw new JobFailure($res['error'] ?: 'LHDN found no TIN for this ID.');
        }
        return $tin;
    }

    /**
     * Cancels the order's validated e-invoice on MyInvois. envoice enforces LHDN's 72-hour window
     * and refuses while a credit note filed against it is still active.
     *
     * @param Order $order
     * @param string $reason
     * @return void
     * @throws JobFailure with envoice's message
     */
    public function cancel(Order $order, string $reason): void
    {
        $orderId = (int) $order->getEntityId();
        $res = $this->api->post('/cancel', ['orderId' => $orderId, 'reason' => $reason], $this->requireToken($order));
        if (!$res['ok']) {
            throw new JobFailure($res['error']);
        }
        $this->documents->save($orderId, ['status' => 'CANCELLED']);
    }

    /**
     * @param Order $order
     * @return string|null receipt PDF bytes
     */
    public function receipt(Order $order): ?string
    {
        $saleId = (string) ($this->documents->get((int) $order->getEntityId())['sale_id'] ?? '');
        $token = $this->tokenForStore((int) $order->getStoreId());
        if ($saleId === '' || $token === '') {
            return null;
        }
        return $this->api->getRaw('/receipt?saleId=' . rawurlencode($saleId), $token);
    }

    /**
     * @param string $iso
     * @return string|null MySQL datetime in UTC
     */
    private static function utcDateTime(string $iso): ?string
    {
        if ($iso === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($iso))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param Order $order
     * @return string
     * @throws JobFailure
     */
    private function requireToken(Order $order): string
    {
        $token = $this->tokenForStore((int) $order->getStoreId());
        if ($token === '') {
            throw new JobFailure('This store URL is not connected to envoice.', true);
        }
        return $token;
    }
}
