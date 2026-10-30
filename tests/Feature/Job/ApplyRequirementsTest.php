<?php

namespace Tests\Feature\Job;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Recruitment\Application\RecruitmentSettings;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/**
 * `docs/design/phase-8-job-posting.md` sections 5 and 6: the extra profile items a job asks for, what an application answers when one is missing, and the apply state of a job
 * seeker. The clock is frozen at 09:00 on 2026-10-05.
 */
final class ApplyRequirementsTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $english;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->english = $this->referenceId('reference_languages', 'english', ['code' => 'en']);
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    // ----- helpers -----

    /** A live job that asks for these items. @param  list<array<string, mixed>>  $requirements */
    private function jobAsking(array $requirements, string $title = 'Backend Developer', array $attributes = []): int
    {
        return $this->insertJob($title, 'active', attributes: ['deadline' => '2026-12-31', 'apply_requirements' => json_encode($requirements), ...$attributes]);
    }

    private function state(int $job, ?string $token = null): TestResponse
    {
        return $this->api('GET', "/me/jobs/{$job}/apply-state", token: $token ?? $this->seekerToken);
    }

    /** @param  array<string, mixed>  $body */
    private function profile(array $body): void
    {
        $this->api('PATCH', '/me/profile', $body, $this->seekerToken)->assertOk();
    }

    /** @var list<array{type: string, url: string}> */
    private array $links = [];

    private function link(string $type, string $url): void
    {
        $this->links[] = ['type' => $type, 'url' => $url];
        $this->api('PUT', '/me/links', ['links' => $this->links], $this->seekerToken)->assertOk();
    }

    private function speaks(string $proficiency): void
    {
        $this->api('PUT', '/me/languages', ['languages' => [['language_id' => $this->english, 'proficiency' => $proficiency]]], $this->seekerToken)->assertOk();
    }

    // ----- the job form -----

    public function test_a_job_keeps_its_requirements_and_a_patch_changes_or_clears_them(): void
    {
        $id = (int) $this->api('POST', '/company/jobs', [...$this->fullJobBody(), 'apply_requirements' => [
            ['item' => 'portfolio'], ['item' => 'language', 'language_id' => $this->english, 'min_proficiency' => 'fluent'],
        ]])->assertCreated()->json('data.id');

        $expected = [['item' => 'portfolio'], ['item' => 'language', 'language_id' => $this->english, 'min_proficiency' => 'fluent']];
        $this->api('GET', "/company/jobs/{$id}")->assertOk()->assertJsonPath('data.apply_requirements', $expected);

        $this->api('PATCH', "/company/jobs/{$id}", ['title' => 'Another title'])->assertOk()->assertJsonPath('data.apply_requirements', $expected);
        $this->api('PATCH', "/company/jobs/{$id}", ['apply_requirements' => [['item' => 'whatsapp']]])->assertOk()->assertJsonPath('data.apply_requirements', [['item' => 'whatsapp']]);
        $this->api('PATCH', "/company/jobs/{$id}", ['apply_requirements' => null])->assertOk()->assertJsonPath('data.apply_requirements', []);
        $this->api('PATCH', "/company/jobs/{$id}", ['apply_requirements' => []])->assertOk()->assertJsonPath('data.apply_requirements', []);
    }

    public function test_a_clone_carries_the_requirements(): void
    {
        $id = $this->jobAsking([['item' => 'linkedin']]);

        $copy = (int) $this->api('POST', "/company/jobs/{$id}/clone")->assertSuccessful()->json('data.id');

        $this->api('GET', "/company/jobs/{$copy}")->assertOk()->assertJsonPath('data.apply_requirements', [['item' => 'linkedin']]);
    }

    public function test_an_unknown_repeated_or_unusable_requirement_is_refused(): void
    {
        $id = $this->insertJob('Backend Developer', 'draft');
        $language = ['item' => 'language', 'language_id' => $this->english, 'min_proficiency' => 'fluent'];
        $inactive = $this->referenceId('reference_languages', 'klingon', ['code' => 'tlh', 'is_active' => false]);
        DB::table('reference_languages')->where('id', $inactive)->update(['is_active' => false]);

        $bad = [
            [['item' => 'gender']],
            [['item' => 'portfolio'], ['item' => 'portfolio']],
            [$language, $language],
            [['item' => 'language', 'language_id' => $this->english]],
            [['item' => 'language', 'language_id' => $this->english, 'min_proficiency' => 'expert']],
            [['item' => 'language', 'language_id' => $inactive, 'min_proficiency' => 'basic']],
            array_map(fn (int $number): array => ['item' => 'language', 'language_id' => $this->referenceId('reference_languages', "lang-{$number}", ['code' => "l{$number}"]), 'min_proficiency' => 'basic'], [1, 2, 3, 4]),
        ];

        foreach ($bad as $requirements) {
            $this->api('PATCH', "/company/jobs/{$id}", ['apply_requirements' => $requirements])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-apply-requirements')->assertJsonStructure(['errors' => ['apply_requirements']]);
        }

        $this->api('PATCH', "/company/jobs/{$id}", ['apply_requirements' => [['item' => 'portfolio'], $language]])->assertOk();
        $this->assertNotNull(DB::table('job_jobs')->where('id', $id)->value('apply_requirements'));
    }

    public function test_a_language_the_job_already_asks_for_may_be_deactivated_later(): void
    {
        // A draft: a live job that was not completed would be refused for what it lacks, whatever the requirements say.
        $id = $this->insertJob('Backend Developer', 'draft', attributes: ['apply_requirements' => json_encode([['item' => 'language', 'language_id' => $this->english, 'min_proficiency' => 'fluent']])]);
        DB::table('reference_languages')->where('id', $this->english)->update(['is_active' => false]);

        $this->api('PATCH', "/company/jobs/{$id}", ['apply_requirements' => [['item' => 'language', 'language_id' => $this->english, 'min_proficiency' => 'fluent']]])->assertOk();
    }

    public function test_the_public_page_lists_the_requirements_with_the_language_by_name(): void
    {
        $id = $this->jobAsking([['item' => 'portfolio'], ['item' => 'language', 'language_id' => $this->english, 'min_proficiency' => 'fluent']]);

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        $this->getJson("/api/v1/jobs/{$id}")->assertOk()->assertJsonPath('data.apply_requirements', [
            ['item' => 'portfolio'], ['item' => 'language', 'language' => ['id' => $this->english, 'name' => 'english'], 'min_proficiency' => 'fluent'],
        ]);

        $none = $this->liveJob();
        $this->getJson("/api/v1/jobs/{$none}")->assertOk()->assertJsonPath('data.apply_requirements', []);
    }

    // ----- applying -----

    public function test_an_application_needs_every_item_and_names_the_ones_that_are_missing(): void
    {
        $job = $this->jobAsking([
            ['item' => 'expected_salary'], ['item' => 'portfolio'], ['item' => 'linkedin'], ['item' => 'whatsapp'], ['item' => 'language', 'language_id' => $this->english, 'min_proficiency' => 'fluent'],
        ]);

        $this->apply($job)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:profile-incomplete')
            ->assertJsonPath('missing', ['expected_salary', 'portfolio', 'linkedin', 'whatsapp', "language:{$this->english}"]);

        $this->profile(['expected_salary' => 8000000, 'expected_salary_period_id' => $this->referenceId('reference_salary_periods', 'monthly'), 'whatsapp' => '081234567890']);
        $this->link('github', 'https://github.com/siti');
        $this->speaks('conversational');

        $this->apply($job)->assertStatus(422)->assertJsonPath('missing', ['linkedin', "language:{$this->english}"]);

        $this->link('linkedin', 'https://www.linkedin.com/in/siti');
        $this->speaks('native');

        $this->apply($job)->assertCreated();
    }

    public function test_an_expected_salary_without_its_period_does_not_count_and_a_job_without_requirements_asks_for_nothing(): void
    {
        $job = $this->jobAsking([['item' => 'expected_salary']]);
        // The profile form refuses an amount without its period, so this state comes only from older data.
        DB::table('jobseeker_profiles')->where('user_id', $this->seekerId)->update(['expected_salary' => 8000000, 'expected_salary_period_id' => null]);

        $this->apply($job)->assertStatus(422)->assertJsonPath('missing', ['expected_salary']);
        $this->apply($this->liveJob())->assertCreated();
    }

    // ----- the apply state -----

    public function test_the_state_lists_what_is_missing_and_turns_to_can_apply_once_the_profile_meets_the_job(): void
    {
        $job = $this->jobAsking([['item' => 'portfolio']]);

        $response = $this->state($job)->assertOk();
        $this->assertSame(['can_apply' => false, 'reasons' => [['code' => 'profile-incomplete', 'missing' => ['portfolio']]], 'application' => null], [
            'can_apply' => $response->json('data.can_apply'), 'reasons' => $response->json('data.reasons'), 'application' => $response->json('data.application'),
        ]);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->link('portfolio', 'https://siti.example.test/work');

        $this->state($job)->assertOk()->assertJsonPath('data.can_apply', true)->assertJsonPath('data.reasons', [])->assertJsonPath('data.application', null);
    }

    public function test_an_unverified_email_comes_first_and_a_stranger_with_no_profile_misses_the_basics(): void
    {
        $token = $this->tokenFor($this->seeker('baru@example.test', null, 'Orang Baru'));

        $reasons = $this->state($this->liveJob(), $token)->assertOk()->json('data.reasons');

        $this->assertSame(['email-not-verified', 'profile-incomplete'], array_column($reasons, 'code'));
        $this->assertContains('phone', $reasons[1]['missing']);
    }

    public function test_the_state_after_applying_shows_the_application_and_the_waiting_period_of_a_rejection(): void
    {
        $job = $this->liveJob();
        $id = (int) $this->apply($job)->assertCreated()->json('data.id');

        $state = $this->state($job)->assertOk();
        $this->assertSame([['code' => 'already-applied']], $state->json('data.reasons'));
        $this->assertSame([$id, 'applied'], [$state->json('data.application.id'), $state->json('data.application.status')]);

        DB::table('job_jobs')->where('id', $job)->update(['reapply_after_days' => 30]);
        DB::table('recruitment_applications')->where('id', $id)->update(['stage_type' => 'rejected', 'stage_entered_at' => '2026-10-01 00:00:00']);

        $this->state($job)->assertOk()->assertJsonPath('data.reasons', [['code' => 'already-applied', 'reapply_on' => '2026-10-31']])->assertJsonPath('data.can_apply', false);

        DB::table('recruitment_applications')->where('id', $id)->update(['stage_entered_at' => '2026-08-01 00:00:00']);

        $this->state($job)->assertOk()->assertJsonPath('data.can_apply', true)->assertJsonPath('data.reasons', [])->assertJsonPath('data.application.status', 'rejected');
    }

    public function test_the_daily_limit_and_a_company_that_cannot_take_applications_are_reasons(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal(RecruitmentSettings::MAX_APPLICATIONS_PER_DAY, 2, null);
        $this->state($this->liveJob())->assertOk()->assertJsonPath('data.applications_left_today', 2);

        $this->apply($this->liveJob())->assertCreated();
        $this->state($this->insertJob('Second Job', 'active', attributes: ['deadline' => '2026-12-31']))->assertOk()->assertJsonPath('data.applications_left_today', 1);

        $this->apply($this->insertJob('Third Job', 'active', attributes: ['deadline' => '2026-12-31']))->assertCreated();
        $fourth = $this->insertJob('Fourth Job', 'active', attributes: ['deadline' => '2026-12-31']);
        $this->state($fourth)->assertOk()->assertJsonPath('data.applications_left_today', 0)->assertJsonPath('data.reasons', [['code' => 'application-limit-reached']]);

        DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'applied')->update(['is_default' => false]);
        $this->assertSame(['application-limit-reached', 'applications-unavailable'], array_column($this->state($fourth)->json('data.reasons'), 'code'));
    }

    public function test_only_a_job_seeker_reads_the_state_of_a_live_job(): void
    {
        $job = $this->liveJob();

        $this->state($job, $this->adminToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:job-seeker-only');
        $this->state(999999)->assertNotFound();
        $this->state($this->insertJob('Closed Job', 'closed'))->assertNotFound();
        $this->state($this->insertJob('Draft Job', 'draft'))->assertNotFound();

        $held = $this->insertJob('Held Job', 'active', attributes: ['deadline' => '2026-12-31', 'report_hold_at' => now()]);
        $this->state($held)->assertNotFound();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson("/api/v1/me/jobs/{$job}/apply-state")->assertUnauthorized();
    }
}
