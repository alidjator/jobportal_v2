<?php

namespace Src\Content\Application\Faqs;

use Src\Content\Domain\FaqCategoryRepository;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

final readonly class DeleteFaqCategory
{
    public function __construct(
        private FaqCategoryRepository $categories,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws EntityNotFound
     * @throws StateConflict `faq-category-not-empty` while the category still has questions: move or delete them, or deactivate the category instead
     */
    public function execute(int $id): void
    {
        $this->transactions->run(function () use ($id): void {
            $this->categories->findById($id) ?? throw EntityNotFound::of('FAQ category', $id);

            if ($this->categories->hasFaqs($id)) {
                throw new StateConflict('The category still has questions. Move or delete them first, or deactivate the category.', 'faq-category-not-empty');
            }

            $this->categories->delete($id);
        });
    }
}
