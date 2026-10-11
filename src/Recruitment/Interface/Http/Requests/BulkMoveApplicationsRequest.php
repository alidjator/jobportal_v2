<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Recruitment\Application\BulkInterviewInput;
use Src\Recruitment\Application\OfferInput;
use Src\Recruitment\Domain\InterviewFormat;
use Src\Shared\Application\Assessment\AssessmentInvitationInput;
use Src\Shared\Interface\Http\Requests\AssessmentInvitationFields;

/**
 * The selection and the stage of a bulk move, with the base schedule for an interview stage (the daily window defaults to 09:00 to 17:00) and the one shared
 * offer for an offer stage and the one shared test (`assessment`) for an assessment stage. The cap on the selection, the stage and every range are use case rules.
 */
final class BulkMoveApplicationsRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            ...BulkSelection::rules(),
            'stage_id' => ['required', 'integer', 'min:1'],
            'interview' => ['nullable', 'array'],
            ...InterviewFields::rules('interview.', 'required_with:interview'),
            'interview.gap_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'interview.day_start' => ['nullable', 'date_format:H:i'],
            'interview.day_end' => ['nullable', 'date_format:H:i'],
            'offer' => ['nullable', 'array'],
            ...OfferFields::rules('offer.', 'required_with:offer'),
            'assessment' => ['nullable', 'array'],
            ...AssessmentInvitationFields::rules('assessment.', 'required_with:assessment'),
        ];
    }

    /** @return list<int> */
    public function applicationIds(): array
    {
        return BulkSelection::ids($this->validated('application_ids'));
    }

    public function stageId(): int
    {
        return $this->integer('stage_id');
    }

    public function interview(): ?BulkInterviewInput
    {
        $data = $this->validated('interview');

        if (! is_array($data) || $data === []) {
            return null;
        }

        return new BulkInterviewInput(
            InterviewFields::time((string) $data['starts_at']),
            (int) $data['duration_minutes'],
            (int) ($data['gap_minutes'] ?? 0),
            (string) ($data['day_start'] ?? '09:00'),
            (string) ($data['day_end'] ?? '17:00'),
            InterviewFormat::from((string) $data['format']),
            isset($data['location']) ? (string) $data['location'] : null,
            isset($data['meeting_url']) ? (string) $data['meeting_url'] : null,
            (int) $data['interviewer_id'],
        );
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
}
