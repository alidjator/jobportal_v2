<?php

namespace Src\Content\Application\Queries;

use Src\Content\Domain\BlogRepository;

final class ListPosts
{
    public function __construct(private BlogRepository $repository) {}

    /** @return array<string, mixed> */
    public function __invoke(?string $search, ?string $category): array
    {
        return $this->repository->listing($search, $category);
    }
}
