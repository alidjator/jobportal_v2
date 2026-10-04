<?php

namespace Src\Content\Application\Faqs;

use Src\Content\Domain\Faq;
use Src\Content\Domain\FaqCategoryRepository;
use Src\Content\Domain\FaqRepository;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\TranslatedText;

final readonly class CreateFaq
{
    public function __construct(
        private FaqRepository $faqs,
        private FaqCategoryRepository $categories,
    ) {}

    /**
     * @param  array<array-key, mixed>  $question
     * @param  array<array-key, mixed>  $answer
     *
     * @throws BusinessRuleViolation `faq-category-not-found` when the category does not exist
     */
    public function execute(int $categoryId, array $question, array $answer, int $sortOrder, bool $isActive): Faq
    {
        if ($this->categories->findById($categoryId) === null) {
            throw new BusinessRuleViolation('The category does not exist.', 'faq-category-not-found');
        }

        return $this->faqs->save(new Faq(null, $categoryId, TranslatedText::fromArray($question), TranslatedText::fromArray($answer), $sortOrder, $isActive));
    }
}
