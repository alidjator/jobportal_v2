<?php

namespace Src\Recruitment\Domain\JobBoard;

/**
 * Satu halaman hasil query (tanpa ketergantungan ke paginator framework).
 */
final class Page
{
    /** @param  list<array<string, mixed>>  $items */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $perPage,
        public readonly int $currentPage,
    ) {}

    /** @param  callable(array<string, mixed>): mixed  $fn */
    public function map(callable $fn): self
    {
        return new self(array_map($fn, $this->items), $this->total, $this->perPage, $this->currentPage);
    }
}
