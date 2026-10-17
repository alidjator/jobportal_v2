<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Recruitment\Application\InterviewInput;
use Src\Recruitment\Application\OfferInput;
use Src\Recruitment\Domain\Interview;
use Src\Shared\Application\Assessment\AssessmentInvitationInput;
use Src\Shared\Interface\Http\Requests\AssessmentInvitationFields;

/**
 * The email to preview and the fields of the form being filled, flat in the body and validated by the rules of the real request of that scenario. `interview_scheduled`
 * and `interview_rescheduled` both take the fields of a schedule: the email shown is the one the application would get (a new time over an active interview is a
 * reschedule).
 */
final class EmailPreviewRequest extends FormRequest
{
    public const array SCENARIOS = ['interview_scheduled', 'interview_rescheduled', 'interview_updated', 'assessment_invited', 'offer_sent'];

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['scenario' => ['required', 'string', Rule::in(self::SCENARIOS)], ...match ($this->input('scenario')) {
            'interview_scheduled', 'interview_rescheduled' => InterviewFields::rules(),
            'interview_updated' => ['note' => ['present', 'nullable', 'string', 'max:'.Interview::NOTE_MAX]],
            'assessment_invited' => AssessmentInvitationFields::rules(),
            'offer_sent' => OfferFields::rules(),
            default => [],
        }];
    }

    public function scenario(): string
    {
        return $this->string('scenario')->toString();
    }

    public function interview(): InterviewInput
    {
        return InterviewFields::input($this->validated());
    }

    public function note(): ?string
    {
        $note = $this->validated('note');

        return is_string($note) && trim($note) !== '' ? trim($note) : null;
    }

    public function assessment(): AssessmentInvitationInput
    {
        return AssessmentInvitationFields::input($this->validated());
    }

    public function offer(): OfferInput
    {
        return OfferFields::input($this->validated());
    }
}
