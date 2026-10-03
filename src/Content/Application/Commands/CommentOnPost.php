<?php

namespace Src\Content\Application\Commands;

use Src\Content\Domain\BlogRepository;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final class CommentOnPost
{
    public function __construct(private BlogRepository $repository) {}

    /** @return array<string, mixed> */
    public function __invoke(int $authorId, string $slug, string $body, ?int $parentId): array
    {
        $postId = $this->repository->publishedPostId($slug) ?? throw new EntityNotFound('Post not found');

        if ($parentId !== null && ! $this->repository->commentBelongsToPost($parentId, $postId)) {
            throw new BusinessRuleViolation('Parent comment does not belong to this post');
        }

        return [
            'comment' => $this->repository->addComment($authorId, $postId, $parentId, $body),
            'message' => 'Comment created successfully',
        ];
    }
}
