<?php

namespace Tests\Feature\Training;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

final class TrainingTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private const string ENDPOINT = 'https://moodle.example.test/webservice/rest/server.php';

    private const string TOKEN = 'secret-moodle-token';

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        config(['services.moodle' => ['url' => self::ENDPOINT, 'token' => self::TOKEN, 'course_url' => 'https://moodle.example.test/course/view.php?id={id}']]);
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /** @return list<array<string, mixed>> */
    private function moodleCourses(): array
    {
        return [
            ['id' => 1, 'fullname' => 'PKP Training', 'summary' => '', 'startdate' => 0, 'enddate' => 0],
            ['id' => 7, 'fullname' => 'Excel Dasar', 'summary' => '<p>Belajar <b>Excel</b> &amp; rumus.</p>', 'courseimage' => 'https://moodle.example.test/img/7.png', 'startdate' => 1790000000, 'enddate' => 0],
            ['id' => 9, 'fullname' => 'Public Speaking', 'summary' => 'Berbicara di depan umum', 'startdate' => 0, 'enddate' => 0],
        ];
    }

    /** Moodle answering every function the way the real web service does. */
    private function fakeMoodle(): void
    {
        Http::fake(fn (Request $request) => match ($request['wsfunction']) {
            'core_course_get_courses_by_field' => Http::response(['courses' => $this->moodleCourses(), 'warnings' => []]),
            'core_user_get_users_by_field' => Http::response($request['values'][0] === 'siti@example.test' ? [['id' => 42, 'email' => 'siti@example.test']] : []),
            'core_enrol_get_users_courses' => Http::response([
                ['id' => 7, 'fullname' => 'Excel Dasar', 'summary' => '<p>Belajar Excel</p>', 'startdate' => 1790000000, 'enddate' => 0, 'progress' => 45.678, 'lastaccess' => 1790100000],
                ['id' => 9, 'fullname' => 'Public Speaking', 'summary' => 'Berbicara', 'startdate' => 0, 'enddate' => 0, 'progress' => null, 'lastaccess' => 0],
            ]),
            default => Http::response([], 500),
        });
    }

    public function test_the_catalog_is_public_and_leaves_out_the_site_course(): void
    {
        $this->fakeMoodle();

        $response = $this->getJson('/api/v1/training/courses')->assertOk()->assertJsonCount(2, 'data');

        $response->assertJsonPath('data.0.id', 7)
            ->assertJsonPath('data.0.name', 'Excel Dasar')
            ->assertJsonPath('data.0.summary', 'Belajar Excel & rumus.')
            ->assertJsonPath('data.0.image_url', 'https://moodle.example.test/img/7.png')
            ->assertJsonPath('data.0.starts_at', '2026-09-21T14:13:20+00:00')
            ->assertJsonPath('data.0.ends_at', null)
            ->assertJsonPath('data.0.url', 'https://moodle.example.test/course/view.php?id=7')
            ->assertJsonPath('data.1.image_url', null)
            ->assertJsonPath('data.1.starts_at', null)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_the_token_travels_in_the_body_and_never_in_the_url(): void
    {
        $this->fakeMoodle();

        $this->getJson('/api/v1/training/courses')->assertOk();

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === self::ENDPOINT
            && $request['wstoken'] === self::TOKEN
            && $request['moodlewsrestformat'] === 'json');
    }

    public function test_the_catalog_can_be_searched_and_paged(): void
    {
        $this->fakeMoodle();

        $this->getJson('/api/v1/training/courses?filter[q]=speak')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 9)->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/training/courses?per_page=1&page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 9)->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/training/courses?filter[price]=1')->assertStatus(422);
    }

    public function test_the_catalog_is_kept_and_a_stale_copy_covers_an_outage(): void
    {
        Carbon::setTestNow('2026-10-10 08:00:00');
        $this->fakeMoodle();

        $this->getJson('/api/v1/training/courses')->assertOk();
        $this->getJson('/api/v1/training/courses')->assertOk();
        Http::assertSentCount(1);

        Carbon::setTestNow('2026-10-10 08:20:00');
        Http::fake(['*' => Http::response('down', 500)]);

        $this->getJson('/api/v1/training/courses')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_an_outage_with_nothing_kept_is_a_503(): void
    {
        Http::fake(['*' => Http::response('down', 500)]);

        $this->getJson('/api/v1/training/courses')->assertStatus(503)->assertJsonPath('type', 'urn:onesis:error:training-unavailable');
    }

    public function test_a_moodle_exception_payload_is_a_503_and_never_leaks_the_token(): void
    {
        Http::fake(['*' => Http::response(['exception' => 'moodle_exception', 'errorcode' => 'invalidtoken', 'message' => 'Invalid token '.self::TOKEN])]);

        $response = $this->getJson('/api/v1/training/courses')->assertStatus(503)->assertJsonPath('type', 'urn:onesis:error:training-unavailable');

        $this->assertStringNotContainsString(self::TOKEN, $response->getContent());
    }

    public function test_without_settings_the_platform_is_unavailable(): void
    {
        config(['services.moodle.token' => null]);
        Http::fake();

        $this->getJson('/api/v1/training/courses')->assertStatus(503)->assertJsonPath('type', 'urn:onesis:error:training-unavailable');
        Http::assertNothingSent();
    }

    public function test_a_job_seeker_sees_their_courses_with_progress(): void
    {
        $this->fakeMoodle();

        $this->api('GET', '/me/training/courses', [], $this->seekerToken)->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', 7)
            ->assertJsonPath('data.0.summary', 'Belajar Excel')
            ->assertJsonPath('data.0.image_url', 'https://moodle.example.test/img/7.png')
            ->assertJsonPath('data.0.progress', 45.7)
            ->assertJsonPath('data.0.last_access_at', '2026-09-22T18:00:00+00:00')
            ->assertJsonPath('data.1.summary', 'Berbicara')
            ->assertJsonPath('data.1.image_url', null)
            ->assertJsonPath('data.1.progress', null)
            ->assertJsonPath('data.1.last_access_at', null);

        Http::assertSent(fn (Request $request): bool => $request['wsfunction'] === 'core_user_get_users_by_field' && $request['field'] === 'email' && $request['values'][0] === 'siti@example.test');
        Http::assertSent(fn (Request $request): bool => $request['wsfunction'] === 'core_enrol_get_users_courses' && (int) $request['userid'] === 42);
    }

    public function test_a_missing_image_stays_empty_when_the_catalog_fails(): void
    {
        Http::fake(fn (Request $request) => match ($request['wsfunction']) {
            'core_user_get_users_by_field' => Http::response([['id' => 42]]),
            'core_enrol_get_users_courses' => Http::response([['id' => 7, 'fullname' => 'Excel Dasar', 'summary' => '', 'startdate' => 0, 'enddate' => 0, 'progress' => 10, 'lastaccess' => 0]]),
            default => Http::response('down', 500),
        });

        $this->api('GET', '/me/training/courses', [], $this->seekerToken)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.image_url', null);
    }

    public function test_an_email_without_a_platform_account_has_no_courses(): void
    {
        $this->fakeMoodle();
        $token = $this->tokenFor($this->seeker('nobody@example.test'));

        $this->api('GET', '/me/training/courses', [], $token)->assertOk()->assertJsonCount(0, 'data');
        Http::assertNotSent(fn (Request $request): bool => $request['wsfunction'] === 'core_enrol_get_users_courses');
    }

    public function test_an_unverified_email_is_refused_before_moodle_is_asked(): void
    {
        Http::fake();
        $token = $this->tokenFor($this->seeker('siti2@example.test', null));

        $this->api('GET', '/me/training/courses', [], $token)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:email-not-verified');
        Http::assertNothingSent();
    }

    public function test_only_a_signed_in_job_seeker_has_courses(): void
    {
        Http::fake();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->getJson('/api/v1/me/training/courses')->assertStatus(401);
        $this->api('GET', '/me/training/courses', [], $this->adminToken)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:job-seeker-only');
        $this->api('GET', '/me/training/courses', [], $this->recruiterToken)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:job-seeker-only');
        Http::assertNothingSent();
    }
}
