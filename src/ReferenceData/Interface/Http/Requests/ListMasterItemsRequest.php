<?php

namespace Src\ReferenceData\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\ReferenceData\Application\Masters\MasterCatalog;
use Src\ReferenceData\Application\Masters\MasterDefinition;
use Src\ReferenceData\Application\Masters\MasterQuery;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Interface\Http\RequestLanguage;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/**
 * Reads a master list. Allowed: `filter[q]` (name search), the master's choice columns (for example `filter[kind]` on skills), `sort`
 * (`sort_order`, `name`, `-name`) and, for a paginated master, `page` and `per_page`. Any other filter or sort is refused with 422.
 */
final class ListMasterItemsRequest extends FormRequest
{
    use PaginatedRequest;

    private const int MAX_SEARCH_LENGTH = 100;

    /** The master this request is about, taken from the route. */
    public function definition(): MasterDefinition
    {
        $key = $this->route('master');

        return (is_string($key) ? MasterCatalog::find($key) : null) ?? throw EntityNotFound::of('Reference list', is_string($key) ? $key : null);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Without a `master` route parameter (the API documentation reads the rules this way) only the generic rules apply.
        $definition = $this->route('master') === null ? null : $this->definition();
        $allowedFilters = ['q', ...($definition?->filterNames() ?? [])];

        $rules = [
            'filter' => ['nullable', 'array:'.implode(',', $allowedFilters)],
            'filter.q' => ['nullable', 'string', 'max:'.self::MAX_SEARCH_LENGTH],
            'sort' => ['nullable', 'string', Rule::in(MasterQuery::SORTS)],
        ];

        if ($definition === null) {
            return $rules;
        }

        foreach ($definition->extras as $field) {
            if (in_array($field->name, $definition->filterNames(), true)) {
                $rules["filter.{$field->name}"] = ['nullable', 'string', Rule::in($field->choices)];
            }
        }

        return $definition->paginated ? [...$rules, ...$this->paginationRules()] : $rules;
    }

    public function toQuery(bool $onlyActive): MasterQuery
    {
        $definition = $this->definition();
        $filters = [];

        foreach ($definition->filterNames() as $name) {
            if ($this->filled("filter.{$name}")) {
                $filters[$name] = $this->string("filter.{$name}")->toString();
            }
        }

        return new MasterQuery(
            $onlyActive,
            RequestLanguage::of($this),
            $this->filled('filter.q') ? trim($this->string('filter.q')->toString()) : null,
            $filters,
            $this->string('sort', MasterQuery::SORT_CURATED)->toString(),
            $definition->paginated ? $this->pageRequest() : null,
        );
    }
}
