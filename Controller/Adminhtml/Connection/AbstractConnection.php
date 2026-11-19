<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Controller\Adminhtml\Connection;

use Envoice\Einvoice\Model\AdminScope;
use Envoice\Einvoice\Model\StoreSites;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;

/**
 * Shared base of the pairing endpoints. Every call names a site URL, which must be one of this
 * install's own store URLs, and every store view on it must be inside the admin role's scope.
 */
abstract class AbstractConnection extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Envoice_Einvoice::config';

    /**
     * @param Context $context
     * @param StoreSites $sites
     * @param AdminScope $scope
     */
    public function __construct(
        Context $context,
        protected readonly StoreSites $sites,
        private readonly AdminScope $scope
    ) {
        parent::__construct($context);
    }

    /**
     * @return string|null
     */
    protected function requestedSite(): ?string
    {
        $site = StoreSites::normalize((string) $this->getRequest()->getParam('site'));
        $stores = $site !== null ? ($this->sites->all()[$site] ?? []) : [];
        if ($stores === []) {
            return null;
        }
        foreach ($stores as $store) {
            if (!$this->scope->allowsStore((int) $store->getId())) {
                return null;
            }
        }
        return $site;
    }

    /**
     * @param array $data
     * @return Json
     */
    protected function json(array $data): Json
    {
        /** @var Json $result */
        $result = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
        return $result->setData($data);
    }
}
