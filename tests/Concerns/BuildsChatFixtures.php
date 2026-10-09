<?php

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

/**
 * The application fixtures plus a live job assigned to the first recruiter that the seeker applied to, and helpers to start conversations and send
 * messages with files (multipart) on either side.
 */
trait BuildsChatFixtures
{
    use BuildsApplicationFixtures;

    protected int $job;

    protected int $applicationId;

    protected function buildChatFixtures(): void
    {
        $this->buildApplicationFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');
    }

    protected function tearDownChatFixtures(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();
    }

    protected function png(): string
    {
        return "\x89PNG\r\n\x1A\n".str_repeat("\0", 32);
    }

    protected function jpeg(): string
    {
        return "\xFF\xD8\xFF\xE0".str_repeat("\0", 32);
    }

    protected function upload(string $name, string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    /** @param  list<UploadedFile>  $files */
    protected function say(int $conversationId, ?string $body, string $token, array $files = [], string $side = 'company'): TestResponse
    {
        return $this->multipartWithFiles('POST', "/{$this->prefix($side)}/conversations/{$conversationId}/messages", $body === null ? [] : ['body' => $body], $files, $token);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $files
     */
    protected function multipartWithFiles(string $method, string $uri, array $data, array $files, string $token): TestResponse
    {
        $this->app['auth']->forgetGuards();

        // `call` ignores the default headers that `withToken` sets, so the credentials go in the server variables.
        return $this->call($method, '/api/v1'.$uri, $data, [], $files === [] ? [] : ['files' => $files], ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token]);
    }

    protected function prefix(string $side): string
    {
        return $side === 'company' ? 'company' : 'me';
    }

    /** The seeker writes first (they applied, so they may). Returns the conversation id. */
    protected function seekerStarts(string $body = 'Hello, is the job still open?', ?string $token = null): int
    {
        return (int) $this->multipartWithFiles('POST', '/me/conversations', ['company_id' => $this->companyId, 'body' => $body], [], $token ?? $this->seekerToken)->assertCreated()->json('data.id');
    }

    /** A member writes first to a job seeker. */
    protected function companyStarts(int $jobSeekerId, string $body = 'We would like to talk.', ?string $token = null): TestResponse
    {
        return $this->multipartWithFiles('POST', '/company/conversations', ['user_id' => $jobSeekerId, 'body' => $body], [], $token ?? $this->recruiterToken);
    }
}
