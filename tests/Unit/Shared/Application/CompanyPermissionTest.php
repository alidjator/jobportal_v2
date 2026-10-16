<?php

namespace Tests\Unit\Shared\Application;

use PHPUnit\Framework\TestCase;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyMembership;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;

final class CompanyPermissionTest extends TestCase
{
    public function test_the_catalog_has_28_permissions_in_six_groups(): void
    {
        $this->assertCount(28, CompanyPermission::cases());
        $this->assertSame(['jobs', 'applicants', 'sourcing', 'library', 'company', 'workforce'], array_values(array_unique(array_map(fn (CompanyPermission $p): string => $p->group(), CompanyPermission::cases()))));
    }

    public function test_ten_permissions_need_reading(): void
    {
        $needing = array_filter(CompanyPermission::cases(), fn (CompanyPermission $p): bool => $p->requiresView());

        $this->assertEqualsCanonicalizing([
            'jobs.create', 'jobs.publish', 'jobs.assign', 'jobs.view_all', 'applications.manage', 'applications.review', 'applications.export', 'interviews.manage', 'offers.manage',
            'assessments.send',
        ], array_values(array_map(fn (CompanyPermission $p): string => $p->value, $needing)));
    }

    public function test_a_company_admin_holds_every_permission_without_rows(): void
    {
        $membership = new CompanyMembership(1, 'company_admin');

        $this->assertTrue($membership->can(CompanyPermission::JobsAssign));
        $this->assertSame(CompanyPermission::values(), $membership->effectivePermissions());
    }

    public function test_a_member_holds_only_the_checked_permissions(): void
    {
        $membership = new CompanyMembership(1, 'member', ['applications.view']);

        $this->assertTrue($membership->can(CompanyPermission::ApplicationsView));
        $this->assertFalse($membership->can(CompanyPermission::JobsAssign));
        $this->assertSame(['applications.view'], $membership->effectivePermissions());
    }

    public function test_requiring_a_missing_permission_names_it(): void
    {
        try {
            (new CompanyMembership(1, 'member', []))->require(CompanyPermission::JobsAssign);
            $this->fail('A missing permission must be refused.');
        } catch (AccessDenied $e) {
            $this->assertSame('company-permission-required', $e->errorCode());
            $this->assertSame(['permission' => 'jobs.assign'], $e->extensions());
        }
    }

    public function test_an_actor_without_a_membership_holds_nothing(): void
    {
        $this->assertFalse(Actor::jobSeeker(1)->can(CompanyPermission::ApplicationsView));
        $this->assertTrue(Actor::companyMember(1)->withMembership(new CompanyMembership(1, 'company_admin'))->can(CompanyPermission::ApplicationsView));
    }
}
