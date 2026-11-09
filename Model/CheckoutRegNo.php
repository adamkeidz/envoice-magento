<?php
declare(strict_types=1);

namespace Envoice\Einvoice\Model;

/**
 * The buyer's business registration number or ID number typed at checkout. It lives in a column
 * of the same name on quote and sales_order and is sent as the buyer idValue. The ID type is left
 * to envoice, which defaults a local buyer to BRN, as the WooCommerce plugin's checkout field does.
 */
class CheckoutRegNo
{
    public const FIELD = 'envoice_buyer_reg_no';

    public const MAX_LENGTH = 50;

    /**
     * Same rule as the checkout field. A value that fails it is dropped, never allowed to block an order.
     */
    public const PATTERN = '^[A-Za-z0-9][A-Za-z0-9 /-]*$';

    /**
     * @param mixed $raw
     * @return string uppercase and single spaced, or empty when the value does not look like an ID
     */
    public static function normalize($raw): string
    {
        $value = strtoupper(trim((string) preg_replace('/\s+/', ' ', (string) $raw)));
        if ($value === '' || strlen($value) > self::MAX_LENGTH || !preg_match('#' . self::PATTERN . '#', $value)) {
            return '';
        }
        return $value;
    }
}
