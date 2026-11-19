<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Model;

use Magento\Backend\Model\Auth;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Adobe Commerce lets an owner limit an admin role to some websites or store groups. The ACL
 * resource alone does not carry that limit, so envoice actions check it here: a scoped admin may
 * pair, unpair or edit e-invoice data only for stores inside the role.
 *
 * The scope is read from the role row (gws_is_all, gws_websites, gws_store_groups) rather than
 * from Magento_AdminGws classes, which exist only on Adobe Commerce. On Magento Open Source those
 * columns are absent, so every admin holding the ACL resource keeps full access.
 */
class AdminScope
{
    /**
     * @param Auth $auth
     * @param StoreManagerInterface $storeManager
     * @param OrderRepositoryInterface $orders
     */
    public function __construct(
        private readonly Auth $auth,
        private readonly StoreManagerInterface $storeManager,
        private readonly OrderRepositoryInterface $orders
    ) {
    }

    /**
     * @param int $storeId
     * @return bool
     */
    public function allowsStore(int $storeId): bool
    {
        $limit = $this->limit();
        if ($limit === null) {
            return true;
        }
        try {
            /** @var Store $store */
            $store = $this->storeManager->getStore($storeId);
        } catch (NoSuchEntityException) {
            return false;
        }
        return in_array((int) $store->getWebsiteId(), $limit['websites'], true)
            || in_array((int) $store->getStoreGroupId(), $limit['groups'], true);
    }

    /**
     * @param int $orderId
     * @return bool
     */
    public function allowsOrder(int $orderId): bool
    {
        if ($this->limit() === null) {
            return true;
        }
        try {
            $order = $this->orders->get($orderId);
        } catch (NoSuchEntityException) {
            return false;
        }
        return $this->allowsStore((int) $order->getStoreId());
    }

    /**
     * Website and store group ids the signed-in role is limited to, or null when it is not limited.
     *
     * @return array{websites: int[], groups: int[]}|null
     */
    private function limit(): ?array
    {
        $user = $this->auth->getUser();
        if ($user === null) {
            return ['websites' => [], 'groups' => []];
        }
        $role = $user->getRole();
        $isAll = $role->getData('gws_is_all');
        if ($isAll === null || (int) $isAll === 1) {
            return null;
        }
        return [
            'websites' => $this->ids($role->getData('gws_websites')),
            'groups' => $this->ids($role->getData('gws_store_groups')),
        ];
    }

    /**
     * Magento_AdminGws turns these columns into arrays after the role loads; the raw row holds a
     * comma list.
     *
     * @param mixed $value
     * @return int[]
     */
    private function ids($value): array
    {
        $parts = is_array($value) ? $value : explode(',', (string) $value);
        $ids = [];
        foreach ($parts as $part) {
            if (trim((string) $part) !== '') {
                $ids[] = (int) $part;
            }
        }
        return $ids;
    }
}
