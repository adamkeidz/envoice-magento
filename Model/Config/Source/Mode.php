<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Mode implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'individual', 'label' => __('Individual e-invoice')],
            ['value' => 'consolidated', 'label' => __('Monthly consolidated e-invoice')],
        ];
    }
}
