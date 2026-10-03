<?php

namespace Src\Content\Domain;

interface BlogRepository
{
    /** @return array{posts: array<string, mixed>, recent_posts: array<int, mixed>, categories: array<int, mixed>} */
    public function listing(?string $search, ?string $categoryIds): array;

    /** @return array<string, mixed>|null */
    public function findPublished(string $slug): ?array;

    public function publishedPostId(string $slug): ?int;

    public function commentBelongsToPost(int $commentId, int $postId): bool;

    /** @return array<string, mixed> */
    public function addComment(int $authorId, int $postId, ?int $parentId, string $body): array;
}
