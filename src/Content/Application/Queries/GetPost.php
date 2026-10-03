<?php

namespace Src\Content\Application\Queries;

use Src\Content\Domain\BlogRepository;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final class GetPost
{
    public function __construct(private BlogRepository $repository) {}

    /** @return array<string, mixed> */
    public function __invoke(string $slug): array
    {
        return $this->repository->findPublished($slug) ?? throw new EntityNotFound('Post not found');
    }
}
