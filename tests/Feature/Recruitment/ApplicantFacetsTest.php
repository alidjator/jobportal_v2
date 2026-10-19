<?php

namespace Tests\Feature\Recruitment;

use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Src\Recruitment\Domain\ApplicantDataEraser;
use Src\Recruitment\Domain\ExperienceMonths;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-applicant-search.md` section 1: the facets copied from the snapshot when an application is made, and the command that builds the missing ones. */
final class ApplicantFacetsTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    private int $applicationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function facets(int $applicationId): ?object
    {
        return DB::table('recruitment_application_facets')->where('application_id', $applicationId)->first();
    }

    /** @return array<string, mixed> */
    private function snapshot(int $applicationId): array
    {
        return json_decode((string) DB::table('recruitment_applications')->where('id', $applicationId)->value('profile_snapshot'), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param  array<string, mixed>  $changes */
    private function changeSnapshot(int $applicationId, array $changes): void
    {
        DB::table('recruitment_applications')->where('id', $applicationId)->update(['profile_snapshot' => json_encode([...$this->snapshot($applicationId), ...$changes])]);
    }

    private function build(): void
    {
        $this->artisan('recruitment:build-application-facets')->assertSuccessful();
    }

    public function test_applying_stores_the_facets_of_the_snapshot_and_the_snapshot_carries_the_location_ids(): void
    {
        $chain = $this->locationChain();
        $snapshot = $this->snapshot($this->applicationId);
        $this->assertSame($chain['province_id'], $snapshot['location']['province_id']);
        $this->assertSame($chain['city_id'], $snapshot['location']['city_id']);

        $facets = $this->facets($this->applicationId);
        $this->assertNotNull($facets);
        $this->assertSame($chain['province_id'], (int) $facets->province_id);
        $this->assertSame($chain['city_id'], (int) $facets->city_id);
        $this->assertSame($this->companyId, (int) $facets->company_id);
        $this->assertSame($this->job, (int) $facets->job_id);
        // March 2022 to June 2024, both months included.
        $this->assertSame(28, (int) $facets->experience_months);
        $this->assertNull($facets->education_rank);
        $this->assertNull($facets->expected_salary_monthly);
        $this->assertSame('{}', $facets->skill_ids);
        $this->assertFalse((bool) $facets->from_invitation);
        $this->assertFalse((bool) $facets->returning);

        foreach (['Backend developer', 'PT Lama', 'BANDUNG'] as $word) {
            $this->assertStringContainsString($word, $facets->search_text);
        }

        // No contact data and no name in the searchable text.
        $this->assertStringNotContainsString('siti@example.test', $facets->search_text);
        $this->assertStringNotContainsString('081234567890', $facets->search_text);
    }

    public function test_a_second_application_to_the_same_company_is_returning(): void
    {
        $other = $this->insertJob('Frontend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        Carbon::setTestNow('2026-10-06 09:00:00');
        $second = (int) $this->apply($other)->assertCreated()->json('data.id');

        $this->assertFalse((bool) $this->facets($this->applicationId)->returning);
        $this->assertTrue((bool) $this->facets($second)->returning);
    }

    public function test_the_command_builds_the_missing_facets_from_the_stored_snapshots_and_runs_again_safely(): void
    {
        $bachelor = $this->referenceId('reference_education_levels', 'bachelor', ['rank' => 5]);
        $master = $this->referenceId('reference_education_levels', 'master', ['rank' => 6]);
        $monthly = $this->referenceId('reference_salary_periods', 'monthly');
        $php = $this->skillId('php');

        $this->changeSnapshot($this->applicationId, [
            'expected_salary' => ['amount' => 9_000_000, 'period' => ['id' => $monthly, 'name' => ['id' => 'Bulanan', 'en' => 'Monthly']]],
            'educations' => [
                ['level' => ['id' => $bachelor], 'institution' => 'Institut Teknologi Bandung', 'field_of_study' => 'Informatika'],
                ['level' => ['id' => $master], 'institution' => 'Universitas Indonesia', 'field_of_study' => null],
            ],
            'skills' => [['id' => $php, 'name' => ['id' => 'PHP', 'en' => 'PHP']]],
        ]);
        DB::table('recruitment_application_facets')->where('application_id', $this->applicationId)->delete();
        DB::table('recruitment_applications')->where('id', $this->applicationId)->update(['source' => 'invitation']);

        $this->artisan('recruitment:build-application-facets')->expectsOutput('Built the facets of 1 application.')->assertSuccessful();

        $facets = $this->facets($this->applicationId);
        $this->assertSame(6, (int) $facets->education_rank);
        $this->assertSame(9_000_000, (int) $facets->expected_salary_monthly);
        $this->assertSame('{'.$php.'}', $facets->skill_ids);
        $this->assertTrue((bool) $facets->from_invitation);
        $this->assertStringContainsString('Institut Teknologi Bandung', $facets->search_text);
        $this->assertStringContainsString('Informatika', $facets->search_text);

        $this->artisan('recruitment:build-application-facets')->expectsOutput('Built the facets of 0 applications.')->assertSuccessful();
    }

    public function test_only_a_salary_given_per_month_is_kept_and_an_old_snapshot_gives_no_location(): void
    {
        $hourly = $this->referenceId('reference_salary_periods', 'hourly');
        $snapshot = $this->snapshot($this->applicationId);
        unset($snapshot['location']['province_id'], $snapshot['location']['city_id']);
        $this->changeSnapshot($this->applicationId, ['location' => $snapshot['location'], 'expected_salary' => ['amount' => 50_000, 'period' => ['id' => $hourly]]]);
        DB::table('recruitment_application_facets')->where('application_id', $this->applicationId)->delete();

        $this->build();

        $facets = $this->facets($this->applicationId);
        $this->assertNull($facets->expected_salary_monthly);
        $this->assertNull($facets->province_id);
        $this->assertNull($facets->city_id);
    }

    public function test_the_command_skips_an_application_without_a_snapshot(): void
    {
        DB::table('recruitment_application_facets')->where('application_id', $this->applicationId)->delete();
        DB::table('recruitment_applications')->where('id', $this->applicationId)->update(['profile_snapshot' => null]);

        $this->artisan('recruitment:build-application-facets')->expectsOutput('Built the facets of 0 applications.')->assertSuccessful();
        $this->assertNull($this->facets($this->applicationId));
    }

    public function test_anonymising_the_applicant_deletes_the_facets(): void
    {
        $this->assertNotNull($this->facets($this->applicationId));

        $this->app->make(ApplicantDataEraser::class)->clearPersonalData($this->seekerId);

        $this->assertNull($this->facets($this->applicationId));
    }

    public function test_months_worked_count_shared_months_once_and_an_open_entry_runs_to_today(): void
    {
        $today = new DateTimeImmutable('2026-10-15');

        $this->assertSame(0, ExperienceMonths::of([], $today));
        // Jan to Jun 2020 (6 months), and Apr to Sep 2020 overlapping it: Jan to Sep is 9 months.
        $this->assertSame(9, ExperienceMonths::of([['start' => '2020-01', 'end' => '2020-06'], ['start' => '2020-04', 'end' => '2020-09']], $today));
        // Open entry: August 2026 to October 2026 is 3 months; an entry marked current ignores its end.
        $this->assertSame(3, ExperienceMonths::of([['start' => '2026-08', 'end' => null]], $today));
        $this->assertSame(3, ExperienceMonths::of([['start' => '2026-08', 'end' => '2026-08', 'is_current' => true]], $today));
        // An entry that ends before it starts counts nothing.
        $this->assertSame(0, ExperienceMonths::of([['start' => '2020-06', 'end' => '2020-01']], $today));
    }
}
