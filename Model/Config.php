<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const MODULE_VERSION = '1.1.2';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * @param int $storeId
     * @return bool
     */
    public function isAutoSubmit(int $storeId): bool
    {
        return $this->flag('envoice/automation/auto_submit', $storeId);
    }

    /**
     * @param int $storeId
     * @return string individual or consolidated
     */
    public function autoMode(int $storeId): string
    {
        $mode = (string) $this->value('envoice/automation/auto_mode', $storeId);
        return $mode === 'consolidated' ? 'consolidated' : 'individual';
    }

    /**
     * @param int $storeId
     * @return bool
     */
    public function isAutoCreditNote(int $storeId): bool
    {
        return $this->flag('envoice/automation/auto_cn', $storeId);
    }

    /**
     * @param int $storeId
     * @return bool
     */
    public function skipNonMyr(int $storeId): bool
    {
        return $this->flag('envoice/automation/skip_non_myr', $storeId);
    }

    /**
     * @param int $storeId
     * @return float|null
     */
    public function minTotal(int $storeId): ?float
    {
        $raw = trim((string) $this->value('envoice/automation/min_total', $storeId));
        return is_numeric($raw) ? (float) $raw : null;
    }

    /**
     * @param int $storeId
     * @return bool
     */
    public function vatAsTin(int $storeId): bool
    {
        return $this->flag('envoice/automation/vat_as_tin', $storeId);
    }

    /**
     * @param int $storeId
     * @return bool
     */
    public function showCheckoutRegNo(int $storeId): bool
    {
        return $this->flag('envoice/automation/checkout_reg_no', $storeId);
    }

    /**
     * Server-to-server base URL of envoice.
     *
     * @return string
     */
    public function apiBase(): string
    {
        $base = trim((string) $this->scopeConfig->getValue('envoice/advanced/api_base'));
        return rtrim($base !== '' ? $base : 'https://envoice.my', '/');
    }

    /**
     * Browser-facing base URL; differs from apiBase only when Magento runs in a container.
     *
     * @return string
     */
    public function publicBase(): string
    {
        $base = trim((string) $this->scopeConfig->getValue('envoice/advanced/public_base'));
        return $base !== '' ? rtrim($base, '/') : $this->apiBase();
    }

    /**
     * @param string $path
     * @param int $storeId
     * @return bool
     */
    private function flag(string $path, int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param string $path
     * @param int $storeId
     * @return mixed
     */
    private function value(string $path, int $storeId)
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
