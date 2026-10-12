<?php

namespace Tests\Unit\Recruitment\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Recruitment\Domain\Video\InterviewRecording;
use Src\Recruitment\Domain\Video\RecordingStatus;
use Src\Shared\Domain\Exceptions\StateConflict;

final class InterviewRecordingTest extends TestCase
{
    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time);
    }

    public function test_a_recording_goes_from_recording_to_processing_to_ready(): void
    {
        $recording = InterviewRecording::start(7, 3, $this->at('2026-10-05 09:00:00'));
        $this->assertTrue($recording->isRecording());

        $stopped = $recording->stop($this->at('2026-10-05 09:30:00'));
        $this->assertSame(RecordingStatus::Processing, $stopped->status);
        $this->assertEquals($this->at('2026-10-05 09:30:00'), $stopped->stoppedAt);
        $this->assertFalse($stopped->isRecording());

        $converted = $stopped->converted('ready/9.webm', 1800);
        $this->assertSame('ready/9.webm', $converted->sourceFile);
        $this->assertSame(1800, $converted->durationSeconds);

        $stored = $converted->stored('interview-recordings/7/abc.webm', 1024, $this->at('2027-01-05 10:00:00'));
        $this->assertSame(RecordingStatus::Ready, $stored->status);
        $this->assertNull($stored->sourceFile);
        $this->assertSame('interview-recordings/7/abc.webm', $stored->path);
        $this->assertSame(1024, $stored->sizeBytes);
        $this->assertSame(1800, $stored->durationSeconds);
        $this->assertEquals($this->at('2026-10-05 09:30:00'), $stored->stoppedAt);
    }

    public function test_only_a_running_recording_can_be_stopped(): void
    {
        $stopped = InterviewRecording::start(7, 3, $this->at('2026-10-05 09:00:00'))->stop($this->at('2026-10-05 09:30:00'));

        $this->expectException(StateConflict::class);
        $stopped->stop($this->at('2026-10-05 09:31:00'));
    }

    public function test_a_failed_recording_keeps_no_file(): void
    {
        $failed = InterviewRecording::start(7, 3, $this->at('2026-10-05 09:00:00'))
            ->stop($this->at('2026-10-05 09:30:00'))
            ->converted('ready/9.webm', 10)
            ->failed();

        $this->assertSame(RecordingStatus::Failed, $failed->status);
        $this->assertNull($failed->sourceFile);
        $this->assertNull($failed->path);
        $this->assertNull($failed->expiresAt);
    }

    public function test_a_conversion_report_for_a_recording_that_is_not_processing_changes_nothing(): void
    {
        $running = InterviewRecording::start(7, 3, $this->at('2026-10-05 09:00:00'));

        $this->assertSame($running, $running->converted('ready/9.webm', 10));
    }
}
