<?php

namespace Src\Content\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Src\Content\Domain\BlogRepository;

class CommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body' => 'required|string|min:2|max:2500',
            'parent_id' => 'nullable|integer|min:1',
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $parentId = $this->input('parent_id');
            if ($parentId === null || $validator->errors()->isNotEmpty()) {
                return;
            }

            $blog = app(BlogRepository::class);
            $postId = $blog->publishedPostId((string) $this->route('slug'));

            // Post tidak ada: ditangani use case (404).
            if ($postId !== null && ! $blog->commentBelongsToPost((int) $parentId, $postId)) {
                $validator->errors()->add('parent_id', 'The selected parent comment is invalid.');
            }
        }];
    }
}
