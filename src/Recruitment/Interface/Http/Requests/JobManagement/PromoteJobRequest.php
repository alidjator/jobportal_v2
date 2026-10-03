<?php

namespace Src\Recruitment\Interface\Http\Requests\JobManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Recruitment\Domain\JobManagement\PromotionBadge;

class PromoteJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'slug' => 'required|string',
            'badge' => ['required', Rule::in(array_map(fn (PromotionBadge $b) => $b->value, PromotionBadge::cases()))],
        ];
    }
}
