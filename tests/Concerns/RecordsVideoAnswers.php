<?php

namespace Tests\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Recording the answer to a video question as the candidate does: a test with one video question is published, sent and started, then takes are
 * started, their chunks sent and completed. The test class sets `$applicationId` and uses the application and assessment fixtures.
 */
trait RecordsVideoAnswers
{
    private int $invitationId = 0;

    private int $questionId = 0;

    private ?string $attemptSession = null;

    private function freeze(int $seconds): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 09:00:00')->addSeconds($seconds));
    }

    /** A published test with one video section (1200 s) and one video question: answer 60 s, `$retakes` retakes. It is sent and started. */
    private function startVideoAttempt(int $retakes = 1, int $answerSeconds = 60): void
    {
        $id = $this->newTest('company', ['title' => 'Video screening']);
        $sectionId = (int) $this->company('POST', "tests/{$id}/sections", ['title' => 'Interview', 'format' => 'video', 'duration_seconds' => 1200])->assertCreated()->json('data.id');
        $this->questionId = (int) $this->company('POST', "sections/{$sectionId}/questions", [
            'type' => 'video_answer', 'text' => 'Tell us about a project.', 'points' => 10, 'think_seconds' => null, 'answer_seconds' => $answerSeconds, 'max_retakes' => $retakes,
        ])->assertCreated()->json('data.id');
        $this->company('POST', "tests/{$id}/publish")->assertOk();

        $stage = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'assessment')->where('is_default', 1)->value('id');
        $this->api('POST', "/company/applications/{$this->applicationId}/move", [
            'stage_id' => $stage,
            'assessment' => ['test_id' => $id, 'due_at' => now()->addDays(3)->utc()->format('Y-m-d\TH:i:s\Z'), 'assessor_ids' => [$this->adminId]],
        ], $this->recruiterToken)->assertOk();
        $this->invitationId = (int) DB::table('assessment_invitations')->orderByDesc('id')->value('id');

        $this->take('POST', 'start', ['consent' => true, 'video_consent' => true])->assertCreated();
    }

    /** @param  array<string, mixed>  $body */
    private function take(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        $response = $this->api($method, "/me/assessment/invitations/{$this->invitationId}/{$uri}", $body, $token ?? $this->seekerToken, $this->attemptSession === null ? [] : ['X-Attempt-Session' => $this->attemptSession]);

        if ($uri === 'start' && is_string($session = $response->json('data.attempt_session'))) {
            $this->attemptSession = $session;
        }

        return $response;
    }

    private function videoUri(string $suffix = ''): string
    {
        return "attempt/questions/{$this->questionId}/video{$suffix}";
    }

    private function startTake(): TestResponse
    {
        return $this->take('POST', $this->videoUri('/takes'));
    }

    /** Sends a chunk that the server accepts. */
    private function sendChunk(int $takeId, int $index, string $contents): TestResponse
    {
        return $this->sendChunkRaw($takeId, $index, $contents)->assertOk();
    }

    private function webm(string $body = 'frames'): string
    {
        return "\x1A\x45\xDF\xA3".$body;
    }

    private function completeTake(int $takeId): TestResponse
    {
        return $this->take('POST', $this->videoUri("/takes/{$takeId}/complete"));
    }

    /** Records a whole take of two chunks and completes it. */
    private function record(string $first = 'one-'): int
    {
        $takeId = (int) $this->startTake()->assertCreated()->json('data.id');
        $this->sendChunk($takeId, 0, $this->webm($first));
        $this->sendChunk($takeId, 1, 'two');
        $this->completeTake($takeId)->assertOk();

        return $takeId;
    }

    /** Sends a chunk without asserting success. */
    private function sendChunkRaw(int $takeId, int $index, string $contents, ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->call(
            'PUT',
            "/api/v1/me/assessment/invitations/{$this->invitationId}/".$this->videoUri("/takes/{$takeId}/chunks/{$index}"),
            [], [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer '.($token ?? $this->seekerToken), 'HTTP_X_ATTEMPT_SESSION' => (string) $this->attemptSession, 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/octet-stream'],
            $contents,
        );
    }
}
