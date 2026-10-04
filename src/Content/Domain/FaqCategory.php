<?php

namespace Src\Content\Domain;

use Src\Shared\Domain\TranslatedText;

final class FaqCategory
{
    public function __construct(
        public readonly ?int $id,
        private TranslatedText $name,
        private int $sortOrder,
        private bool $isActive,
    ) {}

    public function name(): TranslatedText
    {
        return $this->name;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function change(TranslatedText $name, int $sortOrder, bool $isActive): void
    {
        $this->name = $name;
        $this->sortOrder = $sortOrder;
        $this->isActive = $isActive;
    }
}
