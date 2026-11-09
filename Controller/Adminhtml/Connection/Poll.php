<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Controller\Adminhtml\Connection;

use Envoice\Einvoice\Model\ApiClient;
use Envoice\Einvoice\Model\Connections;
use Envoice\Einvoice\Model\StoreSites;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;

/**
 * Pairing step 2, polled while the popup is open. envoice hands the per-store token over
 * exactly once, to the holder of the claim code.
 */
class Poll extends AbstractConnection
{
    /**
     * @param Context $context
     * @param StoreSites $sites
     * @param Connections $connections
     * @param ApiClient $api
     */
    public function __construct(
        Context $context,
        StoreSites $sites,
        private readonly Connections $connections,
        private readonly ApiClient $api
    ) {
        parent::__construct($context, $sites);
    }

    /**
     * @return Json
     */
    public function execute(): Json
    {
        $site = $this->requestedSite();
        $claim = $site !== null ? $this->connections->claim($site) : '';
        if ($claim === '') {
            return $this->json(['ok' => false, 'message' => (string) __('Start Connect again.')]);
        }
        $res = $this->api->post('/claim-status', ['siteUrl' => $site, 'claimToken' => $claim]);
        if (!$res['ok']) {
            return $this->json(['ok' => false, 'message' => $res['error']]);
        }
        if (empty($res['body']['paired'])) {
            return $this->json(['ok' => true, 'paired' => false]);
        }
        if (!empty($res['body']['token'])) {
            $this->connections->savePairing($site, (string) $res['body']['token']);
        }
        return $this->json(['ok' => true, 'paired' => true]);
    }
}
