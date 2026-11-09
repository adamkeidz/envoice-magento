<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Model;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Maps store views to the site URL envoice knows them by. A pairing belongs to a site URL, so
 * store views sharing one base URL share one envoice shop, and store views with their own URL
 * (own domain, or store codes in URLs) each pair separately.
 */
class StoreSites
{
    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(private readonly StoreManagerInterface $storeManager)
    {
    }

    /**
     * Same identity rule as normalizeSiteUrl in envoice's lib/woo/auth.ts.
     *
     * @param string $url
     * @return string|null
     */
    public static function normalize(string $url): ?string
    {
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $parts = parse_url(trim($url));
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        if (($scheme !== 'https' && $scheme !== 'http') || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        if (($scheme === 'https' && $port === ':443') || ($scheme === 'http' && $port === ':80')) {
            $port = '';
        }
        $path = rtrim($parts['path'] ?? '', '/');
        return strtolower($scheme . '://' . $parts['host'] . $port . $path);
    }

    /**
     * @param int $storeId
     * @return string
     */
    public function forStore(int $storeId): string
    {
        /** @var Store $store */
        $store = $this->storeManager->getStore($storeId);
        return (string) self::normalize($store->getBaseUrl(UrlInterface::URL_TYPE_LINK, $store->isFrontUrlSecure()));
    }

    /**
     * Every active store view grouped by site URL.
     *
     * @return array<string, Store[]>
     */
    public function all(): array
    {
        $out = [];
        foreach ($this->storeManager->getStores() as $store) {
            /** @var Store $store */
            if (!$store->isActive()) {
                continue;
            }
            $out[$this->forStore((int) $store->getId())][] = $store;
        }
        return $out;
    }
}
