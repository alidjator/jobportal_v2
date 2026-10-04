<?php

namespace Src\Content\Application\Faqs;

use Src\Content\Domain\FaqCategoryRepository;
use Src\Content\Domain\FaqRepository;

/** The FAQ as the public sees it: active categories in order, each with its active questions in order. Categories without a visible question are left out. */
final readonly class ListPublicFaqs
{
    public function __construct(
        private FaqCategoryRepository $categories,
        private FaqRepository $faqs,
    ) {}

    /** @return list<FaqCategoryWithFaqs> */
    public function execute(): array
    {
        $faqsByCategory = [];

        foreach ($this->faqs->list(null, true) as $faq) {
            $faqsByCategory[$faq->categoryId()][] = $faq;
        }

        $result = [];

        foreach ($this->categories->list(true) as $category) {
            $faqs = $faqsByCategory[$category->id] ?? [];

            if ($faqs !== []) {
                $result[] = new FaqCategoryWithFaqs($category, $faqs);
            }
        }

        return $result;
    }
}
