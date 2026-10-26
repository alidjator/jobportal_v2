<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\ApplicationAnswerView;
use Src\Recruitment\Application\ApplicationInterviewResult;
use Src\Recruitment\Application\ApplicationReviewEntry;
use Src\Recruitment\Application\CompanyApplicationView;
use Src\Recruitment\Application\OtherApplicationView;
use Src\Recruitment\Application\RecruiterHistoryEntry;
use Src\Recruitment\Domain\StageMoveActor;
use Src\Recruitment\Domain\StageType;

/**
 * The detail of an application for the company's team: the whole profile snapshot, the cover letter, the answers and the stage history with the company's
 * stage names (a stage that was deleted later has none) and the rejection reason and note. A withdrawn application also carries `withdrawal`: the reason, who marked it and the team's own note. For an applicant whose account was anonymised the applicant holds
 * only `is_deleted` and a null name.
 *
 * @property CompanyApplicationView $resource
 */
final class CompanyApplicationDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $view = $this->resource;
        $data = $view->data;

        return [
            'id' => $data->id,
            'job' => ['id' => $data->jobId, 'title' => $view->jobTitle],
            'stage' => ['id' => $data->stageId, 'type' => $data->stageType->value, 'name' => $view->stageName],
            /** False when the applicant's email address no longer receives email: reach them by phone or chat. Never says why. */
            'email_deliverable' => $view->emailDeliverable,
            /** The slug of the applicant's current profile (`GET company/candidates/{slug}`), only while they can be found; null otherwise, and for a member who sits only on an interview panel. */
            'candidate_slug' => $view->candidateSlug,
            /** Whether the profile at `candidate_slug` has a picture (`GET company/candidates/{slug}/avatar`); false when there is no slug. */
            'candidate_has_avatar' => $view->candidateHasAvatar,
            'meets_must_have' => $data->meetsMustHave,
            'applied_at' => $data->appliedAt->format(DATE_ATOM),
            'stage_entered_at' => $data->stageEnteredAt->format(DATE_ATOM),
            /** Null for an imported application that was not moved yet: its stage time only says when it was imported. */
            'days_in_stage' => $view->daysInStage,
            'migrated' => $data->migratedAt !== null,
            'migrated_at' => $data->migratedAt?->format(DATE_ATOM),
            'stale' => $view->stale,
            /** @var array{join_date: string, note: string|null}|null the join date and the note for the candidate of a hired application */
            'hire' => HirePayload::of($data->hire),
            /** Whether the applicant allows the company to keep their profile in its talent pool (`POST company/talent-pool` with this application's id) */
            'talent_pool_consent' => $data->talentPoolConsent,
            /** @var list<array{id: int, job: array{id: int, title: string|null}, stage: array{type: string, name: string}, applied_at: string}> */
            'other_applications' => array_map(static fn (OtherApplicationView $other): array => [
                'id' => $other->application->id,
                'job' => ['id' => $other->application->jobId, 'title' => $other->jobTitle],
                'stage' => ['type' => $other->application->stageType->value, 'name' => $other->application->stageName],
                'applied_at' => $other->application->appliedAt->format(DATE_ATOM),
            ], $view->otherApplications),
            'others_hidden' => $view->othersHidden,
            /** @var list<array{request: array{id: int, due_on: string, status: string}, reviewer: array{id: int, name: string|null}, decision: 'advance'|'decline'|'maybe', reason: string|null, answered_at: string}> the answers of shortlist reviews about this application; a reviewer sees the others' only after sending their own */
            'reviews' => array_map(static fn (ApplicationReviewEntry $entry): array => [
                'request' => ['id' => $entry->requestId, 'due_on' => $entry->dueOn->format('Y-m-d'), 'status' => $entry->requestStatus->value],
                'reviewer' => ['id' => $entry->reviewerId, 'name' => $entry->reviewerName],
                'decision' => $entry->decision->value,
                'reason' => $entry->reason,
                'answered_at' => $entry->answeredAt->format(DATE_ATOM),
            ], $view->reviews),
            /** @var array{average: float|null, count: int, mine: int|null, members: list<array{user_id: int, name: string, stars: int}>} */
            'rating' => (new ApplicationRatingResource($view->rating))->resolve($request),
            /** @var list<array{id: int, status: string, starts_at: string, format: string, result: array{score: float|null, scorecards_sent: int, panel_size: int, recommendations: array<string, int>}|null}> */
            'interviews' => array_map(static fn (ApplicationInterviewResult $item): array => [
                'id' => $item->interview->id,
                'status' => $item->interview->status->value,
                'starts_at' => $item->interview->startsAt->format(DATE_ATOM),
                'format' => $item->interview->format->value,
                'result' => $item->result === null ? null : [
                    'score' => $item->result->score,
                    'scorecards_sent' => $item->result->scorecardsSent,
                    'panel_size' => $item->panelSize,
                    'recommendations' => $item->result->recommendations,
                ],
            ], $view->interviews),
            /**
             * Anonymised account: only `is_deleted` and a null name. Otherwise the profile frozen when the applicant applied, and every part after `contact_hidden` may be missing (an imported application of a user without a profile keeps only `name`, `email`, `migrated` and `migrated_at`). A master value (`period`, `experience_level`, `job_type`, `level`, skills, languages) holds its id and its name in both languages, not in the request language. `email`, `phone` and `whatsapp` are null while `contact_hidden` is true.
             *
             * @var array{is_deleted: true, name: null}|array{is_deleted: false, contact_hidden: bool, name?: string|null, email?: string|null, phone?: string|null, whatsapp?: string|null, age?: int|null, headline?: string|null, summary?: string|null, location?: array{province_id: int|null, city_id: int|null, province: string|null, city: string|null, subdistrict: string|null, village: string|null, postal_code: string|null, address_line: string|null}, expected_salary?: array{amount: int, period: array{id: int, name: array{id: string|null, en: string|null}}|null}|null, experience_level?: array{id: int, name: array{id: string|null, en: string|null}}|null, availability?: 'open_to_work'|'employed_open_to_offers'|'not_looking'|null, available_from?: string|null, experiences?: list<array{job_title: string, company_name: string, job_type: array{id: int, name: array{id: string|null, en: string|null}}|null, location_text: string|null, start: string, end: string|null, is_current: bool, description: string|null}>, educations?: list<array{level: array{id: int, name: array{id: string|null, en: string|null}}|null, institution: string, field_of_study: string|null, start_year: int, end_year: int|null, is_current: bool, gpa: string|null, description: string|null}>, skills?: list<array{id: int, name: array{id: string|null, en: string|null}|null}>, languages?: list<array{id: int, name: array{id: string|null, en: string|null}|null, proficiency: 'basic'|'conversational'|'fluent'|'native'}>, links?: list<array{type: 'linkedin'|'github'|'portfolio'|'website'|'instagram'|'x'|'other', url: string}>, migrated?: bool, migrated_at?: string}
             */
            'applicant' => $data->snapshot === null
                ? ['is_deleted' => true, 'name' => null]
                : ['is_deleted' => false, 'contact_hidden' => $data->contactHidden, ...array_diff_key($data->snapshot, ['version' => true])],
            'cover_letter' => $data->coverLetter,
            'resume' => $data->resumeName === null ? null : ['name' => $data->resumeName],
            'withdrawn_note' => $data->withdrawnNote,
            /** @var array{reason: string|null, by: string, actor: array{id: int, name: string}|null, note: string|null, at: string}|null */
            'withdrawal' => $this->withdrawal($view),
            /** @var list<array{question: array{id: int, text: string, type: string}, text: string|null, number: int|null, bool: bool|null, options: list<array{id: int, label: string}>}> */
            'answers' => array_map(static fn (ApplicationAnswerView $answer): array => [
                'question' => ['id' => $answer->questionId, 'text' => $answer->questionText, 'type' => $answer->type->value],
                'text' => $answer->text,
                'number' => $answer->number,
                'bool' => $answer->bool,
                'options' => $answer->options,
            ], $data->answers),
            /** @var list<array{from: array{stage_id: int|null, type: string, name: string|null}|null, to: array{stage_id: int, type: string, name: string|null}, by: string, actor: array{id: int, name: string}|null, reason: string|null, withdrawal_reason: string|null, note: string|null, at: string}> */
            'history' => array_map(fn (RecruiterHistoryEntry $entry): array => [
                'from' => $entry->fromType === null ? null : ['stage_id' => $entry->fromStageId, 'type' => $entry->fromType->value, 'name' => $view->stageNames[(int) $entry->fromStageId] ?? null],
                'to' => ['stage_id' => $entry->toStageId, 'type' => $entry->toType->value, 'name' => $view->stageNames[$entry->toStageId] ?? null],
                'by' => $entry->by->value,
                'actor' => $entry->actorId !== null && isset($view->actorNames[$entry->actorId]) ? ['id' => $entry->actorId, 'name' => $view->actorNames[$entry->actorId]] : null,
                'reason' => $entry->reason?->value,
                'withdrawal_reason' => $entry->withdrawalReason?->value,
                'note' => $entry->note,
                'at' => $entry->at->format(DATE_ATOM),
            ], $data->history),
        ];
    }

    /** The last move into `withdrawn`, when the application is withdrawn now. The team's (or the system's) note is the one of the history entry; the applicant's is the application's. */
    private function withdrawal(CompanyApplicationView $view): ?array
    {
        $data = $view->data;

        if ($data->stageType !== StageType::Withdrawn) {
            return null;
        }

        foreach (array_reverse($data->history) as $entry) {
            if ($entry->toType === StageType::Withdrawn) {
                $byTeam = $entry->by === StageMoveActor::Recruiter;
                $byApplicant = $entry->by === StageMoveActor::Applicant;

                return [
                    'reason' => $entry->withdrawalReason?->value,
                    'by' => $entry->by->value,
                    'actor' => $byTeam && $entry->actorId !== null && isset($view->actorNames[$entry->actorId]) ? ['id' => $entry->actorId, 'name' => $view->actorNames[$entry->actorId]] : null,
                    'note' => $byApplicant ? $data->withdrawnNote : $entry->note,
                    'at' => $entry->at->format(DATE_ATOM),
                ];
            }
        }

        return null;
    }
}
