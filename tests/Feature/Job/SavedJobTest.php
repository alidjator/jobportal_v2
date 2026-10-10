<?php

namespace Tests\Feature\Job;

use Illuminate\Support\Facades\DB;
use Src\Job\Application\Saved\EraseSavedJobs;
use Src\Job\Application\Saved\SaveJob;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

final class SavedJobTest extends FeatureTestCase
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

    /** @return list<int> the job ids in the list, in order */
    private function listedJobIds(?string $token = null): array
    {
        return array_map('intval', $this->api('GET', '/me/saved-jobs', token: $token ?? $this->seekerToken)->assertOk()->json('data.*.job_id'));
    }

    private function savedCount(?int $userId = null): int
    {
        return DB::table('job_saved_jobs')->where('user_id', $userId ?? $this->seekerId)->count();
    }

    public function test_a_job_seeker_saves_a_live_job_and_sees_it_in_the_list_as_a_search_row(): void
    {
        $job = $this->liveJob();

        $this->api('PUT', "/me/saved-jobs/{$job}", token: $this->seekerToken)->assertNoContent();

        $entry = $this->api('GET', '/me/saved-jobs', token: $this->seekerToken)->assertOk()->json('data.0');

        $this->assertSame($job, $entry['job_id']);
        $this->assertSame('Backend Developer', $entry['title']);
        $this->assertTrue($entry['available']);
        $this->assertSame($job, $entry['job']['id']);
        $this->assertSame('pt-maju', $entry['job']['company']['slug']);
        $this->assertNotNull($entry['saved_at']);
    }

    public function test_saving_a_job_again_changes_nothing(): void
    {
        $job = $this->liveJob();

        $this->api('PUT', "/me/saved-jobs/{$job}", token: $this->seekerToken)->assertNoContent();
        $this->api('PUT', "/me/saved-jobs/{$job}", token: $this->seekerToken)->assertNoContent();

        $this->assertSame(1, $this->savedCount());
    }

    public function test_the_list_is_the_most_recently_saved_first_and_paged(): void
    {
        $first = $this->liveJob();
        $second = $this->liveJob();
        $third = $this->liveJob();

        foreach ([$first, $second, $third] as $index => $job) {
            DB::table('job_saved_jobs')->insert(['user_id' => $this->seekerId, 'job_id' => $job, 'saved_at' => now()->subHours(3 - $index)]);
        }

        $this->assertSame([$third, $second, $first], $this->listedJobIds());

        $page = $this->api('GET', '/me/saved-jobs?per_page=2&page=2', token: $this->seekerToken)->assertOk();

        $this->assertSame([$first], array_map('intval', $page->json('data.*.job_id')));
        $this->assertSame(3, $page->json('meta.total'));
    }

    public function test_a_job_that_is_not_live_cannot_be_saved(): void
    {
        $draft = $this->insertJob('Draft job', 'draft');
        $closed = $this->insertJob('Closed job', 'closed', attributes: ['deadline' => '2026-12-31']);
        $past = $this->insertJob('Past deadline', 'active', attributes: ['deadline' => '2020-01-01']);

        foreach ([$draft, $closed, $past, 999_999] as $job) {
            $this->api('PUT', "/me/saved-jobs/{$job}", token: $this->seekerToken)->assertNotFound();
        }

        $this->assertSame(0, $this->savedCount());
    }

    public function test_a_job_that_stops_being_live_stays_in_the_list_without_its_row(): void
    {
        $job = $this->liveJob();
        $this->api('PUT', "/me/saved-jobs/{$job}", token: $this->seekerToken)->assertNoContent();

        DB::table('job_jobs')->where('id', $job)->update(['status' => 'closed']);

        $entry = $this->api('GET', '/me/saved-jobs', token: $this->seekerToken)->assertOk()->json('data.0');

        $this->assertSame($job, $entry['job_id']);
        $this->assertSame('Backend Developer', $entry['title']);
        $this->assertFalse($entry['available']);
        $this->assertNull($entry['job']);
    }

    public function test_a_job_of_a_hidden_company_is_listed_as_unavailable(): void
    {
        $job = $this->liveJob();
        $this->api('PUT', "/me/saved-jobs/{$job}", token: $this->seekerToken)->assertNoContent();

        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'suspended']);

        $this->assertFalse($this->api('GET', '/me/saved-jobs', token: $this->seekerToken)->assertOk()->json('data.0.available'));
    }

    public function test_removing_a_saved_job_works_even_when_it_is_closed_and_twice(): void
    {
        $job = $this->liveJob();
        $this->api('PUT', "/me/saved-jobs/{$job}", token: $this->seekerToken)->assertNoContent();
        DB::table('job_jobs')->where('id', $job)->update(['status' => 'closed']);

        $this->api('DELETE', "/me/saved-jobs/{$job}", token: $this->seekerToken)->assertNoContent();
        $this->api('DELETE', "/me/saved-jobs/{$job}", token: $this->seekerToken)->assertNoContent();

        $this->assertSame([], $this->listedJobIds());
    }

    public function test_one_job_seeker_never_sees_or_removes_the_saved_jobs_of_another(): void
    {
        $job = $this->liveJob();
        $other = $this->seeker('other@example.test', name: 'Other Seeker');
        $otherToken = $this->tokenFor($other);

        $this->api('PUT', "/me/saved-jobs/{$job}", token: $otherToken)->assertNoContent();

        $this->assertSame([], $this->listedJobIds());

        $this->api('DELETE', "/me/saved-jobs/{$job}", token: $this->seekerToken)->assertNoContent();

        $this->assertSame([$job], $this->listedJobIds($otherToken));
    }

    public function test_only_a_signed_in_job_seeker_has_saved_jobs(): void
    {
        $job = $this->liveJob();

        $this->api('GET', '/me/saved-jobs')->assertUnauthorized();
        $this->api('PUT', "/me/saved-jobs/{$job}")->assertUnauthorized();

        foreach ([['GET', '/me/saved-jobs'], ['PUT', "/me/saved-jobs/{$job}"], ['DELETE', "/me/saved-jobs/{$job}"]] as [$method, $uri]) {
            $this->api($method, $uri, token: $this->adminToken)->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:job-seeker-only');
        }
    }

    public function test_a_job_seeker_keeps_at_most_two_hundred_saved_jobs(): void
    {
        $now = now();

        for ($index = 0; $index < SaveJob::MAX_SAVED; $index++) {
            DB::table('job_saved_jobs')->insert(['user_id' => $this->seekerId, 'job_id' => $this->liveJob(), 'saved_at' => $now]);
        }

        $extra = $this->liveJob();

        $this->api('PUT', "/me/saved-jobs/{$extra}", token: $this->seekerToken)->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:saved-jobs-limit');
        $this->assertSame(SaveJob::MAX_SAVED, $this->savedCount());

        // A job that is already saved can be saved again at the limit, and removing one makes room.
        $saved = (int) DB::table('job_saved_jobs')->where('user_id', $this->seekerId)->value('job_id');
        $this->api('PUT', "/me/saved-jobs/{$saved}", token: $this->seekerToken)->assertNoContent();

        $this->api('DELETE', "/me/saved-jobs/{$saved}", token: $this->seekerToken)->assertNoContent();
        $this->api('PUT', "/me/saved-jobs/{$extra}", token: $this->seekerToken)->assertNoContent();
        $this->assertSame(SaveJob::MAX_SAVED, $this->savedCount());
    }

    public function test_the_saved_jobs_of_an_anonymised_account_are_deleted(): void
    {
        $job = $this->liveJob();
        $this->api('PUT', "/me/saved-jobs/{$job}", token: $this->seekerToken)->assertNoContent();

        $this->app->make(EraseSavedJobs::class)->execute($this->seekerId);
        $this->app->make(EraseSavedJobs::class)->execute($this->seekerId);

        $this->assertSame(0, $this->savedCount());
    }
}
