<?php

namespace Src\Content\Infrastructure\Persistence;

use Src\Content\Domain\BlogRepository;
use Src\Content\Infrastructure\Persistence\Eloquent\Post;
use Src\Content\Infrastructure\Persistence\Eloquent\PostCategory;
use Src\Content\Infrastructure\Persistence\Eloquent\PostComment;

final class EloquentBlogRepository implements BlogRepository
{
    private const PER_PAGE = 8;

    public function listing(?string $search, ?string $categoryIds): array
    {
        $posts = Post::query()->published()->withCount('comments');

        if ($search) {
            $posts->where('title', 'like', '%'.$search.'%');
        }

        if ($categoryIds) {
            $posts->whereIn('category_id', explode(',', $categoryIds));
        }

        return [
            'posts' => $posts->latest()->paginate(self::PER_PAGE)->withQueryString()->toArray(),
            'recent_posts' => Post::published()->withCount('comments')->latest()->take(5)->get()->toArray(),
            'categories' => PostCategory::latest()->get(['id', 'name'])->toArray(),
        ];
    }

    public function findPublished(string $slug): ?array
    {
        $post = Post::published()->where('slug', $slug)
            ->with([
                'author:id,name',
                'comments.replies.user:id,name,image',
                'comments.user:id,name,image',
            ])
            ->first();

        return $post?->toArray();
    }

    public function publishedPostId(string $slug): ?int
    {
        $id = Post::published()->where('slug', $slug)->value('id');

        return $id === null ? null : (int) $id;
    }

    public function commentBelongsToPost(int $commentId, int $postId): bool
    {
        return PostComment::where('id', $commentId)->where('post_id', $postId)->exists();
    }

    public function addComment(int $authorId, int $postId, ?int $parentId, string $body): array
    {
        $comment = new PostComment;
        $comment->author_id = $authorId;
        $comment->post_id = $postId;
        $comment->parent_id = $parentId;
        $comment->body = $body;
        $comment->save();

        return $comment->load(['user:id,name,image', 'replies'])->toArray();
    }
}
