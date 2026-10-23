<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `filter[offer_expiring]`: the applications with an unanswered offer ending within three days, the rule of the `offers_expiring` task (the link the task carries). */
final class ApplicantOfferExpiringFilterTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    private int $applicationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');

        $stage = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'offer')->where('is_default', 1)->value('id');
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $stage, 'offer' => $this->offerDetails(['expires_at' => '2026-10-20T09:00:00Z'])], $this->recruiterToken)->assertOk();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /** @return list<int> */
    private function ids(string $uri): array
    {
        return array_map('intval', array_column($this->api('GET', $uri, token: $this->adminToken)->assertOk()->json('data'), 'id'));
    }

    private function offer(string $expiresAt, string $status = 'pending'): void
    {
        DB::table('recruitment_offers')->where('application_id', $this->applicationId)->update(['expires_at' => $expiresAt, 'status' => $status]);
    }

    public function test_the_task_link_is_accepted_and_lists_only_offers_ending_within_three_days(): void
    {
        $link = '/company/applications?filter[state]=offer_pending&filter[offer_expiring]=1';

        $this->offer('2026-10-20 09:00:00');
        $this->assertSame([], $this->ids($link));
        $this->assertSame([$this->applicationId], $this->ids('/company/applications?filter[state]=offer_pending'));

        $this->offer('2026-10-08 09:00:00');
        $this->assertSame([$this->applicationId], $this->ids($link));
        $this->assertSame([$this->applicationId], $this->ids("/company/jobs/{$this->job}/applications?filter[offer_expiring]=true"));

        $this->offer('2026-10-08 09:00:01');
        $this->assertSame([], $this->ids($link));
    }

    public function test_an_answered_offer_is_not_expiring(): void
    {
        $this->offer('2026-10-06 09:00:00', 'declined');
        $this->assertSame([], $this->ids('/company/applications?filter[offer_expiring]=1'));
    }

    public function test_a_false_value_does_not_narrow_the_list(): void
    {
        $this->offer('2026-10-20 09:00:00');
        $this->assertSame([$this->applicationId], $this->ids('/company/applications?filter[offer_expiring]=0'));
    }
}
