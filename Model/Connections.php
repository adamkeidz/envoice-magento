<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Pairings, one row per site URL. The per-store token is stored encrypted with the Magento key.
 */
class Connections
{
    /**
     * @param ResourceConnection $resource
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    /**
     * @param string $siteUrl
     * @return string empty when the site is not paired
     */
    public function token(string $siteUrl): string
    {
        $enc = (string) ($this->row($siteUrl)['token'] ?? '');
        return $enc === '' ? '' : (string) $this->encryptor->decrypt($enc);
    }

    /**
     * @param string $siteUrl
     * @return string
     */
    public function claim(string $siteUrl): string
    {
        return (string) ($this->row($siteUrl)['claim_token'] ?? '');
    }

    /**
     * @param string $siteUrl
     * @param string $claim
     * @return void
     */
    public function startClaim(string $siteUrl, string $claim): void
    {
        $this->resource->getConnection()->insertOnDuplicate(
            $this->table(),
            ['site_url' => $siteUrl, 'claim_token' => $claim],
            ['claim_token']
        );
    }

    /**
     * @param string $siteUrl
     * @param string $token
     * @return void
     */
    public function savePairing(string $siteUrl, string $token): void
    {
        $this->resource->getConnection()->insertOnDuplicate(
            $this->table(),
            ['site_url' => $siteUrl, 'token' => $this->encryptor->encrypt($token), 'claim_token' => null],
            ['token', 'claim_token']
        );
    }

    /**
     * @param string $siteUrl
     * @return void
     */
    public function forget(string $siteUrl): void
    {
        $this->resource->getConnection()->delete($this->table(), ['site_url = ?' => $siteUrl]);
    }

    /**
     * @return string[] site URLs holding a token
     */
    public function pairedSites(): array
    {
        $db = $this->resource->getConnection();
        return $db->fetchCol(
            $db->select()->from($this->table(), ['site_url'])->where('token IS NOT NULL')
        );
    }

    /**
     * @param string $siteUrl
     * @return array
     */
    private function row(string $siteUrl): array
    {
        $db = $this->resource->getConnection();
        $row = $db->fetchRow($db->select()->from($this->table())->where('site_url = ?', $siteUrl));
        return is_array($row) ? $row : [];
    }

    /**
     * @return string
     */
    private function table(): string
    {
        return $this->resource->getTableName('envoice_connection');
    }
}
