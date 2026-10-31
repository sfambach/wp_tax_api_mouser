<?php declare(strict_types=1);

namespace TaxApiMouser\WordPress;

use TaxApiMouser\Core\MouserApiError;
use TaxApiMouser\Core\MouserSearch;

/**
 * The calls to Mouser over WordPress HTTP. A search is kept for an hour per query and quantity, so the purchasing page
 * does not ask Mouser again on every reload.
 */
final class MouserClient
{
    private const CACHE = 3600;

    public function __construct(private readonly StoredKey $key = new StoredKey())
    {
    }

    /**
     * @return list<array<string, mixed>> offers, see {@see MouserSearch::offers()}
     */
    public function search(string $query, int $quantity): array
    {
        $key = $this->key->key() ?? throw MouserApiError::noKey();
        $request = MouserSearch::request($query);
        $cache   = 'taxmouser_s_' . md5($request['path'] . wp_json_encode($request['body']));
        $answer  = get_transient($cache);

        if (! is_array($answer)) {
            $response = wp_remote_post(MouserSearch::BASE . $request['path'] . '?apiKey=' . rawurlencode($key), [
                'timeout' => 15,
                'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                'body'    => (string) wp_json_encode($request['body']),
            ]);

            if (is_wp_error($response)) {
                throw MouserApiError::unreachable($response->get_error_message());
            }

            $answer = json_decode((string) wp_remote_retrieve_body($response), true);

            if (! is_array($answer)) {
                throw MouserApiError::unreachable('HTTP ' . wp_remote_retrieve_response_code($response));
            }

            $offers = MouserSearch::offers($answer, max(1, $quantity));
            set_transient($cache, $answer, self::CACHE);

            return $offers;
        }

        return MouserSearch::offers($answer, max(1, $quantity));
    }
}
