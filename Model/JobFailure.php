<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Model;

/**
 * A queue job that did not finish. Permanent failures stop retrying and wait for the admin's
 * Retry button; the rest go back on the backoff ladder. envoice's HTTP codes do not separate the
 * two (a buyer validation error comes back as 500, an exchange rate outage as 409), so only a
 * missing pairing is permanent.
 */
class JobFailure extends \RuntimeException
{
    /**
     * @param string $message
     * @param bool $permanent
     */
    public function __construct(string $message, private readonly bool $permanent = false)
    {
        parent::__construct($message);
    }

    /**
     * @return bool
     */
    public function isPermanent(): bool
    {
        return $this->permanent;
    }
}
