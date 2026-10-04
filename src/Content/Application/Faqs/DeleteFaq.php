<?php

namespace Src\Content\Application\Faqs;

use Src\Content\Domain\FaqRepository;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final readonly class DeleteFaq
{
    public function __construct(private FaqRepository $faqs) {}

    /** @throws EntityNotFound */
    public function execute(int $id): void
    {
        $this->faqs->findById($id) ?? throw EntityNotFound::of('FAQ', $id);

        $this->faqs->delete($id);
    }
}
