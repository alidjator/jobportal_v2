<?php

namespace Tests\Unit\Recruitment\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

final class ApplicationMovesTest extends TestCase
{
    private function inStage(StageType $type, int $stageId = 11): Application
    {
        return new Application(7, 42, 3, $stageId, $type, null, null, [], null, null, new DateTimeImmutable('2026-10-07'), 5);
    }

    /** @return array<string, array{StageType, StageType}> */
    public static function activeMoves(): array
    {
        return [
            'forward' => [StageType::Applied, StageType::Interview],
            'skipping' => [StageType::Applied, StageType::Offer],
            'backward' => [StageType::Offer, StageType::Screening],
            'to hired' => [StageType::Offer, StageType::Hired],
            'back from hired' => [StageType::Hired, StageType::Applied],
            'between two stages of one type' => [StageType::Interview, StageType::Interview],
        ];
    }

    #[DataProvider('activeMoves')]
    public function test_a_recruiter_moves_freely_between_active_stages(StageType $from, StageType $to): void
    {
        $moved = $this->inStage($from)->moveTo(99, $to);

        $this->assertSame($to, $moved->stageType);
        $this->assertSame(99, $moved->stageId);
        $this->assertSame('7-42', $moved->activeKey());
        $this->assertSame(5, $moved->id);
    }

    public function test_moving_to_the_stage_it_is_already_in_is_refused(): void
    {
        try {
            $this->inStage(StageType::Interview, 11)->moveTo(11, StageType::Interview);
            $this->fail('The move was accepted.');
        } catch (StateConflict $e) {
            $this->assertSame('already-in-stage', $e->errorCode());
        }
    }

    #[DataProvider('finalTypes')]
    public function test_a_recruiter_never_moves_into_a_final_stage(StageType $target): void
    {
        try {
            $this->inStage(StageType::Applied)->moveTo(99, $target);
            $this->fail('The move was accepted.');
        } catch (BusinessRuleViolation $e) {
            $this->assertSame('invalid-stage', $e->errorCode());
            $this->assertArrayHasKey('stage_id', $e->extensions()['errors']);
        }
    }

    /** @return array<string, array{StageType}> */
    public static function finalTypes(): array
    {
        return ['rejected' => [StageType::Rejected], 'withdrawn' => [StageType::Withdrawn]];
    }

    #[DataProvider('finalTypes')]
    public function test_a_final_application_never_moves_or_gets_rejected(StageType $type): void
    {
        foreach ([fn (Application $a): Application => $a->moveTo(99, StageType::Interview), fn (Application $a): Application => $a->reject(98)] as $change) {
            try {
                $change($this->inStage($type));
                $this->fail('The change was accepted.');
            } catch (StateConflict $e) {
                $this->assertSame('application-final', $e->errorCode());
            }
        }
    }

    public function test_rejecting_moves_to_the_rejected_stage_and_keeps_the_key(): void
    {
        foreach ([StageType::Applied, StageType::Screening, StageType::Interview, StageType::Offer, StageType::Hired] as $type) {
            $rejected = $this->inStage($type)->reject(98);

            $this->assertSame(StageType::Rejected, $rejected->stageType);
            $this->assertSame(98, $rejected->stageId);
            $this->assertSame('7-42', $rejected->activeKey());
        }
    }
}
