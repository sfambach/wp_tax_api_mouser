<?php declare(strict_types=1);

namespace TaxApiMouser\WordPress;

use Taxmod\Core\Exception\SecretDoesNotOpen;
use Taxmod\WordPress\Import\Vault;

/**
 * The Mouser API key from the tab «Mouser», sealed in the modeller's vault (D-951). A constant in wp-config.php goes
 * first (D-944). Mouser gives one key per account and unlocks search, cart and order history on it.
 */
final class StoredKey
{
    public const OPTION        = 'taxmod_mouser_key';
    public const VAULT_CONTEXT = 'mouser';
    public const CONSTANT      = 'TAXMOUSER_API_KEY';

    public static function vaultAvailable(): bool
    {
        return class_exists(Vault::class) && Vault::API >= 1;
    }

    public function fromConstant(): bool
    {
        return defined(self::CONSTANT) && is_string(constant(self::CONSTANT)) && constant(self::CONSTANT) !== '';
    }

    public function key(): ?string
    {
        if ($this->fromConstant()) {
            return (string) constant(self::CONSTANT);
        }

        $sealed = (string) get_option(self::OPTION, '');

        return $sealed === '' || ! self::vaultAvailable() ? null : $this->open($sealed);
    }

    public function isStored(): bool
    {
        return (string) get_option(self::OPTION, '') !== '';
    }

    /** true when a key is stored but no longer opens — the salts in wp-config.php changed */
    public function broken(): bool
    {
        $sealed = (string) get_option(self::OPTION, '');

        return $sealed !== '' && self::vaultAvailable() && $this->open($sealed) === null;
    }

    /** An empty key keeps the stored one. Caller has checked capability and nonce and sanitized. */
    public function save(#[\SensitiveParameter] string $key): void
    {
        if ($key !== '' && self::vaultAvailable()) {
            update_option(self::OPTION, Vault::for(self::VAULT_CONTEXT)->seal($key), false);
        }
    }

    public function forget(): void
    {
        delete_option(self::OPTION);
    }

    private function open(string $sealed): ?string
    {
        try {
            return Vault::for(self::VAULT_CONTEXT)->open($sealed);
        } catch (SecretDoesNotOpen) {
            return null;
        }
    }
}
