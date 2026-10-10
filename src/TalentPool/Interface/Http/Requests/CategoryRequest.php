<?php

namespace Src\TalentPool\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\TalentPool\Domain\Category;

/** The body that creates or renames a category: `name` (the length is checked after trimming by the domain). */
final class CategoryRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:'.(Category::MAX_NAME_LENGTH * 4)]];
    }

    public function categoryName(): string
    {
        return $this->string('name')->toString();
    }
}
