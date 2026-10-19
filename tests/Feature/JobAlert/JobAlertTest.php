<?php

namespace Tests\Feature\JobAlert;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Src\JobAlert\Application\JobAlertSettings;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\BuildsJobAlertFixtures;
use Tests\FeatureTestCase;

final class JobAlertTest extends FeatureTestCase
{
    use BuildsJobAlertFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildJobAlertFixtures();
    }

    protected function tearDown(): void
    {
        $this->tearDownJobAlertFixtures();

        parent::tearDown();
    }

    public function test_only_a_signed_in_job_seeker_has_alerts(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson('/api/v1/me/job-alerts')->assertUnauthorized();

        foreach ([['GET', ''], ['POST', '']] as [$method, $suffix]) {
            $this->api($method, '/me/job-alerts'.$suffix, $this->alertBody(), $this->adminToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:job-seeker-only');
        }

        $id = $this->alertId();
        $this->api('GET', "/me/job-alerts/{$id}", token: $this->adminToken)->assertForbidden();
        $this->api('GET', "/me/job-alerts/{$id}/jobs", token: $this->adminToken)->assertForbidden();
    }

    public function test_an_alert_is_created_with_its_defaults_and_the_criteria_are_stored_clean(): void
    {
        $response = $this->createAlert(['name' => '  Backend   jobs ', 'frequency' => 'daily', 'criteria' => [
            'q' => '  php   laravel ', 'role_id' => [$this->roleId, $this->roleId, 9], 'work_mode' => ['remote', 'remote'], 'city_id' => [], 'salary_min' => 8000000, 'salary_period_id' => 2,
        ]])->assertCreated();

        $this->assertSame([
            'name' => 'Backend jobs', 'frequency' => 'daily',
            'criteria' => ['q' => 'php laravel', 'role_id' => [$this->roleId, 9], 'work_mode' => ['remote'], 'salary_min' => 8000000, 'salary_period_id' => 2],
            'email' => true, 'in_app' => true, 'paused' => false,
            'created_at' => '2026-10-05T09:00:00+00:00', 'updated_at' => '2026-10-05T09:00:00+00:00',
        ], collect($response->json('data'))->except('id')->all());

        $row = DB::table('jobalert_alerts')->find($response->json('data.id'));
        $this->assertSame([$this->seekerId, '2026-10-05 09:00:00'], [(int) $row->user_id, $row->last_run_at]);
    }

    public function test_the_shape_of_the_body_is_checked(): void
    {
        $bad = [
            ['name' => ''], ['name' => str_repeat('a', 101)], ['frequency' => 'hourly'], ['frequency' => null], ['criteria' => []], ['criteria' => 'php'],
            ['criteria' => ['unknown' => [1]]], ['criteria' => ['role_id' => 'one']], ['criteria' => ['role_id' => ['x']]], ['criteria' => ['role_id' => [0]]],
            ['criteria' => ['role_id' => range(1, 21)]], ['criteria' => ['work_mode' => ['moon']]], ['criteria' => ['q' => str_repeat('a', 101)]],
            ['criteria' => ['salary_min' => -1, 'salary_period_id' => 1]], ['criteria' => ['salary_min' => 'a', 'salary_period_id' => 1]], ['email' => 'maybe'],
        ];

        foreach ($bad as $override) {
            $this->createAlert($override)->assertStatus(422);
        }

        $this->assertSame(0, DB::table('jobalert_alerts')->count());
    }

    public function test_the_rules_of_an_alert_are_enforced_with_their_own_codes(): void
    {
        $this->createAlert(['criteria' => ['role_id' => [], 'q' => '  ']])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:criteria-required');
        $this->createAlert(['criteria' => ['salary_min' => 5000000]])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:salary-period-required');
        $this->createAlert(['criteria' => ['q' => 'one two three four five six']])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-criteria');
        $this->createAlert(['email' => false, 'in_app' => false])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:channel-required');

        $this->createAlert(['email' => false])->assertCreated()->assertJsonPath('data.email', false)->assertJsonPath('data.in_app', true);
        $this->createAlert(['in_app' => false])->assertCreated()->assertJsonPath('data.in_app', false);
    }

    public function test_a_job_seeker_has_at_most_the_number_of_alerts_an_admin_allows(): void
    {
        $ids = [];

        foreach (range(1, 5) as $number) {
            $ids[] = $this->alertId(['name' => "Alert {$number}"]);
        }

        $this->createAlert(['name' => 'Alert 6'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:alert-limit');

        $this->api('DELETE', "/me/job-alerts/{$ids[0]}", token: $this->seekerToken)->assertNoContent();
        $this->createAlert(['name' => 'Alert 6'])->assertCreated();

        // The limit is per job seeker, and an admin can change it.
        $other = $this->seeker('other@example.test');
        $this->createAlert(token: $this->tokenFor($other))->assertCreated();

        $this->app->make(SettingsStore::class)->setGlobal(JobAlertSettings::MAX_ALERTS_PER_USER, 6, null);
        $this->createAlert(['name' => 'Alert 7'])->assertCreated();
        $this->createAlert(['name' => 'Alert 8'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:alert-limit');
        $this->app->make(SettingsStore::class)->setGlobal(JobAlertSettings::MAX_ALERTS_PER_USER, 7, null);
        $this->createAlert(['name' => 'Alert 8'])->assertCreated();
    }

    public function test_the_list_shows_only_the_alerts_of_the_caller_oldest_first(): void
    {
        $first = $this->alertId(['name' => 'First']);
        $other = $this->seeker('other@example.test');
        $this->alertId(['name' => 'Not mine'], $this->tokenFor($other));
        $second = $this->alertId(['name' => 'Second']);

        $response = $this->api('GET', '/me/job-alerts', token: $this->seekerToken)->assertOk();

        $this->assertSame([$first, $second], array_column($response->json('data'), 'id'));
        $this->assertSame(['id', 'name', 'frequency', 'criteria', 'email', 'in_app', 'paused', 'created_at', 'updated_at'], array_keys($response->json('data.0')));
        $this->api('GET', "/me/job-alerts/{$second}", token: $this->seekerToken)->assertOk()->assertJsonPath('data.name', 'Second');
    }

    public function test_the_alert_of_someone_else_is_not_found_whatever_is_done_to_it(): void
    {
        $id = $this->alertId();
        $stranger = $this->tokenFor($this->seeker('stranger@example.test'));

        foreach ([['GET', ''], ['PUT', ''], ['DELETE', ''], ['POST', '/pause'], ['POST', '/resume'], ['GET', '/jobs']] as [$method, $suffix]) {
            $this->api($method, "/me/job-alerts/{$id}{$suffix}", $this->alertBody(['name' => 'Taken']), $stranger)->assertNotFound();
        }

        $this->api('GET', '/me/job-alerts/99999', token: $this->seekerToken)->assertNotFound();
        $this->assertSame('Backend jobs', DB::table('jobalert_alerts')->find($id)->name);
    }

    public function test_an_alert_is_replaced_and_deleted(): void
    {
        $id = $this->alertId();

        $this->api('PUT', "/me/job-alerts/{$id}", $this->alertBody(['name' => 'Remote', 'email' => false, 'criteria' => ['work_mode' => ['remote']]]), $this->seekerToken)
            ->assertOk()
            ->assertJsonPath('data.name', 'Remote')
            ->assertJsonPath('data.email', false)
            ->assertJsonPath('data.criteria', ['work_mode' => ['remote']]);
        $this->api('PUT', "/me/job-alerts/{$id}", $this->alertBody(['criteria' => ['role_id' => []]]), $this->seekerToken)->assertStatus(422);
        $this->assertSame(['work_mode' => ['remote']], json_decode((string) DB::table('jobalert_alerts')->find($id)->criteria, true));

        $this->api('DELETE', "/me/job-alerts/{$id}", token: $this->seekerToken)->assertNoContent();
        $this->api('GET', "/me/job-alerts/{$id}", token: $this->seekerToken)->assertNotFound();
        $this->assertSame(0, DB::table('jobalert_alerts')->count());
    }

    public function test_changing_the_frequency_restarts_the_alert_from_now_and_other_changes_keep_it(): void
    {
        $id = $this->alertId(['frequency' => 'daily']);
        Carbon::setTestNow('2026-10-05 12:00:00');

        $this->api('PUT', "/me/job-alerts/{$id}", $this->alertBody(['frequency' => 'daily', 'name' => 'Renamed']), $this->seekerToken)->assertOk();
        $this->assertSame('2026-10-05 09:00:00', DB::table('jobalert_alerts')->find($id)->last_run_at);

        $this->api('PUT', "/me/job-alerts/{$id}", $this->alertBody(['frequency' => 'weekly']), $this->seekerToken)->assertOk()->assertJsonPath('data.frequency', 'weekly');
        $this->assertSame('2026-10-05 12:00:00', DB::table('jobalert_alerts')->find($id)->last_run_at);
    }

    public function test_pausing_and_resuming_are_repeatable_and_resuming_starts_from_now(): void
    {
        $id = $this->alertId();

        $this->api('POST', "/me/job-alerts/{$id}/pause", token: $this->seekerToken)->assertOk()->assertJsonPath('data.paused', true);
        $this->api('POST', "/me/job-alerts/{$id}/pause", token: $this->seekerToken)->assertOk()->assertJsonPath('data.paused', true);

        Carbon::setTestNow('2026-10-05 15:00:00');
        $this->api('POST', "/me/job-alerts/{$id}/resume", token: $this->seekerToken)->assertOk()->assertJsonPath('data.paused', false);
        $this->assertSame('2026-10-05 15:00:00', DB::table('jobalert_alerts')->find($id)->last_run_at);

        // A running alert keeps its cursor when it is resumed again.
        Carbon::setTestNow('2026-10-05 16:00:00');
        $this->api('POST', "/me/job-alerts/{$id}/resume", token: $this->seekerToken)->assertOk()->assertJsonPath('data.paused', false);
        $this->assertSame('2026-10-05 15:00:00', DB::table('jobalert_alerts')->find($id)->last_run_at);
    }

    public function test_the_jobs_of_an_alert_are_the_live_jobs_it_matches_now_in_the_shape_of_the_search(): void
    {
        $id = $this->alertId();
        $first = $this->liveJobFor('Backend One', ['published_at' => '2026-10-01 08:00:00']);
        $second = $this->liveJobFor('Backend Two', ['published_at' => '2026-10-02 08:00:00']);
        $this->liveJobFor('Other role', ['role_id' => $this->referenceId('reference_job_roles', 'data-analyst')]);
        $this->liveJobFor('Closed', ['status' => 'closed']);
        $this->liveJobFor('Late', ['deadline' => '2026-10-04']);

        $response = $this->api('GET', "/me/job-alerts/{$id}/jobs", token: $this->seekerToken)->assertOk();

        $this->assertSame([$second, $first], array_column($response->json('data'), 'id'));
        $this->assertSame(
            ['id', 'slug', 'title', 'company', 'locations', 'job_type', 'job_level', 'work_mode', 'salary', 'deadline', 'published_at', 'match', 'saved'],
            array_keys($response->json('data.0')),
        );
        $this->assertSame(2, $response->json('meta.total'));

        $page = $this->api('GET', "/me/job-alerts/{$id}/jobs?per_page=1&page=2", token: $this->seekerToken)->assertOk();
        $this->assertSame([$first], array_column($page->json('data'), 'id'));
        $this->api('GET', "/me/job-alerts/{$id}/jobs?per_page=500", token: $this->seekerToken)->assertStatus(422);
    }

    public function test_the_jobs_of_an_alert_follow_its_salary_rule(): void
    {
        $monthly = $this->referenceId('reference_salary_periods', 'monthly');
        $id = $this->alertId(['criteria' => ['salary_min' => 12000000, 'salary_period_id' => $monthly]]);
        $good = $this->liveJobFor('Good', ['salary_min' => 10000000, 'salary_max' => 15000000, 'salary_period_id' => $monthly, 'salary_visible' => true]);
        $this->liveJobFor('Hidden', ['salary_min' => 10000000, 'salary_max' => 15000000, 'salary_period_id' => $monthly, 'salary_visible' => false]);
        $this->liveJobFor('Low', ['salary_min' => 5000000, 'salary_max' => 11000000, 'salary_period_id' => $monthly, 'salary_visible' => true]);
        $this->liveJobFor('Empty');

        $this->assertSame([$good], array_column($this->api('GET', "/me/job-alerts/{$id}/jobs", token: $this->seekerToken)->assertOk()->json('data'), 'id'));
    }
}
