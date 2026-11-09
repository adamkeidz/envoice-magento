<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Block\Adminhtml\System\Config;

use Envoice\Einvoice\Model\Connections as ConnectionStore;
use Envoice\Einvoice\Model\StoreSites;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Connections extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Envoice_Einvoice::system/config/connections.phtml';

    /**
     * @param Context $context
     * @param StoreSites $sites
     * @param ConnectionStore $connections
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly StoreSites $sites,
        private readonly ConnectionStore $connections,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritdoc
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * @inheritdoc
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * @return array<int, array{site: string, stores: string, connected: bool}>
     */
    public function getRows(): array
    {
        $rows = [];
        foreach ($this->sites->all() as $site => $stores) {
            $names = array_map(static fn ($store) => (string) $store->getName(), $stores);
            $rows[] = [
                'site' => $site,
                'stores' => implode(', ', $names),
                'connected' => $this->connections->token($site) !== '',
            ];
        }
        return $rows;
    }

    /**
     * @return array
     */
    public function getWidgetConfig(): array
    {
        return [
            'startUrl' => $this->getUrl('envoice/connection/start'),
            'pollUrl' => $this->getUrl('envoice/connection/poll'),
            'disconnectUrl' => $this->getUrl('envoice/connection/disconnect'),
            'text' => [
                'waiting' => (string) __('Finish in the envoice window.'),
                'expired' => (string) __('Connect timed out. Try again.'),
                'failed' => (string) __('Could not reach this Magento admin.'),
                'confirmDisconnect' => (string) __('Disconnect this store URL from envoice? Orders stop filing.'),
            ],
        ];
    }
}
