<?php declare(strict_types=1);

namespace TaxApiMouser\WordPress;

use TaxApiMouser\Core\MouserApiError;
use TaxApiMouser\Core\MouserMailParser;
use Taxmod\Core\Import\Mail\OrderMailParser;

/**
 * The boundary: registers the Mouser mail reader with Taxmod Mail (filter `taxmail_parsers`), a tab on the modeller's
 * Configuration page with the API key and a test search, and the search as offers for the purchasing page of Taxmod
 * Stock (filter `taxmod_supplier_offers`, D-1027 E6).
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md
 */
final class Plugin
{
    public const VERSION = '0.1.0';
    public const TAB     = 'mouser';

    public static function boot(): void
    {
        add_action('plugins_loaded', static function (): void {
            load_plugin_textdomain('taxapimouser', false, dirname(plugin_basename(dirname(__DIR__, 2) . '/wp-tax-api-mouser.php')) . '/languages');

            // The contract lives in the modeller (0.1.81 or later); without it there is nothing to register with.
            if (! interface_exists(OrderMailParser::class)) {
                add_action('admin_notices', static fn () => print '<div class="notice notice-warning"><p><strong>Taxmod Mouser:</strong> '
                    . esc_html__('needs the Taxonomy Modeller 0.1.81 or later and Taxmod Mail 0.6.0 or later.', 'taxapimouser') . '</p></div>');

                return;
            }

            // D-1027 E6: Mouser's offers for a position of a demand, when a key is there.
            add_filter('taxmod_supplier_offers', [self::class, 'offers'], 10, 3);

            $reader = new MouserMailParser(self::VERSION);
            add_filter('taxmail_parsers', static fn (array $parsers): array => [...$parsers, $reader]);
            add_filter('taxmod_config_tabs', static fn (array $tabs): array => $tabs + [self::TAB => [
                'title'   => 'Mouser',
                'version' => self::VERSION,
                'reader'  => $reader,
                'guide'   => self::guide(...),
                'render'  => static fn (): string => self::render(),
                'save'    => static fn (array $post): bool => self::save($post),
            ]], 30);
        });
    }

    /**
     * Mouser's offers appended to those of other suppliers; without a key or on a refusal there are none, and the reason
     * goes along under `errors` so the page can say it.
     *
     * @param array<string, mixed> $offers `list` of offers plus optional `errors` (merchant => text)
     * @return array<string, mixed>
     */
    public static function offers(mixed $offers, string $query, int $quantity): array
    {
        $offers = is_array($offers) ? $offers : [];
        $key    = new StoredKey();

        if ($key->key() === null) {
            return $offers;
        }

        try {
            $offers['list'] = [...(array) ($offers['list'] ?? []), ...(new MouserClient($key))->search($query, $quantity)];
        } catch (MouserApiError $e) {
            $offers['errors']['Mouser'] = self::message($e);
        }

        return $offers;
    }

    /** The key (never shown again once stored) and a test search, which writes nothing. */
    private static function render(): string
    {
        $key  = new StoredKey();
        $html = '<h3>' . esc_html__('API', 'taxapimouser') . '</h3>';

        if (! StoredKey::vaultAvailable() && ! $key->fromConstant()) {
            return $html . '<p>' . esc_html__('Needs the vault of the Taxonomy Modeller (0.1.31 or later), or the constant TAXMOUSER_API_KEY in wp-config.php.', 'taxapimouser') . '</p>';
        }

        if ($key->broken()) {
            $html .= '<div class="notice notice-warning inline"><p>' . esc_html__('The stored key no longer opens — the salts in wp-config.php changed. Enter it again.', 'taxapimouser') . '</p></div>';
        }

        $html .= $key->fromConstant()
            ? '<p>' . esc_html__('The key comes from TAXMOUSER_API_KEY in wp-config.php.', 'taxapimouser') . '</p>'
            : '<table class="form-table" role="presentation"><tbody><tr><th scope="row"><label for="taxapimouser_key">' . esc_html__('API key', 'taxapimouser') . '</label></th><td>'
                . '<input type="password" class="regular-text" id="taxapimouser_key" name="taxapimouser_key" autocomplete="off" placeholder="'
                . esc_attr($key->isStored() ? __('stored — leave empty to keep it', 'taxapimouser') : '') . '">'
                . '<p class="description">' . esc_html__('From My Mouser › APIs. It is stored encrypted. Mouser uses the same key for search, cart and order history once they are unlocked.', 'taxapimouser') . '</p>'
                . ($key->isStored() ? '<p><label><input type="checkbox" name="taxapimouser_forget" value="1"> ' . esc_html__('Delete the stored key', 'taxapimouser') . '</label></p>' : '')
                . '</td></tr></tbody></table>';

        $query = isset($_GET['taxapimouser_test']) ? sanitize_text_field(wp_unslash((string) $_GET['taxapimouser_test'])) : '';
        $html .= '<h3>' . esc_html__('Test search', 'taxapimouser') . '</h3><p><input type="search" id="taxapimouser_test" value="' . esc_attr($query) . '" placeholder="BC547C"> '
            . '<button type="button" class="button" onclick="var q=document.getElementById(\'taxapimouser_test\').value;var u=new URL(location.href);u.searchParams.set(\'taxapimouser_test\',q);location.href=u">' . esc_html__('Search', 'taxapimouser') . '</button></p>';

        if ($query === '' || $key->key() === null) {
            return $html;
        }

        try {
            $offers = (new MouserClient($key))->search($query, 1);
        } catch (MouserApiError $e) {
            return $html . '<div class="notice notice-error inline"><p>' . esc_html(self::message($e)) . '</p></div>';
        }

        $rows = '';

        foreach (array_slice($offers, 0, 5) as $offer) {
            $rows .= '<tr><td><a href="' . esc_url((string) $offer['url']) . '" target="_blank" rel="noopener">' . esc_html((string) $offer['number']) . '</a></td><td>'
                . esc_html($offer['maker'] . ' ' . $offer['maker_number']) . '</td><td>' . esc_html((string) $offer['description']) . '</td><td>'
                . esc_html((string) $offer['stock']) . '</td><td>' . esc_html(($offer['price'] ?? '—') . ' ' . $offer['currency']) . '</td></tr>';
        }

        return $html . '<table class="widefat striped"><thead><tr><th>' . esc_html__('Mouser no.', 'taxapimouser') . '</th><th>' . esc_html__('Maker', 'taxapimouser') . '</th><th>'
            . esc_html__('Description', 'taxapimouser') . '</th><th>' . esc_html__('In stock', 'taxapimouser') . '</th><th>' . esc_html__('Price (1 pc)', 'taxapimouser') . '</th></tr></thead><tbody>'
            . ($rows === '' ? '<tr><td colspan="5">' . esc_html__('Nothing found.', 'taxapimouser') . '</td></tr>' : $rows) . '</tbody></table>';
    }

    /**
     * The modeller has checked capability and nonce (D-945); the key is sanitized here.
     *
     * @param array<string, mixed> $post already unslashed
     */
    private static function save(array $post): bool
    {
        $key = new StoredKey();

        if ($key->fromConstant()) {
            return true;
        }

        if (! empty($post['taxapimouser_forget'])) {
            $key->forget();

            return true;
        }

        $key->save(is_string($post['taxapimouser_key'] ?? null) ? trim(sanitize_text_field($post['taxapimouser_key'])) : '');

        return $key->key() !== null;
    }

    private static function message(MouserApiError $e): string
    {
        return match ($e->reason) {
            'no_key'      => __('No Mouser API key stored.', 'taxapimouser'),
            'empty_query' => __('Nothing to search for.', 'taxapimouser'),
            /* translators: %s: Mouser's message */
            'refused'     => sprintf(__('Mouser refused: %s', 'taxapimouser'), $e->detail),
            /* translators: %s: the transport error */
            default       => sprintf(__('Mouser not reachable: %s', 'taxapimouser'), $e->detail),
        };
    }

    /** The box «How to connect» beside the tab (D-963). */
    public static function guide(): string
    {
        return '<ol><li>' . esc_html__('Let Mouser send its order mails to the mailbox of Taxmod Mail (or forward them there).', 'taxapimouser') . '</li><li>'
            . esc_html__('Keep this module active: Taxmod Mail hands every Mouser mail to it, and the proposals appear in the Inbox.', 'taxapimouser') . '</li></ol><p>'
            . esc_html__('For the search: enter the API key from My Mouser › APIs under «API»; the purchasing page of Taxmod Stock then shows Mouser offers per position. Cart and order history follow.', 'taxapimouser') . '</p>';
    }
}
