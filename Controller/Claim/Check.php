<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Controller\Claim;

use Envoice\Einvoice\Model\Connections;
use Envoice\Einvoice\Model\StoreSites;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Proof of site control for pairing. While a claim is pending for this store URL it serves
 * {"check": sha256(claim)}, never the claim itself. envoice fetches it before it pairs a site,
 * so nobody can pair a store they do not run.
 */
class Check implements HttpGetActionInterface
{
    /**
     * @param JsonFactory $jsonFactory
     * @param StoreManagerInterface $storeManager
     * @param StoreSites $sites
     * @param Connections $connections
     */
    public function __construct(
        private readonly JsonFactory $jsonFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly StoreSites $sites,
        private readonly Connections $connections
    ) {
    }

    /**
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        $site = $this->sites->forStore((int) $this->storeManager->getStore()->getId());
        $claim = $this->connections->claim($site);
        if ($claim === '') {
            return $result->setHttpResponseCode(404)->setData(['error' => 'no-claim']);
        }
        return $result->setData(['check' => hash('sha256', $claim)]);
    }
}
