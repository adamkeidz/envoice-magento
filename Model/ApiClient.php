<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Model;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * HTTP client for envoice's store plugin API (/api/v1/woo, shared by every store platform).
 */
class ApiClient
{
    private const TIMEOUT_SECONDS = 30;

    /**
     * @param CurlFactory $curlFactory
     * @param Json $json
     * @param Config $config
     */
    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly Json $json,
        private readonly Config $config
    ) {
    }

    /**
     * @param string $path
     * @param array $body
     * @param string $token empty for the unauthenticated pairing calls
     * @return array{ok: bool, status: int, body: array, error: string}
     */
    public function post(string $path, array $body, string $token = ''): array
    {
        $curl = $this->curl($token);
        try {
            $curl->post($this->url($path), $this->json->serialize($body));
        } catch (\Exception $e) {
            return ['ok' => false, 'status' => 0, 'body' => [], 'error' => 'Could not reach envoice: ' . $e->getMessage()];
        }
        return $this->result($curl);
    }

    /**
     * @param string $path
     * @param string $token
     * @return array{ok: bool, status: int, body: array, error: string}
     */
    public function get(string $path, string $token): array
    {
        $curl = $this->curl($token);
        try {
            $curl->get($this->url($path));
        } catch (\Exception $e) {
            return ['ok' => false, 'status' => 0, 'body' => [], 'error' => 'Could not reach envoice: ' . $e->getMessage()];
        }
        return $this->result($curl);
    }

    /**
     * Binary GET (receipt PDF).
     *
     * @param string $path
     * @param string $token
     * @return string|null
     */
    public function getRaw(string $path, string $token): ?string
    {
        $curl = $this->curl($token);
        try {
            $curl->get($this->url($path));
        } catch (\Exception $e) {
            return null;
        }
        return $curl->getStatus() === 200 ? $curl->getBody() : null;
    }

    /**
     * @param string $token
     * @return Curl
     */
    private function curl(string $token): Curl
    {
        $curl = $this->curlFactory->create();
        $curl->setTimeout(self::TIMEOUT_SECONDS);
        $curl->addHeader('Content-Type', 'application/json');
        $curl->addHeader('Accept', 'application/json');
        if ($token !== '') {
            $curl->addHeader('Authorization', 'Bearer ' . $token);
        }
        return $curl;
    }

    /**
     * @param string $path
     * @return string
     */
    private function url(string $path): string
    {
        return $this->config->apiBase() . '/api/v1/woo' . $path;
    }

    /**
     * @param Curl $curl
     * @return array{ok: bool, status: int, body: array, error: string}
     */
    private function result(Curl $curl): array
    {
        $status = $curl->getStatus();
        try {
            $body = $this->json->unserialize($curl->getBody());
        } catch (\InvalidArgumentException $e) {
            $body = [];
        }
        $body = is_array($body) ? $body : [];
        $ok = $status >= 200 && $status < 300;
        $error = '';
        if (!$ok) {
            $error = isset($body['error']) ? (string) $body['error'] : 'envoice returned HTTP ' . $status . '.';
        }
        return ['ok' => $ok, 'status' => $status, 'body' => $body, 'error' => $error];
    }
}
