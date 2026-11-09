<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Cron;

use Envoice\Einvoice\Model\Connections;
use Envoice\Einvoice\Model\DocumentService;
use Envoice\Einvoice\Model\Documents;

/**
 * LHDN validates a submitted document within minutes; pick up the result so the order panel
 * and waiting credit note jobs see VALIDATED without anyone pressing Refresh.
 */
class RefreshStatuses
{
    /**
     * @param Documents $documents
     * @param Connections $connections
     * @param DocumentService $service
     */
    public function __construct(
        private readonly Documents $documents,
        private readonly Connections $connections,
        private readonly DocumentService $service
    ) {
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        if (!$this->documents->hasSubmitted()) {
            return;
        }
        foreach ($this->connections->pairedSites() as $siteUrl) {
            $this->service->refreshSite($siteUrl);
        }
    }
}
