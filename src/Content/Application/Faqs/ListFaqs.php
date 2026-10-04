<?php

namespace Src\Content\Application\Faqs;

use Src\Content\Domain\Faq;
use Src\Content\Domain\FaqRepository;

/** Every question, active or not, optionally of one category, in display order (admin). Bounded list: not paginated. */
final readonly class ListFaqs
{
    public function __construct(private FaqRepository $faqs) {}

    /** @return list<Faq> */
    public function execute(?int $categoryId): array
    {
        return $this->faqs->list($categoryId, false);
    }
}
