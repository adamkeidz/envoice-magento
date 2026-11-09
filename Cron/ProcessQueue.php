<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Cron;

use Envoice\Einvoice\Model\QueueProcessor;

class ProcessQueue
{
    /**
     * @param QueueProcessor $processor
     */
    public function __construct(private readonly QueueProcessor $processor)
    {
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        $this->processor->processDue();
    }
}
