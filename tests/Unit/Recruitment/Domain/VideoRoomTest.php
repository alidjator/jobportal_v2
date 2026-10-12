<?php

namespace Tests\Unit\Recruitment\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewFormat;
use Src\Recruitment\Domain\Video\VideoJoinWindow;
use Src\Recruitment\Domain\Video\VideoRoom;

final class VideoRoomTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-10-12 10:00:00 UTC');
    }

    private function room(?int $roomId = null, ?DateTimeImmutable $closedAt = null, ?DateTimeImmutable $waitingAt = null, ?DateTimeImmutable $admittedAt = null): VideoRoom
    {
        return new VideoRoom(1, $roomId, $roomId === null ? null : 'pin', $roomId === null ? null : 'secret', $closedAt, $waitingAt, $admittedAt, $admittedAt === null ? null : 5);
    }

    public function test_the_window_runs_from_ten_minutes_before_the_start_to_thirty_minutes_after_the_end(): void
    {
        $interview = Interview::schedule(7, $this->now->modify('+1 hour'), 60, InterviewFormat::Video, null, null, 3, 2, $this->now);

        $this->assertSame('2026-10-12 10:50', VideoJoinWindow::opensAt($interview)->format('Y-m-d H:i'));
        $this->assertSame('2026-10-12 12:30', VideoJoinWindow::closesAt($interview)->format('Y-m-d H:i'));
        $this->assertFalse(VideoJoinWindow::isOpen($interview, new DateTimeImmutable('2026-10-12 10:49:59 UTC')));
        $this->assertTrue(VideoJoinWindow::isOpen($interview, new DateTimeImmutable('2026-10-12 10:50:00 UTC')));
        $this->assertTrue(VideoJoinWindow::isOpen($interview, new DateTimeImmutable('2026-10-12 12:30:00 UTC')));
        $this->assertFalse(VideoJoinWindow::isOpen($interview, new DateTimeImmutable('2026-10-12 12:30:01 UTC')));
    }

    public function test_a_room_is_open_once_created_and_until_it_is_closed(): void
    {
        $this->assertFalse($this->room()->isOpen());
        $this->assertTrue($this->room(10)->isOpen());
        $this->assertFalse($this->room(10, $this->now)->isOpen());
    }

    public function test_the_candidate_waits_for_a_minute_after_asking_to_enter_unless_admitted(): void
    {
        $this->assertFalse($this->room()->isWaiting($this->now));
        $this->assertTrue($this->room(waitingAt: $this->now->modify('-60 seconds'))->isWaiting($this->now));
        $this->assertFalse($this->room(waitingAt: $this->now->modify('-61 seconds'))->isWaiting($this->now));
        $this->assertFalse($this->room(waitingAt: $this->now, admittedAt: $this->now)->isWaiting($this->now));
        $this->assertTrue($this->room(admittedAt: $this->now)->isAdmitted());
    }
}
