<?php

namespace Src\Content\Application\Queries;

use Src\Content\Application\ContentLanguage;
use Src\Content\Domain\FaqRepository;

final class ListFaqs
{
    public function __construct(private FaqRepository $repository, private ContentLanguage $language) {}

    /** @return array<int, mixed> */
    public function __invoke(): array
    {
        return $this->repository->categoriesWithFaqs($this->language->current());
    }
}
