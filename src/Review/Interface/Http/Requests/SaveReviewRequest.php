<?php

namespace Src\Review\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Review\Domain\ReviewContent;

/** The overall rating, the three optional aspect ratings (each 1 to 5), a title and a text of plain characters (clients escape them). */
final class SaveReviewRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rating = ['integer', 'between:1,5'];

        return [
            'rating' => ['required', ...$rating],
            'culture_rating' => ['nullable', ...$rating],
            'pay_rating' => ['nullable', ...$rating],
            'hiring_rating' => ['nullable', ...$rating],
            'title' => ['required', 'string', 'min:3', 'max:100'],
            'body' => ['required', 'string', 'min:50', 'max:3000'],
        ];
    }

    public function content(): ReviewContent
    {
        return new ReviewContent(
            $this->integer('rating'),
            $this->filled('culture_rating') ? $this->integer('culture_rating') : null,
            $this->filled('pay_rating') ? $this->integer('pay_rating') : null,
            $this->filled('hiring_rating') ? $this->integer('hiring_rating') : null,
            trim($this->string('title')->toString()),
            trim($this->string('body')->toString()),
        );
    }
}
