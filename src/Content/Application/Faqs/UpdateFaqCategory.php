<?php

namespace Src\Content\Application\Faqs;

use Src\Content\Domain\FaqCategory;
use Src\Content\Domain\FaqCategoryRepository;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\TranslatedText;

final readonly class UpdateFaqCategory
{
    public function __construct(private FaqCategoryRepository $categories) {}

    /**
     * @param  array<array-key, mixed>  $name
     *
     * @throws EntityNotFound
     */
    public function execute(int $id, array $name, int $sortOrder, bool $isActive): FaqCategory
    {
        $category = $this->categories->findById($id) ?? throw EntityNotFound::of('FAQ category', $id);

        $category->change(TranslatedText::fromArray($name), $sortOrder, $isActive);

        return $this->categories->save($category);
    }
}
