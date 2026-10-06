<?php

namespace Src\Job\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Job\Application\Admin\AdminJobSort;
use Src\Job\Domain\JobStatus;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/**
 * Allowed: `filter[q]` (title or company name), `filter[status]` (one of the seven statuses), `filter[company_id]`, `sort` (`updated_at`, `created_at`,
 * `published_at`, `title`, `deadline`, each with an optional `-`; default `-updated_at`), `page` and `per_page`. Any other filter is refused with 422.
 */
final class ListJobsForAdminRequest extends FormRequest
{
    use PaginatedRequest;

    private const int MAX_SEARCH_LENGTH = 100;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:q,status,company_id'],
            'filter.q' => ['nullable', 'string', 'max:'.self::MAX_SEARCH_LENGTH],
            'filter.status' => ['nullable', 'string', Rule::enum(JobStatus::class)],
            'filter.company_id' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', Rule::enum(AdminJobSort::class)],
        ];
    }

    public function status(): ?JobStatus
    {
        return $this->filled('filter.status') ? JobStatus::from($this->string('filter.status')->toString()) : null;
    }

    public function companyId(): ?int
    {
        return $this->filled('filter.company_id') ? $this->integer('filter.company_id') : null;
    }

    public function search(): ?string
    {
        return $this->filled('filter.q') ? trim($this->string('filter.q')->toString()) : null;
    }

    public function sort(): AdminJobSort
    {
        return $this->filled('sort') ? AdminJobSort::from($this->string('sort')->toString()) : AdminJobSort::UpdatedAtDescending;
    }
}
