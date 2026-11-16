<?php declare(strict_types=1);

namespace TaxApiMouser\WordPress;

use Taxmod\WordPress\Import\StoredSecret;

/**
 * The Mouser API key from the tab «Mouser», in the modeller's sealed store (D-951, D-1409). A constant in wp-config.php
 * goes first (D-944). Mouser gives one key per account and unlocks search, cart and order history on it.
 */
final class StoredKey
{
    private readonly StoredSecret $secret;

    public function __construct()
    {
        $this->secret = new StoredSecret('taxmod_mouser_key', 'mouser', 'TAXMOUSER_API_KEY');
    }

    public function fromConstant(): bool
    {
        return $this->secret->slot()->fromConstant();
    }

    public function key(): ?string
    {
        return $this->secret->value();
    }

    public function isStored(): bool
    {
        return $this->secret->slot()->isStored();
    }

    /** true when a key is stored but no longer opens — the salts in wp-config.php changed */
    public function broken(): bool
    {
        return $this->secret->slot()->broken();
    }

    /** An empty key keeps the stored one. Caller has checked capability and nonce and sanitized. */
    public function save(#[\SensitiveParameter] string $key): void
    {
        $this->secret->save($key);
    }

    public function forget(): void
    {
        $this->secret->forget();
    }
}
