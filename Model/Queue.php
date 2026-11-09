<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\DuplicateException;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Work queue drained by cron. One row per (kind, order, credit memo), so an event that fires
 * twice enqueues once. A worker leases a row by pushing next_run_at past the lease window, which
 * makes a crashed run retry on its own once the lease lapses.
 */
class Queue
{
    public const KIND_SUBMIT = 'submit';
    public const KIND_CREDITNOTE = 'creditnote';

    private const LEASE_SECONDS = 600;
    private const BACKOFF_MINUTES = [1, 5, 15, 60, 180, 360, 720, 1440];

    /**
     * @param ResourceConnection $resource
     * @param DateTime $dateTime
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * Add a job unless one exists. With $reset, an existing job is put back to pending now.
     *
     * @param string $kind
     * @param int $orderId
     * @param int $creditmemoId
     * @param string $mode
     * @param bool $reset
     * @return int job id
     */
    public function enqueue(string $kind, int $orderId, int $creditmemoId = 0, string $mode = 'auto', bool $reset = false): int
    {
        $db = $this->db();
        $existing = $this->find($kind, $orderId, $creditmemoId);
        if (!$existing) {
            try {
                $db->insert($this->table(), [
                    'kind' => $kind,
                    'mode' => $mode,
                    'order_id' => $orderId,
                    'creditmemo_id' => $creditmemoId,
                    'status' => 'pending',
                    'attempts' => 0,
                    'next_run_at' => $this->now(),
                ]);
                return (int) $db->lastInsertId($this->table());
            } catch (DuplicateException $e) {
                $existing = $this->find($kind, $orderId, $creditmemoId);
            }
        }
        if ($reset) {
            $db->update(
                $this->table(),
                ['mode' => $mode, 'status' => 'pending', 'attempts' => 0, 'next_run_at' => $this->now(), 'last_error' => null],
                ['job_id = ?' => (int) $existing['job_id']]
            );
        }
        return (int) $existing['job_id'];
    }

    /**
     * @param int $limit
     * @return array[] due jobs, oldest first so an order's e-invoice runs before its credit notes
     */
    public function due(int $limit): array
    {
        $db = $this->db();
        return $db->fetchAll(
            $db->select()->from($this->table())
                ->where('status = ?', 'pending')
                ->where('next_run_at <= ?', $this->now())
                ->order('job_id ASC')
                ->limit($limit)
        );
    }

    /**
     * Claim a due job for this worker. False when another worker already holds it.
     *
     * @param int $jobId
     * @return bool
     */
    public function lease(int $jobId): bool
    {
        return $this->db()->update(
            $this->table(),
            [
                'attempts' => new \Zend_Db_Expr('attempts + 1'),
                'next_run_at' => $this->now(self::LEASE_SECONDS),
            ],
            ['job_id = ?' => $jobId, 'status = ?' => 'pending', 'next_run_at <= ?' => $this->now()]
        ) === 1;
    }

    /**
     * @param int $jobId
     * @param string $note
     * @return void
     */
    public function done(int $jobId, string $note): void
    {
        $this->db()->update($this->table(), ['status' => 'done', 'last_error' => $note ?: null], ['job_id = ?' => $jobId]);
    }

    /**
     * @param int $jobId
     * @param string $error
     * @return void
     */
    public function fail(int $jobId, string $error): void
    {
        $this->db()->update($this->table(), ['status' => 'failed', 'last_error' => $error], ['job_id = ?' => $jobId]);
    }

    /**
     * Schedule the next attempt, or give up once the backoff ladder is used up.
     *
     * @param array $job the row as leased (attempts already counts this run)
     * @param string $error
     * @return void
     */
    public function retryLater(array $job, string $error): void
    {
        $attempts = (int) $job['attempts'];
        if ($attempts >= count(self::BACKOFF_MINUTES)) {
            $this->fail((int) $job['job_id'], $error);
            return;
        }
        $this->db()->update(
            $this->table(),
            ['next_run_at' => $this->now(self::BACKOFF_MINUTES[$attempts] * 60), 'last_error' => $error],
            ['job_id = ?' => (int) $job['job_id']]
        );
    }

    /**
     * @param int $jobId
     * @return array
     */
    public function get(int $jobId): array
    {
        $db = $this->db();
        $row = $db->fetchRow($db->select()->from($this->table())->where('job_id = ?', $jobId));
        return is_array($row) ? $row : [];
    }

    /**
     * @param int $orderId
     * @return array[]
     */
    public function forOrder(int $orderId): array
    {
        $db = $this->db();
        return $db->fetchAll($db->select()->from($this->table())->where('order_id = ?', $orderId)->order('job_id ASC'));
    }

    /**
     * @param int $orderId
     * @return bool
     */
    public function hasPendingSubmit(int $orderId): bool
    {
        $job = $this->find(self::KIND_SUBMIT, $orderId, 0);
        return $job && $job['status'] === 'pending';
    }

    /**
     * @param string $kind
     * @param int $orderId
     * @param int $creditmemoId
     * @return array
     */
    private function find(string $kind, int $orderId, int $creditmemoId): array
    {
        $db = $this->db();
        $row = $db->fetchRow(
            $db->select()->from($this->table())
                ->where('kind = ?', $kind)
                ->where('order_id = ?', $orderId)
                ->where('creditmemo_id = ?', $creditmemoId)
        );
        return is_array($row) ? $row : [];
    }

    /**
     * @param int $offsetSeconds
     * @return string UTC timestamp
     */
    private function now(int $offsetSeconds = 0): string
    {
        return $this->dateTime->gmtDate('Y-m-d H:i:s', $this->dateTime->gmtTimestamp() + $offsetSeconds);
    }

    /**
     * @return string
     */
    private function table(): string
    {
        return $this->resource->getTableName('envoice_queue');
    }

    /**
     * @return AdapterInterface
     */
    private function db(): AdapterInterface
    {
        return $this->resource->getConnection('sales');
    }
}
