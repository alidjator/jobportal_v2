<?php

namespace Src\Content\Application\Faqs;

use Src\Content\Domain\Faq;
use Src\Content\Domain\FaqCategoryRepository;
use Src\Content\Domain\FaqRepository;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\TranslatedText;

final readonly class UpdateFaq
{
    public function __construct(
        private FaqRepository $faqs,
        private FaqCategoryRepository $categories,
    ) {}

    /**
     * @param  array<array-key, mixed>  $question
     * @param  array<array-key, mixed>  $answer
     *
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `faq-category-not-found` when the target category does not exist
     */
    public function execute(int $id, int $categoryId, array $question, array $answer, int $sortOrder, bool $isActive): Faq
    {
        $faq = $this->faqs->findById($id) ?? throw EntityNotFound::of('FAQ', $id);

        if ($this->categories->findById($categoryId) === null) {
            throw new BusinessRuleViolation('The category does not exist.', 'faq-category-not-found');
        }

        $faq->change($categoryId, TranslatedText::fromArray($question), TranslatedText::fromArray($answer), $sortOrder, $isActive);

        return $this->faqs->save($faq);
    }
}
