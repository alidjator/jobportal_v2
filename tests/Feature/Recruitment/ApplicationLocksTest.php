<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Facades\DB;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

final class ApplicationLocksTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /** A job that has one application and is back to a draft, so it can be edited without the rules of a live job. */
    private function jobWithApplication(): int
    {
        $job = $this->insertJob('Backend Developer', 'active', attributes: [
            'deadline' => '2026-12-31',
            'category_id' => $this->referenceId('reference_job_categories', 'engineering'),
            'role_id' => $this->referenceId('reference_job_roles', 'backend-developer'),
            'job_type_id' => $this->referenceId('reference_job_types', 'full-time'),
            'job_level_id' => $this->referenceId('reference_job_levels', 'senior'),
        ]);
        $this->apply($job)->assertCreated();
        DB::table('job_jobs')->where('id', $job)->update(['status' => 'draft']);

        return $job;
    }

    // ----- the lock on the position of a job -----

    public function test_a_job_with_applications_cannot_change_its_title_category_role_type_or_level(): void
    {
        $job = $this->jobWithApplication();

        $this->api('PATCH', "/company/jobs/{$job}", ['title' => 'Frontend Developer'])->assertStatus(409)
            ->assertJsonPath('type', 'urn:jobportal:error:job-locked-after-applications')->assertJsonPath('fields', ['title']);

        $this->api('PATCH', "/company/jobs/{$job}", [
            'category_id' => $this->referenceId('reference_job_categories', 'design'),
            'job_level_id' => $this->referenceId('reference_job_levels', 'junior'),
        ])->assertStatus(409)->assertJsonPath('fields', ['category_id', 'job_level_id']);

        $this->api('PATCH', "/company/jobs/{$job}", ['role_id' => $this->referenceId('reference_job_roles', 'designer')])->assertStatus(409)->assertJsonPath('fields', ['role_id']);
        $this->api('PATCH', "/company/jobs/{$job}", ['job_type_id' => $this->referenceId('reference_job_types', 'contract')])->assertStatus(409)->assertJsonPath('fields', ['job_type_id']);

        $this->assertSame('Backend Developer', $this->jobRow($job)->title);
    }

    public function test_the_other_fields_stay_editable_and_a_title_sent_unchanged_is_not_a_change(): void
    {
        $job = $this->jobWithApplication();

        $this->api('PATCH', "/company/jobs/{$job}", ['title' => 'Backend Developer', 'description' => str_repeat('We changed the text. ', 5), 'vacancies' => 7])->assertOk();

        $this->assertSame(7, (int) $this->jobRow($job)->vacancies);
    }

    public function test_a_job_without_applications_can_still_change_everything(): void
    {
        $withApplication = $this->jobWithApplication();
        $other = $this->insertJob('Other job', 'draft');

        $this->api('PATCH', "/company/jobs/{$other}", ['title' => 'Renamed job'])->assertOk();
        $this->assertSame('Renamed job', $this->jobRow($other)->title);
        $this->assertSame('Backend Developer', $this->jobRow($withApplication)->title);
    }

    public function test_a_withdrawn_application_still_locks_the_job(): void
    {
        $job = $this->jobWithApplication();
        $this->api('POST', '/me/applications/'.DB::table('recruitment_applications')->value('id').'/withdraw', token: $this->seekerToken)->assertNoContent();

        $this->api('PATCH', "/company/jobs/{$job}", ['title' => 'Frontend Developer'])->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:job-locked-after-applications');
    }

    // ----- deleting an extra stage -----

    public function test_deleting_an_extra_stage_moves_its_applications_to_the_default_stage_of_the_same_type(): void
    {
        $job = $this->insertJob('Backend Developer', 'active', attributes: ['deadline' => '2026-12-31']);
        $id = (int) $this->apply($job)->assertCreated()->json('data.id');

        $extra = (int) $this->api('POST', '/company/stages', ['name' => 'HR interview', 'type' => 'interview'])->assertCreated()->json('data.id');
        $default = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'interview')->where('is_default', 1)->value('id');
        DB::table('recruitment_applications')->where('id', $id)->update(['stage_id' => $extra, 'stage_type' => 'interview']);
        DB::table('recruitment_application_stage_history')->insert([
            'application_id' => $id, 'from_stage_id' => 1, 'to_stage_id' => $extra, 'from_type' => 'applied', 'to_type' => 'interview',
            'actor_kind' => 'recruiter', 'actor_id' => $this->recruiterId, 'created_at' => now(),
        ]);

        $this->api('DELETE', "/company/stages/{$extra}")->assertNoContent();

        $row = DB::table('recruitment_applications')->where('id', $id)->first();
        $this->assertSame($default, (int) $row->stage_id);
        $this->assertSame('interview', $row->stage_type);

        $last = DB::table('recruitment_application_stage_history')->where('application_id', $id)->orderByDesc('id')->first();
        $this->assertSame([$extra, $default, 'interview', 'interview', 'system', null], [(int) $last->from_stage_id, (int) $last->to_stage_id, $last->from_type, $last->to_type, $last->actor_kind, $last->actor_id]);

        // The applicant sees the type once, not twice: moving between stages of one type is housekeeping.
        $history = $this->api('GET', "/me/applications/{$id}", token: $this->seekerToken)->assertOk()->json('data.history');
        $this->assertSame(['applied', 'interview'], array_column($history, 'status'));
    }

    public function test_only_the_applications_of_the_deleted_stage_move(): void
    {
        $first = $this->insertJob('First job', 'active', attributes: ['deadline' => '2026-12-31']);
        $second = $this->insertJob('Second job', 'active', attributes: ['deadline' => '2026-12-31']);
        $moved = (int) $this->apply($first)->assertCreated()->json('data.id');
        $stays = (int) $this->apply($second)->assertCreated()->json('data.id');
        $applied = (int) DB::table('recruitment_applications')->where('id', $stays)->value('stage_id');

        $extra = (int) $this->api('POST', '/company/stages', ['name' => 'Phone screen', 'type' => 'screening'])->assertCreated()->json('data.id');
        DB::table('recruitment_applications')->where('id', $moved)->update(['stage_id' => $extra, 'stage_type' => 'screening']);

        $this->api('DELETE', "/company/stages/{$extra}")->assertNoContent();

        $this->assertSame($applied, (int) DB::table('recruitment_applications')->where('id', $stays)->value('stage_id'));
        $this->assertSame(0, DB::table('recruitment_application_stage_history')->where('application_id', $stays)->where('actor_kind', 'system')->count());
        $this->assertSame(1, DB::table('recruitment_application_stage_history')->where('application_id', $moved)->where('actor_kind', 'system')->count());
    }

    // ----- the notification preference -----

    public function test_the_application_update_preference_is_offered_for_email_and_on_by_default(): void
    {
        $response = $this->api('GET', '/me/notification-preferences', token: $this->seekerToken)->assertOk();

        $entry = collect($response->json('data'))->firstWhere('key', 'application_update');
        $this->assertNotNull($entry);
        $this->assertSame(['email' => ['enabled' => true, 'is_default' => true]], $entry['channels']);

        $this->api('PUT', '/me/notification-preferences/application_update/email', ['enabled' => false], $this->seekerToken)->assertNoContent();
        $this->api('PUT', '/me/notification-preferences/application_update/push', ['enabled' => true], $this->seekerToken)->assertStatus(422);

        $preferences = $this->app->make(NotificationPreferences::class);
        $this->assertFalse($preferences->isEnabled($this->seekerId, 'application_update', NotificationChannel::Email));
    }
}
