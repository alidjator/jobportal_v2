<?php

namespace Tests\Feature\Job;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\Concerns\BuildsJobFixtures;
use Tests\FeatureTestCase;

final class JobViewsTest extends FeatureTestCase
{
    use BuildsJobFixtures;

    private int $seekerId;

    private string $seekerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildJobFixtures();
        $this->seekerId = (int) UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti Rahma', 'email' => 'siti@example.test', 'status' => 'active', 'locale' => 'en', 'email_verified_at' => '2026-10-01 00:00:00'])->id;
        $this->seekerToken = $this->tokenFor($this->seekerId);
    }

    protected function tearDown(): void
    {
        $this->tearDownJobFixtures();

        parent::tearDown();
    }

    private function publishedJob(): int
    {
        $id = (int) $this->api('POST', '/company/jobs', $this->fullJobBody())->assertCreated()->json('data.id');
        $this->api('POST', "/company/jobs/{$id}/publish")->assertOk()->assertJsonPath('data.status', 'active');

        return $id;
    }

    /** @param  array<string, string>  $headers */
    private function countView(int $jobId, ?string $token = null, string $address = '203.0.113.7', array $headers = []): TestResponse
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        $request = $this->withServerVariables(['REMOTE_ADDR' => $address]);

        if ($token !== null) {
            $request = $request->withToken($token);
        }

        return $request->postJson("/api/v1/jobs/{$jobId}/view", [], ['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) Firefox/130.0', ...$headers]);
    }

    /** @return list<string> */
    private function viewerKeys(int $jobId): array
    {
        return DB::table('job_views')->where('job_id', $jobId)->orderBy('id')->pluck('viewer_key')->all();
    }

    public function test_a_visitor_is_counted_once_a_day_as_a_hash_without_the_address(): void
    {
        $id = $this->publishedJob();

        $this->countView($id)->assertNoContent();
        $this->countView($id)->assertNoContent();

        $keys = $this->viewerKeys($id);
        $this->assertCount(1, $keys);
        $this->assertStringStartsWith('v', $keys[0]);
        $this->assertStringNotContainsString('203.0.113.7', $keys[0]);
        $this->assertSame('2026-10-05', DB::table('job_views')->where('job_id', $id)->value('day'));
    }

    public function test_another_visitor_is_counted_again(): void
    {
        $id = $this->publishedJob();

        $this->countView($id)->assertNoContent();
        $this->countView($id, address: '203.0.113.8')->assertNoContent();

        $this->assertCount(2, $this->viewerKeys($id));
    }

    public function test_a_visitor_key_does_not_carry_over_to_the_next_day(): void
    {
        $id = $this->publishedJob();

        $this->countView($id)->assertNoContent();
        Carbon::setTestNow('2026-10-06 09:00:00');
        $this->countView($id)->assertNoContent();

        $keys = $this->viewerKeys($id);
        $this->assertCount(2, $keys);
        $this->assertNotSame($keys[0], $keys[1]);
        $this->assertSame(['2026-10-05', '2026-10-06'], DB::table('job_views')->where('job_id', $id)->orderBy('id')->pluck('day')->all());
    }

    public function test_a_signed_in_job_seeker_is_counted_as_their_account_once_a_day(): void
    {
        $id = $this->publishedJob();

        $this->countView($id, $this->seekerToken)->assertNoContent();
        $this->countView($id, $this->seekerToken, '203.0.113.99')->assertNoContent();

        $this->assertSame(["u{$this->seekerId}"], $this->viewerKeys($id));

        Carbon::setTestNow('2026-10-06 09:00:00');
        $this->countView($id, $this->seekerToken)->assertNoContent();

        $this->assertSame(["u{$this->seekerId}", "u{$this->seekerId}"], $this->viewerKeys($id));
    }

    public function test_the_members_of_the_owning_company_are_not_counted_but_other_companies_are(): void
    {
        $id = $this->publishedJob();

        $this->countView($id, $this->adminToken)->assertNoContent();
        $this->countView($id, $this->recruiterToken)->assertNoContent();
        $this->assertSame([], $this->viewerKeys($id));

        $this->countView($id, $this->otherAdminToken)->assertNoContent();
        $this->assertSame(["u{$this->otherAdminId}"], $this->viewerKeys($id));
    }

    public function test_crawlers_and_browsers_without_a_name_are_not_counted(): void
    {
        $id = $this->publishedJob();

        $this->countView($id, headers: ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])->assertNoContent();
        $this->countView($id, headers: ['User-Agent' => 'curl/8.4.0'])->assertNoContent();
        $this->countView($id, headers: ['User-Agent' => ''])->assertNoContent();

        $this->assertSame([], $this->viewerKeys($id));
    }

    public function test_a_job_that_is_not_live_is_not_counted(): void
    {
        $draft = (int) $this->api('POST', '/company/jobs', $this->fullJobBody())->assertCreated()->json('data.id');

        $this->countView($draft)->assertNotFound();
        $this->countView(999999)->assertNotFound();

        $this->assertSame(0, DB::table('job_views')->count());
    }

    public function test_reading_the_job_page_does_not_count_a_view(): void
    {
        $id = $this->publishedJob();

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/jobs/{$id}", ['User-Agent' => 'Mozilla/5.0 Firefox/130.0'])->assertOk();

        $this->assertSame(0, DB::table('job_views')->count());
    }

    public function test_the_job_may_be_given_with_its_slug(): void
    {
        $id = $this->publishedJob();

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->postJson("/api/v1/jobs/{$id}-senior-backend-developer/view", [], ['User-Agent' => 'Mozilla/5.0 Firefox/130.0'])->assertNoContent();

        $this->assertCount(1, $this->viewerKeys($id));
    }

    public function test_old_days_are_folded_into_daily_counts_and_the_raw_rows_are_deleted(): void
    {
        $id = $this->publishedJob();
        $other = $this->publishedJob();

        // Today is 2026-10-05; 90 days back is 2026-07-07, so that day is the oldest one kept.
        foreach ([['u1', '2026-07-05'], ['u2', '2026-07-05'], ['u1', '2026-07-06'], ['u3', '2026-07-07'], ['u3', '2026-10-05']] as [$key, $day]) {
            DB::table('job_views')->insert(['job_id' => $id, 'viewer_key' => $key, 'day' => $day, 'created_at' => now()]);
        }

        DB::table('job_views')->insert(['job_id' => $other, 'viewer_key' => 'u1', 'day' => '2026-07-05', 'created_at' => now()]);

        $this->assertSame(0, Artisan::call('job:fold-views'));

        $this->assertSame(
            [[$id, '2026-07-05', 2], [$other, '2026-07-05', 1], [$id, '2026-07-06', 1]],
            DB::table('job_view_daily')->orderBy('day')->orderBy('job_id')->get()->map(fn (object $row): array => [(int) $row->job_id, (string) $row->day, (int) $row->views])->all(),
        );
        $this->assertSame(['2026-07-07', '2026-10-05'], DB::table('job_views')->orderBy('day')->pluck('day')->all());

        $this->assertSame(0, Artisan::call('job:fold-views'));
        $this->assertSame(3, DB::table('job_view_daily')->count());
        $this->assertSame(2, DB::table('job_views')->count());
    }

    public function test_an_anonymised_account_loses_its_raw_views_and_the_others_stay(): void
    {
        $id = $this->publishedJob();

        $this->countView($id, $this->seekerToken)->assertNoContent();
        $this->countView($id)->assertNoContent();
        $this->assertCount(2, $this->viewerKeys($id));

        Event::dispatch(new UserAnonymized($this->seekerId, now()->toDateTimeImmutable()));

        $keys = $this->viewerKeys($id);
        $this->assertCount(1, $keys);
        $this->assertStringStartsWith('v', $keys[0]);
    }
}
