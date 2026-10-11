<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Platform\Application\Settings\SettingsStore;

/**
 * The job fixtures (a company with an admin and recruiters, another company) plus a provider with a psychologist, a second provider with its own
 * psychologist, and an admin who holds both assessment permissions. Helpers call the three authoring areas: the company's, the provider's and the
 * library's.
 */
trait BuildsAssessmentFixtures
{
    use BuildsJobFixtures;

    protected int $providerId;

    protected int $otherProviderId;

    protected string $psychologistToken;

    protected string $otherPsychologistToken;

    protected int $assessmentAdminId;

    protected string $assessmentAdminToken;

    protected function buildAssessmentFixtures(): void
    {
        $this->buildJobFixtures();
        $this->addAssessmentFixtures();
    }

    /** The provider, psychologist and admin fixtures alone, for a test that builds the job fixtures another way (for example with applicants). */
    protected function addAssessmentFixtures(): void
    {
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');

        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => 'ops@example.test', 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo(['assessment_library.manage', 'assessment_providers.manage']);
        $this->assessmentAdminId = (int) $admin->id;
        $this->assessmentAdminToken = $admin->createToken('console', ['*'], now()->addHour())->plainTextToken;

        [$this->providerId, $this->psychologistToken] = $this->providerWithPsychologist('Lembaga Psikologi Contoh', 'dewi@example.test');
        [$this->otherProviderId, $this->otherPsychologistToken] = $this->providerWithPsychologist('Biro Psikologi Lain', 'wulan@example.test');
    }

    /** @return array{0: int, 1: string} the provider id and the psychologist's token */
    protected function providerWithPsychologist(string $name, string $email): array
    {
        $providerId = (int) DB::table('assessment_providers')->insertGetId(['name' => $name, 'legal_name' => 'PT '.$name, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $user = UserModel::query()->create(['type' => 'psychologist', 'name' => 'Psikolog '.$name, 'email' => $email, 'status' => 'active', 'locale' => 'id', 'email_verified_at' => '2026-10-01 00:00:00']);
        DB::table('assessment_psychologists')->insert([
            'user_id' => $user->id, 'provider_id' => $providerId, 'str_number' => 'STR-1', 'str_valid_until' => '2030-01-31',
            'silp_number' => 'SIPP-1', 'silp_valid_until' => '2030-01-31', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$providerId, $user->createToken('provider', ['*'], now()->addHour())->plainTextToken];
    }

    /** @param  array<string, mixed>  $body */
    protected function company(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        return $this->api($method, '/company/assessment/'.$uri, $body, $token ?? $this->adminToken);
    }

    /** @param  array<string, mixed>  $body */
    protected function provider(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        return $this->api($method, '/provider/'.$uri, $body, $token ?? $this->psychologistToken);
    }

    /** @param  array<string, mixed>  $body */
    protected function library(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        return $this->api($method, '/admin/assessment/library/'.$uri, $body, $token ?? $this->assessmentAdminToken);
    }

    /** @param  array<string, mixed>  $body */
    protected function assessmentAdmin(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        return $this->api($method, '/admin/assessment/'.$uri, $body, $token ?? $this->assessmentAdminToken);
    }

    protected function overrideSetting(string $key, int|bool $value, ?int $companyId = null): void
    {
        $companyId ??= $this->companyId;

        $this->app->make(SettingsStore::class)->setCompanyOverride($key, $companyId, $value, $this->assessmentAdminId);
    }

    /** @return array<string, mixed> */
    protected function section(string $title = 'Basics', int $seconds = 600, array $extra = []): array
    {
        return ['title' => $title, 'format' => 'questions', 'duration_seconds' => $seconds, ...$extra];
    }

    /** @return array<string, mixed> a single-choice question whose right answer is the first option */
    protected function choice(string $text = 'Which is a prime number?', array $extra = []): array
    {
        return [
            'type' => 'single_choice', 'text' => $text, 'points' => 2,
            'options' => [['label' => '7', 'is_correct' => true], ['label' => '8'], ['label' => '9']],
            ...$extra,
        ];
    }

    /**
     * Creates a test through the given caller and returns its id; the caller is one of `company`, `provider` or `library`.
     *
     * @param  array<string, mixed>  $extra
     */
    protected function newTest(string $area = 'company', array $extra = []): int
    {
        return (int) $this->{$area}('POST', 'tests', ['title' => 'Logic and numbers', 'measured_capabilities' => ['Logic'], ...$extra])->assertCreated()->json('data.id');
    }

    /** Adds a section with one question to the test and returns the section id. */
    protected function sectionWithQuestion(string $area, int $testId, ?array $question = null): int
    {
        $sectionId = (int) $this->{$area}('POST', "tests/{$testId}/sections", $this->section())->assertCreated()->json('data.id');
        $this->{$area}('POST', "sections/{$sectionId}/questions", $question ?? $this->choice())->assertCreated();

        return $sectionId;
    }
}
