<?php

namespace Src\Recruitment\Interface\Http\Requests\JobManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Recruitment\Application\JobManagement\JobPosting;
use Src\Recruitment\Domain\JobManagement\ApplyChannel;
use Src\Recruitment\Domain\JobManagement\PromotionBadge;

/** Aturan sama dengan `StoreJobService` lama (ID master dicek ke tabel, enum DB dicek eksplisit). */
class StoreJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'category_id' => 'required|integer|exists:job_categories,id',
            'role_id' => 'required|integer|exists:job_roles,id',
            'experience' => 'required|integer|exists:experiences,id',
            'education' => 'required|integer|exists:education,id',
            'job_type' => 'required|integer|exists:job_types,id',
            'vacancies' => 'required|max:255',
            'salary_mode' => ['required', Rule::in(['range', 'custom'])],
            'custom_salary' => 'required_if:salary_mode,custom|nullable|string|max:255',
            'min_salary' => 'required|numeric|min:0|lte:max_salary',
            'max_salary' => 'required|numeric|gte:min_salary',
            'salary_type' => 'required|integer|exists:salary_types,id',
            'show_salary' => 'required|string|max:1',
            'deadline' => 'required|date',
            'description' => 'required',
            'featured' => 'nullable|numeric',
            'badge' => ['nullable', Rule::in(array_map(fn (PromotionBadge $b) => $b->value, PromotionBadge::cases()))],
            'is_remote' => 'nullable|boolean',
            'apply_on' => ['required', Rule::in([ApplyChannel::APP, ApplyChannel::EMAIL, ApplyChannel::CUSTOM_URL])],
            'apply_email' => 'required_if:apply_on,email|nullable|email|max:255',
            'apply_url' => 'required_if:apply_on,custom_url|nullable|url|max:255',
            'work_time' => 'required|integer|exists:job_work_times,id',
            'subdistrict_id' => 'nullable|integer|exists:subdistricts,bps_code',
            'city_id' => 'required|integer|exists:cities,bps_code',
            'province_id' => 'required|integer|exists:states,bps_code',
            'grp_pos_id' => 'required|integer|exists:job_levels,id',
            'hard_skill' => 'required|string',
            'soft_skill' => 'required|string',
            'benefits' => 'required|string',
            'notes' => 'nullable|string',
            'job_order_id' => 'nullable|integer|unique:jobs,job_order_id',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:255',
        ];
    }

    public function toPosting(): JobPosting
    {
        return JobPosting::fromValidated($this->validated());
    }
}
