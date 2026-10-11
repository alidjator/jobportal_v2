<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

final class ApplicationViewedTest extends FeatureTestCase
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

    private function open(?string $token = null): TestResponse
    {
        return $this->api('GET', "/company/applications/{$this->applicationId}", token: $token ?? $this->recruiterToken);
    }

    private function viewedAt(): ?string
    {
        $value = DB::table('recruitment_applications')->where('id', $this->applicationId)->value('viewed_at');

        return $value === null ? null : (string) $value;
    }

    public function test_the_first_open_of_the_detail_sets_the_time_and_a_second_open_keeps_it(): void
    {
        $this->assertNull($this->viewedAt());

        Carbon::setTestNow('2026-10-05 10:00:00');
        $this->open()->assertOk();
        $this->assertSame('2026-10-05 10:00:00', $this->viewedAt());

        Carbon::setTestNow('2026-10-05 11:00:00');
        $this->open($this->adminToken)->assertOk();
        $this->assertSame('2026-10-05 10:00:00', $this->viewedAt());
    }

    public function test_marking_does_not_touch_the_updated_time(): void
    {
        $before = DB::table('recruitment_applications')->where('id', $this->applicationId)->value('updated_at');

        Carbon::setTestNow('2026-10-05 10:00:00');
        $this->open()->assertOk();

        $this->assertSame($before, DB::table('recruitment_applications')->where('id', $this->applicationId)->value('updated_at'));
    }

    public function test_a_member_who_may_not_see_the_job_marks_nothing(): void
    {
        foreach ([$this->secondRecruiterToken, $this->otherAdminToken] as $token) {
            $this->open($token)->assertStatus(404);
        }

        $this->open($this->seekerToken)->assertStatus(403);
        $this->assertNull($this->viewedAt());
    }

    public function test_the_list_the_export_and_the_resume_do_not_mark_it(): void
    {
        $this->api('GET', "/company/jobs/{$this->job}/applications", token: $this->recruiterToken)->assertOk();
        $this->api('GET', "/company/jobs/{$this->job}/applications/export", token: $this->adminToken)->assertOk();
        $this->api('GET', "/company/applications/{$this->applicationId}/resume", token: $this->recruiterToken)->assertOk();

        $this->assertNull($this->viewedAt());
    }

    public function test_the_applicant_sees_null_before_and_the_time_after_in_the_detail_and_the_list(): void
    {
        $detail = fn (): TestResponse => $this->api('GET', "/me/applications/{$this->applicationId}", token: $this->seekerToken)->assertOk();
        $list = fn (): TestResponse => $this->api('GET', '/me/applications', token: $this->seekerToken)->assertOk();

        $detail()->assertJsonPath('data.viewed_at', null);
        $list()->assertJsonPath('data.0.viewed_at', null);

        Carbon::setTestNow('2026-10-05 10:00:00');
        $this->open()->assertOk();

        $detail()->assertJsonPath('data.viewed_at', '2026-10-05T10:00:00+00:00');
        $list()->assertJsonPath('data.0.viewed_at', '2026-10-05T10:00:00+00:00');
    }

    public function test_a_withdrawn_application_is_marked_too(): void
    {
        $this->api('POST', "/me/applications/{$this->applicationId}/withdraw", [], $this->seekerToken)->assertNoContent();

        Carbon::setTestNow('2026-10-05 10:00:00');
        $this->open()->assertOk();

        $this->assertSame('2026-10-05 10:00:00', $this->viewedAt());
    }
}
