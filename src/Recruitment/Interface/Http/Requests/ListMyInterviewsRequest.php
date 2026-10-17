<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Interface\Http\Requests\CursorPaginatedRequest;

/**
 * Filters: `when` (`upcoming`, the default, or `past`), `my_scorecard` (`pending`: the scorecards the member still owes) and `mine` (`0` lists every interview of the
 * jobs the member may see, which needs `interviews.manage`). Any other filter is refused with 422. Cursor-paginated (`cursor`, `per_page`).
 */
final class ListMyInterviewsRequest extends FormRequest
{
    use CursorPaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->cursorRules(),
            'filter' => ['nullable', 'array:when,my_scorecard,mine'],
            'filter.when' => ['nullable', 'string', 'in:upcoming,past'],
            'filter.my_scorecard' => ['nullable', 'string', 'in:pending'],
            'filter.mine' => ['nullable', 'boolean'],
        ];
    }

    public function upcoming(): bool
    {
        return $this->input('filter.when', 'upcoming') === 'upcoming';
    }

    public function scorecardPending(): bool
    {
        return $this->input('filter.my_scorecard') === 'pending';
    }

    public function allOfCompany(): bool
    {
        return $this->has('filter.mine') && ! $this->boolean('filter.mine');
    }
}
