<?php

namespace Tests\Feature\JobAlert;

use Illuminate\Support\Facades\DB;
use Src\JobAlert\Domain\AlertCriteria;
use Src\JobAlert\Domain\AlertFrequency;
use Src\JobAlert\Domain\JobAlert;
use Src\JobAlert\Domain\JobAlertRepository;
use Src\Shared\Application\Job\JobMatcher;
use Src\Shared\Application\Job\LiveJobProfile;
use Src\Shared\Application\Job\LiveJobProfiles;
use Src\Shared\Application\References\TagRegistry;
use Tests\Concerns\BuildsJobAlertFixtures;
use Tests\FeatureTestCase;

/**
 * The alerts found for a job by one SQL statement (`immediateFitting`) must be exactly the alerts whose own search, run through the public job search
 * (`JobMatcher`), finds that job. Every filter, the keyword and the cursor are crossed with jobs that fit and jobs that miss in one way each.
 */
final class ReverseAlertMatchingTest extends FeatureTestCase
{
    use BuildsJobAlertFixtures;

    private int $otherRoleId;

    private int $php;

    private int $go;

    private int $monthly;

    private int $weekly;

    /** @var array<string, int> */
    private array $place;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildJobAlertFixtures();
        $this->otherRoleId = $this->referenceId('reference_job_roles', 'data-analyst');
        $this->php = $this->skillId('php');
        $this->go = $this->skillId('go');
        $this->monthly = $this->referenceId('reference_salary_periods', 'monthly');
        $this->weekly = $this->referenceId('reference_salary_periods', 'weekly');
        $this->place = $this->locationChain();
    }

    protected function tearDown(): void
    {
        $this->tearDownJobAlertFixtures();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $skillIds
     * @param  list<string>  $tags
     */
    private function live(string $title, array $attributes = [], array $skillIds = [], array $tags = []): int
    {
        $id = $this->liveJobFor($title, ['published_at' => '2026-10-05 08:00:00', ...$attributes]);

        foreach ($skillIds as $skillId) {
            DB::table('job_job_skills')->insert(['job_id' => $id, 'skill_id' => $skillId]);
        }

        foreach ($this->app->make(TagRegistry::class)->idsFor($tags) as $tagId) {
            DB::table('job_job_tags')->insert(['job_id' => $id, 'tag_id' => $tagId]);
        }

        return $id;
    }

    /** @param  array<string, mixed>  $criteria */
    private function alert(array $criteria, string $lastRunAt = '2026-10-05 07:00:00', string $frequency = 'immediate', bool $paused = false): int
    {
        $time = new \DateTimeImmutable($lastRunAt, new \DateTimeZone('UTC'));
        $alert = JobAlert::create($this->seekerId, 'Alert', AlertFrequency::from($frequency), AlertCriteria::of($criteria), true, true, $time);
        $id = (int) $this->app->make(JobAlertRepository::class)->add($alert)->id;

        if ($paused) {
            DB::table('jobalert_alerts')->where('id', $id)->update(['paused' => true]);
        }

        return $id;
    }

    /** @return list<int> the alerts the reverse statement finds for the job, walked in small batches */
    private function reverse(int $jobId): array
    {
        $job = $this->app->make(LiveJobProfiles::class)->of($jobId);
        $this->assertInstanceOf(LiveJobProfile::class, $job);

        $repository = $this->app->make(JobAlertRepository::class);
        $found = [];
        $after = 0;

        while (($batch = $repository->immediateFitting($job, $after, 3)) !== []) {
            foreach ($batch as $alert) {
                $after = (int) $alert->id;
                $found[] = $after;
            }
        }

        return $found;
    }

    /** @return list<int> the immediate, active alerts whose own search finds the job after their cursor */
    private function forward(int $jobId): array
    {
        $matcher = $this->app->make(JobMatcher::class);
        $repository = $this->app->make(JobAlertRepository::class);
        $found = [];
        $after = 0;

        while (($batch = $repository->activeAfter(AlertFrequency::Immediate, $after, 50)) !== []) {
            foreach ($batch as $alert) {
                $after = (int) $alert->id;

                if ($matcher->match($alert->criteria->toArray(), $alert->lastRunAt, null, $jobId, 1)->total > 0) {
                    $found[] = $after;
                }
            }
        }

        return $found;
    }

    private function assertSameAlerts(int ...$jobIds): void
    {
        foreach ($jobIds as $jobId) {
            $this->assertSame($this->forward($jobId), $this->reverse($jobId), 'Job '.DB::table('job_jobs')->where('id', $jobId)->value('title'));
        }
    }

    public function test_the_reverse_statement_finds_the_same_alerts_as_the_job_search(): void
    {
        $companyName = (string) DB::table('company_companies')->where('id', $this->companyId)->value('name');

        $everything = $this->live('Senior Backend Developer', [
            'role_id' => $this->roleId, 'province_id' => $this->place['province_id'], 'city_id' => $this->place['city_id'], 'work_mode' => 'remote',
            'salary_min' => 8000000, 'salary_max' => 12000000, 'salary_period_id' => $this->monthly, 'salary_visible' => true,
        ], [$this->php], ['Laravel', 'Remote Friendly']);
        $bare = $this->live('Kasir Toko');
        $hiddenSalary = $this->live('Analyst', ['role_id' => $this->otherRoleId, 'salary_min' => 9000000, 'salary_period_id' => $this->monthly, 'salary_visible' => false]);
        $onlyMinimum = $this->live('Teller', ['salary_min' => 5000000, 'salary_period_id' => $this->monthly, 'salary_visible' => true, 'work_mode' => 'onsite']);
        $accented = $this->live('Staf Administrasi Café');

        $this->alert(['role_id' => [$this->roleId]]);
        $this->alert(['role_id' => [$this->otherRoleId, $this->roleId]]);
        $this->alert(['role_id' => [$this->otherRoleId]]);
        $this->alert(['city_id' => [$this->place['city_id']]]);
        $this->alert(['province_id' => [$this->place['province_id']], 'work_mode' => ['remote', 'hybrid']]);
        $this->alert(['work_mode' => ['onsite']]);
        $this->alert(['skill_id' => [$this->php]]);
        $this->alert(['skill_id' => [$this->go, $this->php]]);
        $this->alert(['skill_id' => [$this->go]]);
        $this->alert(['company_id' => [$this->companyId]]);
        $this->alert(['company_id' => [$this->otherCompanyId]]);
        $this->alert(['salary_min' => 10000000, 'salary_period_id' => $this->monthly]);
        $this->alert(['salary_min' => 12000000, 'salary_period_id' => $this->monthly]);
        $this->alert(['salary_min' => 12000001, 'salary_period_id' => $this->monthly]);
        $this->alert(['salary_min' => 1000000, 'salary_period_id' => $this->weekly]);
        $this->alert(['salary_min' => 5000000, 'salary_period_id' => $this->monthly]);
        $this->alert(['q' => 'backend']);
        $this->alert(['q' => 'BACKEND developer']);
        $this->alert(['q' => 'backend python']);
        $this->alert(['q' => 'laravel']);
        $this->alert(['q' => 'friendly remote']);
        $this->alert(['q' => strtolower(substr($companyName, 0, 4))]);
        $this->alert(['q' => 'cafe']);
        $this->alert(['q' => 'café']);
        $this->alert(['q' => '100%']);
        $this->alert(['q' => 'a_b']);
        $this->alert(['q' => 'senior', 'role_id' => [$this->roleId], 'work_mode' => ['remote'], 'salary_min' => 8000000, 'salary_period_id' => $this->monthly]);
        $this->alert(['q' => 'senior', 'role_id' => [$this->otherRoleId]]);
        $this->alert(['role_id' => [$this->roleId]], '2026-10-05 08:00:00');
        $this->alert(['role_id' => [$this->roleId]], '2026-10-05 07:59:59');
        $this->alert(['role_id' => [$this->roleId]], '2026-10-05 07:00:00', 'daily');
        $this->alert(['role_id' => [$this->roleId]], '2026-10-05 07:00:00', paused: true);

        $this->assertSameAlerts($everything, $bare, $hiddenSalary, $onlyMinimum, $accented);
        $this->assertNotSame([], $this->reverse($everything), 'The fixtures must produce matches, or the comparison proves nothing.');
        $this->assertNotSame([], $this->reverse($accented));
    }

    public function test_a_job_that_is_not_live_has_no_profile(): void
    {
        $draft = $this->insertJob('Draft', 'draft');
        $past = $this->live('Past deadline', ['deadline' => '2026-10-04']);

        $profiles = $this->app->make(LiveJobProfiles::class);

        $this->assertNull($profiles->of($draft));
        $this->assertNull($profiles->of($past));
        $this->assertNull($profiles->of(999999));
    }

    public function test_the_words_of_the_keyword_are_stored_with_the_alert_and_follow_an_update(): void
    {
        $id = $this->alert(['q' => 'Backend  Developer backend']);

        $this->assertSame(['backend', 'developer'], json_decode((string) DB::table('jobalert_alerts')->where('id', $id)->value('keywords'), true));

        $repository = $this->app->make(JobAlertRepository::class);
        $alert = $repository->findById($id);
        $repository->update($alert->revise('Alert', AlertFrequency::Immediate, AlertCriteria::of(['role_id' => [$this->roleId]]), true, true, now()->toDateTimeImmutable()));

        $this->assertSame([], json_decode((string) DB::table('jobalert_alerts')->where('id', $id)->value('keywords'), true));
    }
}
