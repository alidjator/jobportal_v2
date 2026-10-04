<?php

namespace Src\Content\Application\Faqs;

use Src\Content\Domain\FaqCategory;
use Src\Content\Domain\FaqCategoryRepository;
use Src\Shared\Domain\TranslatedText;

final readonly class CreateFaqCategory
{
    public function __construct(private FaqCategoryRepository $categories) {}

    /** @param  array<array-key, mixed>  $name */
    public function execute(array $name, int $sortOrder, bool $isActive): FaqCategory
    {
        return $this->categories->save(new FaqCategory(null, TranslatedText::fromArray($name), $sortOrder, $isActive));
    }
}
