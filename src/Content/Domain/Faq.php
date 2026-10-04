<?php

namespace Src\Content\Domain;

use Src\Shared\Domain\TranslatedText;

final class Faq
{
    public function __construct(
        public readonly ?int $id,
        private int $categoryId,
        private TranslatedText $question,
        private TranslatedText $answer,
        private int $sortOrder,
        private bool $isActive,
    ) {}

    public function categoryId(): int
    {
        return $this->categoryId;
    }

    public function question(): TranslatedText
    {
        return $this->question;
    }

    public function answer(): TranslatedText
    {
        return $this->answer;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function change(int $categoryId, TranslatedText $question, TranslatedText $answer, int $sortOrder, bool $isActive): void
    {
        $this->categoryId = $categoryId;
        $this->question = $question;
        $this->answer = $answer;
        $this->sortOrder = $sortOrder;
        $this->isActive = $isActive;
    }
}
