<?php

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Shared\Application\Recruitment\DefaultStageProvisioner;

/**
 * The job fixtures plus a job seeker with a complete profile and one resume, the company's default stages, and helpers to apply, to create
 * screening questions and to attach them to a job. Storage is faked.
 */
trait BuildsApplicationFixtures
{
    use BuildsJobFixtures;

    protected int $seekerId;

    protected string $seekerToken;

    protected int $resumeId;

    protected function buildApplicationFixtures(): void
    {
        Storage::fake();
        $this->buildJobFixtures();
        $this->app->make(DefaultStageProvisioner::class)->provision($this->companyId);

        $this->seekerId = $this->seeker('siti@example.test');
        $this->seekerToken = $this->tokenFor($this->seekerId);
        $this->resumeId = $this->completeProfile($this->seekerToken);
    }

    protected function tearDownApplicationFixtures(): void
    {
        $this->tearDownJobFixtures();
    }

    protected function seeker(string $email, ?string $verifiedAt = '2026-10-01 00:00:00', string $name = 'Siti Rahma'): int
    {
        return (int) UserModel::query()->create(['type' => 'job_seeker', 'name' => $name, 'email' => $email, 'status' => 'active', 'locale' => 'en', 'email_verified_at' => $verifiedAt])->id;
    }

    protected function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    }

    /** Fills in everything an application needs and returns the id of the uploaded resume. */
    protected function completeProfile(string $token, bool $withResume = true): int
    {
        $this->api('PATCH', '/me/profile', [
            'phone' => '081234567890', 'birth_date' => '1998-05-10', 'gender' => 'female', 'headline' => 'Backend developer',
            ...$this->locationChain(),
        ], $token)->assertOk();
        $this->api('POST', '/me/experiences', ['job_title' => 'Backend Developer', 'company_name' => 'PT Lama', 'start_date' => '2022-03', 'end_date' => '2024-06'], $token)->assertCreated();

        if (! $withResume) {
            return 0;
        }

        $this->app['auth']->forgetGuards();

        return (int) $this->withToken($token)
            ->post('/api/v1/me/resumes', ['file' => UploadedFile::fake()->createWithContent('Siti CV.pdf', $this->pdf())], ['Accept' => 'application/json'])
            ->assertCreated()->json('data.id');
    }

    protected function liveJob(): int
    {
        return $this->insertJob('Backend Developer', 'active', attributes: ['deadline' => '2026-12-31']);
    }

    /** @param  array<string, mixed>  $body */
    protected function apply(int|string $job, array $body = [], ?string $token = null): TestResponse
    {
        return $this->api('POST', "/jobs/{$job}/applications", ['resume_id' => $this->resumeId, ...$body], $token ?? $this->seekerToken);
    }

    /** @param  list<string>  $labels */
    protected function question(string $type, string $text, array $labels = []): int
    {
        $body = ['type' => $type, 'text' => $text];

        if ($labels !== []) {
            $body['options'] = array_map(fn (string $label): array => ['label' => $label], $labels);
        }

        return (int) $this->api('POST', '/company/screening-questions', $body)->assertCreated()->json('data.id');
    }

    protected function optionId(int $questionId, string $label): int
    {
        return (int) DB::table('recruitment_screening_question_options')->where('question_id', $questionId)->where('label', $label)->value('id');
    }

    /** @param  list<array<string, mixed>>  $questions */
    protected function setQuestions(int $jobId, array $questions): void
    {
        $this->api('PUT', "/company/jobs/{$jobId}/screening-questions", ['questions' => $questions])->assertOk();
    }
}
