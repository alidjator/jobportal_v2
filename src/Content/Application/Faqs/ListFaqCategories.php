<?php

namespace Src\Content\Application\Faqs;

use Src\Content\Domain\FaqCategory;
use Src\Content\Domain\FaqCategoryRepository;

/** Every category, active or not, in display order (admin). Bounded list: not paginated. */
final readonly class ListFaqCategories
{
    public function __construct(private FaqCategoryRepository $categories) {}

    /** @return list<FaqCategory> */
    public function execute(): array
    {
        return $this->categories->list(false);
    }
}
