<?php

namespace Src\Campaign\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Campaign\Domain\CampaignStatus;
use Src\Campaign\Domain\CampaignType;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/**
 * Allowed: `filter[q]` (name or code), `filter[type]`, `filter[status]` (`active`, `inactive`, `scheduled`, `ended`), `filter[company_id]` (used for a platform admin
 * only; a company admin always gets their own campaigns), `page` and `per_page`. Any other filter is refused with 422. Newest campaigns come first.
 */
final class ListCampaignsRequest extends FormRequest
{
    use PaginatedRequest;

    private const int MAX_SEARCH_LENGTH = 100;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:q,type,status,company_id'],
            'filter.q' => ['nullable', 'string', 'max:'.self::MAX_SEARCH_LENGTH],
            'filter.type' => ['nullable', 'string', Rule::enum(CampaignType::class)],
            'filter.status' => ['nullable', 'string', Rule::enum(CampaignStatus::class)],
            'filter.company_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function search(): ?string
    {
        return $this->filled('filter.q') ? trim($this->string('filter.q')->toString()) : null;
    }

    public function type(): ?CampaignType
    {
        return $this->filled('filter.type') ? CampaignType::from($this->string('filter.type')->toString()) : null;
    }

    public function status(): ?CampaignStatus
    {
        return $this->filled('filter.status') ? CampaignStatus::from($this->string('filter.status')->toString()) : null;
    }

    public function companyId(): ?int
    {
        return $this->filled('filter.company_id') ? $this->integer('filter.company_id') : null;
    }
}
