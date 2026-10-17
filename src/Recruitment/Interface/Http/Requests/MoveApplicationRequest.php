<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Recruitment\Application\InterviewInput;
use Src\Recruitment\Application\OfferInput;
use Src\Shared\Application\Assessment\AssessmentInvitationInput;
use Src\Shared\Interface\Http\Requests\AssessmentInvitationFields;

/**
 * The stage to move an application to, and the interview to schedule with the move (needed for an interview stage unless the application already has an
 * active one or the request says `interview.schedule_later`), and likewise the offer for an offer stage and the test (`assessment`) for an assessment stage. Whether it is an active stage of the company is a use case rule.
 */
final class MoveApplicationRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'stage_id' => ['required', 'integer', 'min:1'],
            'interview' => ['nullable', 'array'],
            'interview.schedule_later' => ['nullable', 'boolean'],
            ...InterviewFields::rules('interview.', Rule::requiredIf(fn (): bool => InterviewFields::requiredUnlessLater($this->input('interview')))),
            'offer' => ['nullable', 'array'],
            ...OfferFields::rules('offer.', 'required_with:offer'),
            'assessment' => ['nullable', 'array'],
            ...AssessmentInvitationFields::rules('assessment.', 'required_with:assessment'),
        ];
    }

    public function interview(): ?InterviewInput
    {
        $data = $this->validated('interview');

        return InterviewFields::requiredUnlessLater($data) && is_array($data) ? InterviewFields::input($data) : null;
    }

    /** The request says the interview time comes later: the move needs no schedule (`interview: {schedule_later: true}`). */
    public function scheduleLater(): bool
    {
        return InterviewFields::scheduleLater($this->validated('interview'));
    }

    public function offer(): ?OfferInput
    {
        $data = $this->validated('offer');

        return is_array($data) && $data !== [] ? OfferFields::input($data) : null;
    }

    public function assessment(): ?AssessmentInvitationInput
    {
        return AssessmentInvitationFields::inputOrNull($this->validated('assessment'));
    }

    public function stageId(): int
    {
        return $this->integer('stage_id');
    }
}
