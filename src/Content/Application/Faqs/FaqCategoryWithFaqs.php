<?php

namespace Src\Content\Application\Faqs;

use Src\Content\Domain\Faq;
use Src\Content\Domain\FaqCategory;

final readonly class FaqCategoryWithFaqs
{
    /** @param  list<Faq>  $faqs */
    public function __construct(
        public FaqCategory $category,
        public array $faqs,
    ) {}
}
