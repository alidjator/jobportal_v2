<?php

namespace Src\Assessment\Application\Export;

use DateTimeInterface;
use Src\Assessment\Application\Reports\GetMyExplanation;
use Src\Assessment\Domain\AssessmentDataEraser;
use Src\Assessment\Domain\AttemptOutcomeRepository;
use Src\Assessment\Domain\AttemptRepository;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Application\Privacy\ExportedFile;
use Src\Shared\Application\Privacy\PersonalDataExporter;
use Src\Shared\Application\Privacy\PersonalDataSection;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The Assessment part of the personal data export: the test invitations (with when the test was taken and its integrity tier, never the signals behind it),
 * the explanation of each psychological result a psychologist signed (what the candidate may read, never the company's report or the reviewer's notes) and the
 * drawings the candidate uploaded in tests. Answers and scores are not part of it: they carry the answer keys of the test provider.
 */
final readonly class AssessmentDataExporter implements PersonalDataExporter
{
    public function __construct(
        private InvitationRepository $invitations,
        private AttemptRepository $attempts,
        private AttemptOutcomeRepository $outcomes,
        private TestRepository $tests,
        private GetMyExplanation $explanations,
        private AssessmentDataEraser $drawings,
        private CompanyDirectory $companies,
    ) {}

    public function key(): string
    {
        return 'assessments';
    }

    public function export(int $userId): PersonalDataSection
    {
        $invitations = [];

        for ($page = 1; ; $page++) {
            $result = $this->invitations->pageOfApplicant($userId, null, new PageRequest($page, PageRequest::MAX_PER_PAGE));
            array_push($invitations, ...$result->items);

            if (count($result->items) < PageRequest::MAX_PER_PAGE) {
                break;
            }
        }

        $companies = $this->companies->summaries(array_values(array_unique(array_map(static fn (Invitation $invitation): int => $invitation->companyId, $invitations))));
        $files = [];

        foreach ($this->drawings->drawingPaths($userId) as $number => $path) {
            $files[] = new ExportedFile($path, 'drawing-'.($number + 1).'.'.(pathinfo($path, PATHINFO_EXTENSION) ?: 'png'));
        }

        return new PersonalDataSection([
            'invitations' => array_map(fn (Invitation $invitation): array => $this->row($userId, $invitation, ($companies[$invitation->companyId] ?? null)?->name), $invitations),
            'drawings' => count($files),
        ], $files);
    }

    /** @return array<string, mixed> */
    private function row(int $userId, Invitation $invitation, ?string $company): array
    {
        $attempt = $invitation->id === null ? null : $this->attempts->findByInvitation($invitation->id);
        $result = $attempt?->id === null ? null : $this->outcomes->resultOf($attempt->id);

        return [
            'test' => $this->tests->find($invitation->testId)?->title(),
            'company' => $company,
            'status' => $invitation->status->value,
            'mode' => $invitation->mode->value,
            'location' => $invitation->location,
            'note' => $invitation->note,
            'starts_at' => $invitation->startsAt->format(DateTimeInterface::ATOM),
            'due_at' => $invitation->dueAt->format(DateTimeInterface::ATOM),
            'closed_at' => $invitation->closedAt?->format(DateTimeInterface::ATOM),
            'consented_at' => $attempt?->consentedAt->format(DateTimeInterface::ATOM),
            'started_at' => $attempt?->startedAt->format(DateTimeInterface::ATOM),
            'submitted_at' => $attempt?->submittedAt?->format(DateTimeInterface::ATOM),
            'auto_submitted' => $attempt?->autoSubmitted,
            'integrity_tier' => $result?->integrityTier->value,
            'explanation' => $this->explanation($userId, $invitation),
        ];
    }

    /** @return array<string, mixed>|null null until a psychologist signed the report of this test */
    private function explanation(int $userId, Invitation $invitation): ?array
    {
        if ($invitation->id === null) {
            return null;
        }

        try {
            $explanation = $this->explanations->execute(Actor::jobSeeker($userId), $invitation->id);
        } catch (EntityNotFound|StateConflict) {
            return null;
        }

        return [
            'report_number' => $explanation->reportNumber,
            'signed_by' => $explanation->signerName,
            'signed_at' => $explanation->signedAt->format(DateTimeInterface::ATOM),
            'text' => $explanation->explanation,
            'scales' => $explanation->scales,
        ];
    }
}
