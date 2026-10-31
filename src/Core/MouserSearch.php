<?php declare(strict_types=1);

namespace TaxApiMouser\Core;

/**
 * Mouser's Search API: the request for a keyword or a Mouser number, and its answer read into offers (D-1027 E6).
 *
 * An offer is a plain array, the shape the purchasing page of Taxmod Stock reads through `taxmod_supplier_offers`, so
 * neither plugin needs the other's classes. The price is the one of the price break that the quantity reaches.
 *
 * ```mermaid
 * flowchart LR
 *   Q["Bezeichnung / Mouser-Nr."] --> R["POST search/keyword · search/partnumber"] --> A["Parts"] --> O["Angebote: Nr., Preis für Menge, Lager"]
 * ```
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/lager/einkauf.md EK6
 */
final class MouserSearch
{
    public const MERCHANT = 'Mouser';
    public const BASE     = 'https://api.mouser.com/api/v1';

    /** Offers shown per search; Mouser answers at most 50. */
    private const RECORDS = 10;

    /**
     * The path and body of a search: a Mouser number (it has a dash after the maker prefix, e.g. 637-BC547C) is looked up
     * exactly, anything else by keyword.
     *
     * @return array{path: string, body: array<string, mixed>}
     */
    public static function request(string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            throw MouserApiError::emptyQuery();
        }

        if (preg_match('/^\d{2,4}-\S+$/', $query) === 1) {
            return ['path' => '/search/partnumber', 'body' => ['SearchByPartRequest' => ['mouserPartNumber' => $query, 'partSearchOptions' => 'Exact']]];
        }

        return ['path' => '/search/keyword', 'body' => ['SearchByKeywordRequest' => ['keyword' => $query, 'records' => self::RECORDS, 'startingRecord' => 0]]];
    }

    /**
     * @param array<string, mixed> $answer the decoded JSON
     * @return list<array{merchant: string, number: string, maker: string, maker_number: string, description: string, stock: int, price: string|null, currency: string, min: int, mult: int, url: string}>
     */
    public static function offers(array $answer, int $quantity): array
    {
        $errors = is_array($answer['Errors'] ?? null) ? $answer['Errors'] : [];

        if ($errors !== []) {
            throw MouserApiError::refused(implode('; ', array_map(static fn ($e): string => is_array($e) ? (string) ($e['Message'] ?? $e['Code'] ?? '') : (string) $e, $errors)));
        }

        if (isset($answer['Message']) && ! isset($answer['SearchResults'])) {
            throw MouserApiError::refused((string) $answer['Message']);
        }

        $out = [];

        foreach ((array) ($answer['SearchResults']['Parts'] ?? []) as $part) {
            if (! is_array($part) || trim((string) ($part['MouserPartNumber'] ?? '')) === '' || ($part['MouserPartNumber'] ?? '') === 'N/A') {
                continue;
            }

            [$price, $currency] = self::priceFor((array) ($part['PriceBreaks'] ?? []), $quantity);
            $out[] = [
                'merchant'     => self::MERCHANT,
                'number'       => trim((string) $part['MouserPartNumber']),
                'maker'        => trim((string) ($part['Manufacturer'] ?? '')),
                'maker_number' => trim((string) ($part['ManufacturerPartNumber'] ?? '')),
                'description'  => trim((string) ($part['Description'] ?? '')),
                'stock'        => (int) ($part['AvailabilityInStock'] ?? 0),
                'price'        => $price,
                'currency'     => $currency,
                'min'          => max(1, (int) ($part['Min'] ?? 1)),
                'mult'         => max(1, (int) ($part['Mult'] ?? 1)),
                'url'          => (string) ($part['ProductDetailUrl'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * The price of the highest break the quantity reaches — or of the first, when it reaches none — as a decimal with a point.
     *
     * @param array<mixed> $breaks
     * @return array{0: string|null, 1: string}
     */
    public static function priceFor(array $breaks, int $quantity): array
    {
        $chosen = null;

        foreach ($breaks as $break) {
            if (! is_array($break)) {
                continue;
            }

            $chosen ??= $break;

            if ((int) ($break['Quantity'] ?? 0) <= $quantity && (int) ($break['Quantity'] ?? 0) >= (int) ($chosen['Quantity'] ?? 0)) {
                $chosen = $break;
            }
        }

        if ($chosen === null) {
            return [null, ''];
        }

        return [self::decimal((string) ($chosen['Price'] ?? '')), (string) ($chosen['Currency'] ?? '')];
    }

    /** «0,129 €», «1.234,50 €» or «$0.13» as «0.129», «1234.50», «0.13»: the last separator is the decimal point. */
    public static function decimal(string $price): ?string
    {
        $digits = (string) preg_replace('/[^\d,.]/', '', $price);

        if (preg_match('/\d/', $digits) !== 1) {
            return null;
        }

        $last = max(strrpos($digits, ',') === false ? -1 : (int) strrpos($digits, ','), strrpos($digits, '.') === false ? -1 : (int) strrpos($digits, '.'));

        if ($last < 0) {
            return $digits;
        }

        $whole = (string) preg_replace('/\D/', '', substr($digits, 0, $last));

        return ($whole === '' ? '0' : $whole) . '.' . preg_replace('/\D/', '', substr($digits, $last + 1));
    }
}
