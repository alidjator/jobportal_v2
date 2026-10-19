<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Interface\Http\Requests\CursorPaginatedRequest;

/**
 * Filters: `when` (`upcoming`, the default, or `past`), `my_scorecard` (`pending`: the scorecards the member still owes), `rsvp` (`pending`: the upcoming interviews the member has not answered; `declined`: the upcoming interviews the member scheduled where a panel member cannot attend), `proposal` (`open`: the upcoming interviews the member scheduled or leads where the job seeker's proposed time waits for an answer) and `mine` (`0` lists every interview of the
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
            'filter' => ['nullable', 'array:when,my_scorecard,rsvp,proposal,mine'],
            'filter.when' => ['nullable', 'string', 'in:upcoming,past'],
            'filter.my_scorecard' => ['nullable', 'string', 'in:pending'],
            'filter.rsvp' => ['nullable', 'string', 'in:pending,declined'],
            'filter.proposal' => ['nullable', 'string', 'in:open'],
            'filter.mine' => ['nullable', 'boolean'],
        ];
    }

    public function proposalOpen(): bool
    {
        return $this->input('filter.proposal') === 'open';
    }

    public function upcoming(): bool
    {
        return $this->input('filter.when', 'upcoming') === 'upcoming';
    }

    public function scorecardPending(): bool
    {
        return $this->input('filter.my_scorecard') === 'pending';
    }

    public function rsvp(): ?string
    {
        $rsvp = $this->input('filter.rsvp');

        return is_string($rsvp) ? $rsvp : null;
    }

    public function allOfCompany(): bool
    {
        return $this->has('filter.mine') && ! $this->boolean('filter.mine');
    }
}
