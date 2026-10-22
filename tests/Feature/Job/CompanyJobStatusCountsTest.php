<?php

namespace Tests\Feature\Job;

use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** The filter `applications=0` of the status counts leaves out the jobs somebody applied to, as it does in the job list. */
final class CompanyJobStatusCountsTest extends FeatureTestCase
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

    public function test_the_status_counts_leave_out_the_jobs_with_applications_when_asked_for_the_ones_without(): void
    {
        $applied = $this->liveJob();
        $this->insertJob('Designer', 'active', [$this->adminId]);
        $this->insertJob('Analyst', 'draft', [$this->adminId]);
        $this->apply($applied)->assertCreated();

        $all = $this->api('GET', '/company/jobs/status-counts')->assertOk()->json('data');
        $this->assertSame([3, 2, 1], [$all['all'], $all['active'], $all['draft']]);

        $without = $this->api('GET', '/company/jobs/status-counts?filter[applications]=0')->assertOk()->json('data');
        $this->assertSame([2, 1, 1], [$without['all'], $without['active'], $without['draft']]);

        $list = $this->api('GET', '/company/jobs?filter[applications]=0')->assertOk();
        $this->assertSame($without['all'], $list->json('meta.total'));
    }
}
