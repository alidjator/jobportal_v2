<?php

namespace Tests\Feature\ReferenceData;

use Illuminate\Support\Facades\DB;
use Src\Shared\Application\References\ReferenceValues;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;

final class ReferenceValuesTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    public function test_an_active_master_item_is_active_and_has_a_name(): void
    {
        $id = $this->referenceId('reference_job_categories', 'engineering');
        $values = $this->app->make(ReferenceValues::class);

        $this->assertTrue($values->isActive('job-categories', $id));
        $this->assertSame('engineering', $values->name('job-categories', $id, 'en'));
    }

    public function test_a_deactivated_or_unknown_item_is_not_active_but_a_deactivated_one_keeps_its_name(): void
    {
        $id = $this->referenceId('reference_job_categories', 'engineering');
        DB::table('reference_job_categories')->where('id', $id)->update(['is_active' => false]);
        $values = $this->app->make(ReferenceValues::class);

        $this->assertFalse($values->isActive('job-categories', $id));
        $this->assertFalse($values->isActive('job-categories', 999999));
        $this->assertSame('engineering', $values->name('job-categories', $id, 'en'));
        $this->assertNull($values->name('job-categories', 999999, 'en'));
    }

    public function test_a_benefit_that_is_not_approved_is_never_found(): void
    {
        $approved = $this->referenceId('reference_benefits', 'insurance');
        $pending = $this->referenceId('reference_benefits', 'gym', ['status' => 'pending']);
        $rejected = $this->referenceId('reference_benefits', 'pool', ['status' => 'rejected', 'rejection_reason' => 'Not a benefit.']);
        $values = $this->app->make(ReferenceValues::class);

        $this->assertTrue($values->isActive('benefits', $approved));
        $this->assertFalse($values->isActive('benefits', $pending), 'a pending proposal is not a selectable benefit');
        $this->assertFalse($values->isActive('benefits', $rejected));
        $this->assertNull($values->name('benefits', $pending, 'en'));
    }
}
