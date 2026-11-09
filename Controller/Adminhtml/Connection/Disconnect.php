<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Controller\Adminhtml\Connection;

use Envoice\Einvoice\Model\ApiClient;
use Envoice\Einvoice\Model\Connections;
use Envoice\Einvoice\Model\StoreSites;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;

class Disconnect extends AbstractConnection
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
        if ($site === null) {
            return $this->json(['ok' => false, 'message' => (string) __('Unknown store URL.')]);
        }
        $token = $this->connections->token($site);
        if ($token !== '') {
            $this->api->post('/disconnect', [], $token);
        }
        $this->connections->forget($site);
        return $this->json(['ok' => true]);
    }
}
