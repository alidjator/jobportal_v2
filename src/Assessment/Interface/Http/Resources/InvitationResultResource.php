<?php

namespace Src\Assessment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Results\InvitationResult;
use Src\Assessment\Domain\TestKind;

/**
 * The result of one test as the team sees it. A skill test shows its points, percentage and band (empty while a written answer waits for its assessor). A
 * psychological test shows only `awaiting_psychologist_review` until a psychologist signs, then the scores and the signed report.
 *
 * @property InvitationResult $resource
 */
final class InvitationResultResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $attempt = $this->resource->attempt;

        return [
            'invitation' => (new InvitationResource($this->resource->invitation))->toArray($request),
            'attempt' => $attempt === null ? null : [
                'started_at' => $attempt->startedAt->format(DateTimeInterface::ATOM),
                'submitted_at' => $attempt->submittedAt?->format(DateTimeInterface::ATOM),
                'auto_submitted' => $attempt->autoSubmitted,
            ],
            'result' => $this->resource->result === null ? null : ($this->resource->invitation->testKind === TestKind::Psychological ? $this->psychological() : $this->skill()),
        ];
    }

    /** @return array<string, mixed> */
    private function skill(): array
    {
        $result = $this->resource->result;

        return [
            'status' => $result?->status->value,
            'earned_points' => $result?->earnedPoints,
            'max_points' => $result?->maxPoints,
            'percentage' => $result?->percentage,
            'band' => $result?->band?->value,
            'sections' => $result?->sectionScores,
            'integrity_tier' => $result?->integrityTier->value,
        ];
    }

    /** @return array<string, mixed> */
    private function psychological(): array
    {
        $report = $this->resource->report;
        $signed = $report !== null && $report->isSigned();

        return [
            'status' => $signed ? 'signed' : 'awaiting_psychologist_review',
            'integrity_tier' => $this->resource->result?->integrityTier->value,
            'scales' => $signed ? $this->resource->scales : [],
            'report' => $report === null || ! $signed ? null : [
                'number' => $report->number,
                'signed_at' => $report->signedAt?->format(DateTimeInterface::ATOM),
                'signer_name' => $report->signerName,
                'text' => $report->body,
                /** @var 'recommended'|'consider'|'not_yet'|null The hiring advice of the report, for the company only: never in the explanation the candidate reads. */
                'recommendation' => $report->recommendation?->value,
                'recommendation_label' => $report->recommendation?->label(),
            ],
        ];
    }
}
