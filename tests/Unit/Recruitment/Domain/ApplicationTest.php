<?php

namespace Tests\Unit\Recruitment\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\ApplicationResume;
use Src\Recruitment\Domain\RejectionReason;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Domain\Exceptions\StateConflict;

final class ApplicationTest extends TestCase
{
    private function submitted(): Application
    {
        return Application::submit(
            7,
            42,
            3,
            11,
            'Hello',
            new ApplicationResume('cv.pdf', 'recruitment/applications/abc.pdf', 1024),
            ['name' => 'Siti'],
            true,
            new DateTimeImmutable('2026-10-06 10:00:00'),
        );
    }

    private function inStage(Application $application, StageType $type): Application
    {
        return new Application(
            $application->userId,
            $application->jobId,
            $application->companyId,
            11,
            $type,
            $application->coverLetter,
            $application->resume,
            $application->profileSnapshot,
            $application->meetsMustHave,
            null,
            $application->appliedAt,
            5,
        );
    }

    public function test_a_new_application_starts_in_the_applied_stage_with_an_active_key(): void
    {
        $application = $this->submitted();

        $this->assertSame(StageType::Applied, $application->stageType);
        $this->assertSame(11, $application->stageId);
        $this->assertSame('7-42', $application->activeKey());
        $this->assertNull($application->id);
    }

    public function test_withdrawing_moves_to_the_withdrawn_stage_and_frees_the_active_key(): void
    {
        $withdrawn = $this->submitted()->withdraw(17, 'Found another job');

        $this->assertSame(StageType::Withdrawn, $withdrawn->stageType);
        $this->assertSame(17, $withdrawn->stageId);
        $this->assertSame('Found another job', $withdrawn->withdrawnNote);
        $this->assertNull($withdrawn->activeKey());
        $this->assertSame('Hello', $withdrawn->coverLetter);
    }

    #[DataProvider('withdrawableTypes')]
    public function test_every_active_stage_except_hired_can_be_withdrawn(StageType $type): void
    {
        $this->assertTrue($this->inStage($this->submitted(), $type)->canBeWithdrawn());
    }

    /** @return array<string, array{StageType}> */
    public static function withdrawableTypes(): array
    {
        return [
            'applied' => [StageType::Applied],
            'screening' => [StageType::Screening],
            'interview' => [StageType::Interview],
            'offer' => [StageType::Offer],
        ];
    }

    #[DataProvider('finishedTypes')]
    public function test_hired_and_final_applications_cannot_be_withdrawn(StageType $type): void
    {
        $application = $this->inStage($this->submitted(), $type);

        $this->assertFalse($application->canBeWithdrawn());

        try {
            $application->withdraw(17, null);
            $this->fail('The withdrawal was accepted.');
        } catch (StateConflict $e) {
            $this->assertSame('application-not-withdrawable', $e->errorCode());
        }
    }

    /** @return array<string, array{StageType}> */
    public static function finishedTypes(): array
    {
        return [
            'hired' => [StageType::Hired],
            'rejected' => [StageType::Rejected],
            'withdrawn' => [StageType::Withdrawn],
        ];
    }

    public function test_a_rejected_or_hired_application_keeps_blocking_a_new_one(): void
    {
        $this->assertSame('7-42', $this->inStage($this->submitted(), StageType::Rejected)->activeKey());
        $this->assertSame('7-42', $this->inStage($this->submitted(), StageType::Hired)->activeKey());
    }

    public function test_only_the_other_reason_requires_a_note(): void
    {
        foreach (RejectionReason::cases() as $reason) {
            $this->assertSame($reason === RejectionReason::Other, $reason->requiresNote());
        }
    }
}
