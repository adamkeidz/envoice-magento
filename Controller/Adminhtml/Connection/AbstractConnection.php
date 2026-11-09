<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Controller\Adminhtml\Connection;

use Envoice\Einvoice\Model\StoreSites;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;

/**
 * Shared base of the pairing endpoints. Every call names a site URL, which must be one of this
 * install's own store URLs.
 */
abstract class AbstractConnection extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Envoice_Einvoice::config';

    /**
     * @param Context $context
     * @param StoreSites $sites
     */
    public function __construct(Context $context, protected readonly StoreSites $sites)
    {
        parent::__construct($context);
    }

    /**
     * @return string|null
     */
    protected function requestedSite(): ?string
    {
        $site = StoreSites::normalize((string) $this->getRequest()->getParam('site'));
        return $site !== null && array_key_exists($site, $this->sites->all()) ? $site : null;
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
