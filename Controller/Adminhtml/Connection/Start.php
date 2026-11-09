<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Controller\Adminhtml\Connection;

use Envoice\Einvoice\Model\ApiClient;
use Envoice\Einvoice\Model\Config;
use Envoice\Einvoice\Model\Connections;
use Envoice\Einvoice\Model\StoreSites;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Controller\Result\Json;

/**
 * Pairing step 1: mint a one-time claim code, register the site with envoice, and hand the
 * browser the connect popup URL. The frontend claim check serves sha256(claim) meanwhile.
 */
class Start extends AbstractConnection
{
    /**
     * @param Context $context
     * @param StoreSites $sites
     * @param Connections $connections
     * @param ApiClient $api
     * @param Config $config
     * @param ProductMetadataInterface $productMetadata
     */
    public function __construct(
        Context $context,
        StoreSites $sites,
        private readonly Connections $connections,
        private readonly ApiClient $api,
        private readonly Config $config,
        private readonly ProductMetadataInterface $productMetadata
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
        $stores = $this->sites->all()[$site];
        $claim = bin2hex(random_bytes(16));
        $this->connections->startClaim($site, $claim);
        $res = $this->api->post('/register', [
            'siteUrl' => $site,
            'claimToken' => $claim,
            'storeName' => mb_substr((string) $stores[0]->getFrontendName(), 0, 200),
            'wooVersion' => mb_substr((string) $this->productMetadata->getVersion(), 0, 20),
            'pluginVersion' => Config::MODULE_VERSION,
            'platform' => 'magento',
        ]);
        if (!$res['ok']) {
            return $this->json(['ok' => false, 'message' => $res['error']]);
        }
        return $this->json([
            'ok' => true,
            'popupUrl' => $this->config->publicBase() . '/woo/connect?site=' . rawurlencode($site)
                . '&claim=' . rawurlencode($claim),
        ]);
    }
}
