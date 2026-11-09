<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Model;

use Psr\Log\LoggerInterface;

class QueueProcessor
{
    private const BATCH = 50;

    /**
     * @param Queue $queue
     * @param DocumentService $service
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Queue $queue,
        private readonly DocumentService $service,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return int jobs run
     */
    public function processDue(): int
    {
        $ran = 0;
        foreach ($this->queue->due(self::BATCH) as $job) {
            $ran += $this->run((int) $job['job_id']) ? 1 : 0;
        }
        return $ran;
    }

    /**
     * Run one job now, for the admin Submit and Retry buttons.
     *
     * @param int $jobId
     * @return array envoice_queue row after the run
     */
    public function processNow(int $jobId): array
    {
        $this->run($jobId);
        return $this->queue->get($jobId);
    }

    /**
     * @param int $jobId
     * @return bool false when another worker holds the job
     */
    private function run(int $jobId): bool
    {
        if (!$this->queue->lease($jobId)) {
            return false;
        }
        $job = $this->queue->get($jobId);
        try {
            $this->queue->done($jobId, $this->service->runJob($job));
        } catch (JobFailure $e) {
            if ($e->isPermanent()) {
                $this->queue->fail($jobId, $e->getMessage());
            } else {
                $this->queue->retryLater($job, $e->getMessage());
            }
        } catch (\Throwable $e) {
            $this->logger->error('envoice job ' . $jobId . ' failed: ' . $e->getMessage());
            $this->queue->retryLater($job, $e->getMessage());
        }
        return true;
    }
}
