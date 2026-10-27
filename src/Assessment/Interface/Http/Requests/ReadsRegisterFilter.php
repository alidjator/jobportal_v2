<?php

namespace Src\Assessment\Interface\Http\Requests;

use Src\Assessment\Domain\RegisterFilter;

/** The filters of the report register: `filter[from]` and `filter[to]` (Jakarta days, `Y-m-d`), `filter[psychologist_id]` and `filter[test_id]`. */
trait ReadsRegisterFilter
{
    /** @return array<string, mixed> */
    protected function registerFilterRules(): array
    {
        return [
            'filter' => ['nullable', 'array:from,to,psychologist_id,test_id'],
            'filter.from' => ['nullable', 'date_format:Y-m-d'],
            'filter.to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:filter.from'],
            'filter.psychologist_id' => ['nullable', 'integer', 'min:1'],
            'filter.test_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function registerFilter(): RegisterFilter
    {
        return new RegisterFilter(
            $this->filled('filter.from') ? $this->string('filter.from')->toString() : null,
            $this->filled('filter.to') ? $this->string('filter.to')->toString() : null,
            $this->filled('filter.psychologist_id') ? $this->integer('filter.psychologist_id') : null,
            $this->filled('filter.test_id') ? $this->integer('filter.test_id') : null,
        );
    }
}
