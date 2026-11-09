<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * Local copy of each order's e-invoice and each credit memo's credit note, as envoice last
 * reported them. Credit memos are real Magento entities, so their ids are the refund ids
 * envoice keys credit notes on.
 */
class Documents
{
    private const DOCUMENT_COLUMNS = [
        'sale_id', 'status', 'uuid', 'qr_link', 'buyer_tin', 'buyer_id_type', 'buyer_id_value', 'buyer_name',
        'validated_at',
    ];

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * @param int $orderId
     * @return array empty when the order has no row yet
     */
    public function get(int $orderId): array
    {
        $db = $this->db();
        $row = $db->fetchRow(
            $db->select()->from($this->resource->getTableName('envoice_document'))->where('order_id = ?', $orderId)
        );
        return is_array($row) ? $row : [];
    }

    /**
     * Upsert only the given columns.
     *
     * @param int $orderId
     * @param array $data
     * @return void
     */
    public function save(int $orderId, array $data): void
    {
        $data = array_intersect_key($data, array_flip(self::DOCUMENT_COLUMNS));
        if (!$data) {
            return;
        }
        $this->db()->insertOnDuplicate(
            $this->resource->getTableName('envoice_document'),
            ['order_id' => $orderId] + $data,
            array_keys($data)
        );
    }

    /**
     * @param int $orderId
     * @return array<int, array> keyed by creditmemo_id
     */
    public function creditNotes(int $orderId): array
    {
        $db = $this->db();
        return $db->fetchAssoc(
            $db->select()->from($this->resource->getTableName('envoice_credit_note'))->where('order_id = ?', $orderId)
        );
    }

    /**
     * @param int $creditmemoId
     * @return array
     */
    public function creditNote(int $creditmemoId): array
    {
        $db = $this->db();
        $row = $db->fetchRow(
            $db->select()->from($this->resource->getTableName('envoice_credit_note'))
                ->where('creditmemo_id = ?', $creditmemoId)
        );
        return is_array($row) ? $row : [];
    }

    /**
     * @param int $creditmemoId
     * @param int $orderId
     * @param array $data sale_id, status, uuid
     * @return void
     */
    public function saveCreditNote(int $creditmemoId, int $orderId, array $data): void
    {
        $data = array_intersect_key($data, array_flip(['sale_id', 'status', 'uuid']));
        $this->db()->insertOnDuplicate(
            $this->resource->getTableName('envoice_credit_note'),
            ['creditmemo_id' => $creditmemoId, 'order_id' => $orderId] + $data,
            array_keys($data) ?: ['order_id']
        );
    }

    /**
     * @return bool whether any e-invoice or credit note still waits on LHDN validation
     */
    public function hasSubmitted(): bool
    {
        $db = $this->db();
        foreach (['envoice_document', 'envoice_credit_note'] as $table) {
            $hit = $db->fetchOne(
                $db->select()->from($this->resource->getTableName($table), ['status'])
                    ->where('status = ?', 'SUBMITTED')->limit(1)
            );
            if ($hit) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return AdapterInterface
     */
    private function db(): AdapterInterface
    {
        return $this->resource->getConnection('sales');
    }
}
