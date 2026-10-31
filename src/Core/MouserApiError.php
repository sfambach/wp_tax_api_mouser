<?php declare(strict_types=1);

namespace TaxApiMouser\Core;

/**
 * Why a Mouser call gave no answer; the boundary words it (code plus detail, no user text in the core).
 */
final class MouserApiError extends \RuntimeException
{
    private function __construct(public readonly string $reason, public readonly string $detail)
    {
        parent::__construct($reason . ': ' . $detail);
    }

    public static function emptyQuery(): self
    {
        return new self('empty_query', '');
    }

    public static function noKey(): self
    {
        return new self('no_key', '');
    }

    /** Mouser answered with errors, or the key was refused. */
    public static function refused(string $message): self
    {
        return new self('refused', $message);
    }

    public static function unreachable(string $message): self
    {
        return new self('unreachable', $message);
    }
}
