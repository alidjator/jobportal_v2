<?php

namespace Tests\Feature\Company;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Src\Company\Application\Team\ReleaseMemberOfAnonymizedAccount;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\Concerns\BuildsJobFixtures;
use Tests\FeatureTestCase;

/** A company member who deletes their own account: the last company admin is refused, anyone else is released from the company once anonymised. */
final class MemberAccountDeletionTest extends FeatureTestCase
{
    use BuildsJobFixtures;

    private const string PASSWORD = 'correct-horse-battery';

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildJobFixtures();
        UserModel::query()->whereIn('id', [$this->adminId, $this->recruiterId, $this->otherAdminId])->update(['password' => Hash::make(self::PASSWORD)]);
    }

    protected function tearDown(): void
    {
        $this->tearDownJobFixtures();

        parent::tearDown();
    }

    private function requestDeletion(string $token): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->postJson('/api/v1/me/deletion', ['password' => self::PASSWORD, 'confirm' => true]);
    }

    private function membersOf(int $companyId): array
    {
        return array_map('intval', DB::table('company_members')->where('company_id', $companyId)->orderBy('id')->pluck('user_id')->all());
    }

    private function anonymiseAfterGracePeriod(): void
    {
        Carbon::setTestNow('2026-11-10 09:00:00');

        $this->assertSame(0, Artisan::call('identity:anonymize-due-accounts'));
    }

    public function test_the_only_company_admin_cannot_delete_the_account(): void
    {
        $this->requestDeletion($this->adminToken)
            ->assertStatus(409)
            ->assertJsonPath('type', 'urn:jobportal:error:last-company-admin');

        $admin = UserModel::query()->findOrFail($this->adminId);
        $this->assertSame('active', $admin->status);
        $this->assertNull($admin->deletion_requested_at);
        $this->assertSame(1, $admin->tokens()->count());
        $this->assertContains($this->adminId, $this->membersOf($this->companyId));
    }

    public function test_the_only_company_admin_of_another_company_is_judged_on_their_own_company(): void
    {
        $this->requestDeletion($this->otherAdminToken)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:last-company-admin');
    }

    public function test_a_company_admin_may_delete_the_account_when_another_admin_can_still_sign_in(): void
    {
        $second = $this->jobMember($this->companyId, 'Dewi Admin', 'dewi@example.test', 'company_admin');

        $this->requestDeletion($this->adminToken)->assertOk();

        $this->assertSame('deactivated', UserModel::query()->findOrFail($this->adminId)->status);
        $this->assertSame('active', UserModel::query()->findOrFail($second)->status);
    }

    public function test_two_admins_cannot_both_leave_the_company_without_an_admin(): void
    {
        $second = $this->jobMember($this->companyId, 'Dewi Admin', 'dewi@example.test', 'company_admin');
        UserModel::query()->whereKey($second)->update(['password' => Hash::make(self::PASSWORD)]);
        $secondToken = $this->tokenFor($second);

        $this->requestDeletion($this->adminToken)->assertOk();
        $this->requestDeletion($secondToken)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:last-company-admin');

        $this->assertSame('active', UserModel::query()->findOrFail($second)->status);
    }

    public function test_an_admin_whose_colleague_admin_is_suspended_counts_as_the_last_one(): void
    {
        $second = $this->jobMember($this->companyId, 'Dewi Admin', 'dewi@example.test', 'company_admin');
        UserModel::query()->whereKey($second)->update(['status' => 'suspended']);

        $this->requestDeletion($this->adminToken)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:last-company-admin');
    }

    public function test_a_recruiter_may_delete_the_account_even_when_the_company_has_one_admin(): void
    {
        $this->requestDeletion($this->recruiterToken)->assertOk();

        $this->assertSame('deactivated', UserModel::query()->findOrFail($this->recruiterId)->status);
    }

    public function test_anonymising_a_recruiter_releases_the_membership_and_hands_the_jobs_over(): void
    {
        $alone = $this->insertJob('Only Rina', 'active', [$this->recruiterId]);
        $shared = $this->insertJob('Rina and Sari', 'active', [$this->recruiterId, $this->secondRecruiterId]);

        $this->requestDeletion($this->recruiterToken)->assertOk();

        $this->assertContains($this->recruiterId, $this->membersOf($this->companyId), 'The membership stays during the grace period.');

        $this->anonymiseAfterGracePeriod();

        $this->assertNotContains($this->recruiterId, $this->membersOf($this->companyId));
        $this->assertSame([$this->adminId], $this->assigneesOf($alone));
        $this->assertSame([$this->secondRecruiterId], $this->assigneesOf($shared));
        $this->assertSame(0, DB::table('job_assignments')->where('user_id', $this->recruiterId)->count());
    }

    public function test_the_release_is_audited_as_a_member_removal(): void
    {
        $this->requestDeletion($this->recruiterToken)->assertOk();
        $this->anonymiseAfterGracePeriod();

        $entry = DB::table('platform_audit_logs')->where('action', 'company.member_removed')->first();

        $this->assertNotNull($entry);
        $this->assertSame($this->recruiterId, (int) $entry->actor_id);
    }

    public function test_an_account_that_is_not_a_member_is_left_alone_and_running_twice_changes_nothing(): void
    {
        $release = $this->app->make(ReleaseMemberOfAnonymizedAccount::class);

        $release->execute(999999, now()->toDateTimeImmutable());
        $release->execute($this->recruiterId, now()->toDateTimeImmutable());
        $release->execute($this->recruiterId, now()->toDateTimeImmutable());

        $this->assertSame([$this->adminId, $this->secondRecruiterId], $this->membersOf($this->companyId));
    }

    public function test_an_admin_who_became_the_last_one_during_the_grace_period_is_still_released(): void
    {
        $this->insertJob('Andi only', 'active', [$this->adminId]);
        UserModel::query()->whereKey($this->adminId)->update(['status' => 'deactivated', 'deletion_requested_at' => '2026-09-01 10:00:00']);

        $this->anonymiseAfterGracePeriod();

        $this->assertNotContains($this->adminId, $this->membersOf($this->companyId));
        $this->assertSame(0, DB::table('job_assignments')->where('user_id', $this->adminId)->count());
    }
}
