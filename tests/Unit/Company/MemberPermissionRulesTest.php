<?php

namespace Tests\Unit\Company;

use PHPUnit\Framework\TestCase;
use Src\Company\Domain\MemberPermissionRules;
use Src\Company\Domain\MemberPreset;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

final class MemberPermissionRulesTest extends TestCase
{
    public function test_a_member_needs_at_least_one_permission(): void
    {
        $this->expectException(BusinessRuleViolation::class);
        $this->expectExceptionMessage('at least one permission');

        MemberPermissionRules::validated([]);
    }

    public function test_an_action_without_reading_is_refused_and_names_the_permission(): void
    {
        try {
            MemberPermissionRules::validated(['messages.send', 'jobs.publish']);
            $this->fail('An action without reading must be refused.');
        } catch (BusinessRuleViolation $e) {
            $this->assertSame('permission-requires-view', $e->errorCode());
            $this->assertSame(['permission' => 'jobs.publish'], $e->extensions());
        }
    }

    public function test_managing_without_reviewing_is_refused_and_names_the_permission(): void
    {
        try {
            MemberPermissionRules::validated(['applications.view', 'applications.manage']);
            $this->fail('Managing without reviewing must be refused.');
        } catch (BusinessRuleViolation $e) {
            $this->assertSame('permission-requires-review', $e->errorCode());
            $this->assertSame(['permission' => 'applications.manage'], $e->extensions());
        }

        $this->assertSame(['applications.view', 'applications.manage', 'applications.review'], MemberPermissionRules::validated(['applications.review', 'applications.manage', 'applications.view']));
    }

    public function test_the_hiring_manager_preset_reads_reviews_and_runs_interviews_only(): void
    {
        $this->assertSame(['applications.view', 'applications.review', 'interviews.manage'], MemberPreset::HiringManager->permissionNames());
    }

    public function test_permissions_that_need_no_reading_stand_alone_and_come_back_in_catalog_order_without_duplicates(): void
    {
        $this->assertSame(['messages.send', 'company.profile'], MemberPermissionRules::validated(['company.profile', 'messages.send', 'company.profile']));
    }

    public function test_every_preset_is_a_valid_set(): void
    {
        foreach ([MemberPreset::Recruiter, MemberPreset::Interviewer, MemberPreset::HiringManager] as $preset) {
            $this->assertEqualsCanonicalizing($preset->permissionNames(), MemberPermissionRules::validated($preset->permissionNames()));
        }
    }

    public function test_the_presets_match_the_design(): void
    {
        $this->assertCount(13, MemberPreset::Recruiter->permissions());
        $this->assertSame(['applications.view', 'interviews.manage'], MemberPreset::Interviewer->permissionNames());
        $this->assertCount(28, MemberPreset::CompanyAdmin->permissions());
        $this->assertContains('talent_pool.manage', MemberPreset::Recruiter->permissionNames());
        $this->assertNotContains('jobs.assign', MemberPreset::Recruiter->permissionNames());
        $this->assertNotContains('analytics.view', MemberPreset::Recruiter->permissionNames());
    }
}
