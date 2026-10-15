<?php

namespace Tests\Feature\Job;

use Illuminate\Support\Facades\DB;
use Src\Job\Application\ReassignJobsOfRemovedMember;
use Tests\Concerns\BuildsJobFixtures;
use Tests\FeatureTestCase;

final class JobAssigneesTest extends FeatureTestCase
{
    use BuildsJobFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildJobFixtures();
    }

    protected function tearDown(): void
    {
        $this->tearDownJobFixtures();

        parent::tearDown();
    }

    /** @return list<array{type: string, data: array<string, mixed>}> */
    private function notificationsOf(int $userId): array
    {
        return array_map(
            fn ($row): array => ['type' => (string) $row->type, 'data' => json_decode((string) $row->data, true)],
            DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $userId)->orderBy('created_at')->orderBy('id')->get()->all(),
        );
    }

    /** @return list<object> */
    private function auditEntries(): array
    {
        return DB::table('platform_audit_logs')->where('action', 'job.assignees_changed')->orderBy('id')->get()->all();
    }

    // ----- reading -----

    public function test_who_sees_the_assignees_follows_who_sees_the_job(): void
    {
        $id = $this->insertJob('Shared', 'draft', [$this->adminId, $this->recruiterId]);

        $this->api('GET', "/company/jobs/{$id}/assignees")->assertOk()->assertJsonCount(2, 'data');
        $this->api('GET', "/company/jobs/{$id}/assignees", token: $this->recruiterToken)->assertOk()->assertJsonCount(2, 'data');
        $this->api('GET', "/company/jobs/{$id}/assignees", token: $this->secondRecruiterToken)->assertNotFound();
        $this->api('GET', "/company/jobs/{$id}/assignees", token: $this->otherAdminToken)->assertNotFound();
    }

    public function test_an_assignee_shows_name_and_role(): void
    {
        $id = $this->insertJob('Shared', 'draft', [$this->recruiterId, $this->adminId]);

        $this->assertSame(
            [['user_id' => $this->recruiterId, 'name' => 'Rina Rekruter', 'role' => 'member'], ['user_id' => $this->adminId, 'name' => 'Andi Admin', 'role' => 'company_admin']],
            $this->api('GET', "/company/jobs/{$id}/assignees")->json('data'),
        );
    }

    // ----- replacing -----

    public function test_a_company_admin_replaces_the_set_and_the_change_is_audited(): void
    {
        $id = $this->insertJob('Shared', 'draft', [$this->adminId, $this->recruiterId]);

        $response = $this->api('PUT', "/company/jobs/{$id}/assignees", ['user_ids' => [$this->recruiterId, $this->secondRecruiterId]])->assertOk();

        $this->assertSame([$this->recruiterId, $this->secondRecruiterId], array_column($response->json('data'), 'user_id'));
        $this->assertEqualsCanonicalizing([$this->recruiterId, $this->secondRecruiterId], $this->assigneesOf($id));

        $entries = $this->auditEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(['user', $this->adminId, 'job', $id], [$entries[0]->actor_type, (int) $entries[0]->actor_id, $entries[0]->subject_type, (int) $entries[0]->subject_id]);
        $this->assertSame(['added' => [$this->secondRecruiterId], 'removed' => [$this->adminId]], json_decode((string) $entries[0]->metadata, true));
        $this->assertSame($this->adminId, DB::table('job_assignments')->where('job_id', $id)->where('user_id', $this->secondRecruiterId)->value('assigned_by_user_id'));
    }

    public function test_the_audit_entry_holds_no_names_or_emails(): void
    {
        $id = $this->insertJob('Shared', 'draft', [$this->adminId]);
        $this->api('PUT', "/company/jobs/{$id}/assignees", ['user_ids' => [$this->adminId, $this->recruiterId]])->assertOk();

        $entry = $this->auditEntries()[0];

        $this->assertStringNotContainsString('Rina', (string) $entry->metadata);
        $this->assertStringNotContainsString('@', (string) $entry->metadata);
    }

    public function test_the_newly_assigned_and_the_removed_are_told_but_not_the_admin_who_did_it(): void
    {
        $id = $this->insertJob('Shared', 'draft', [$this->adminId, $this->recruiterId]);

        $this->api('PUT', "/company/jobs/{$id}/assignees", ['user_ids' => [$this->adminId, $this->secondRecruiterId]])->assertOk();

        $this->assertSame([['type' => 'job.assigned', 'data' => ['job_id' => $id]]], $this->notificationsOf($this->secondRecruiterId));
        $this->assertSame([['type' => 'job.unassigned', 'data' => ['job_id' => $id]]], $this->notificationsOf($this->recruiterId));
        $this->assertSame([], $this->notificationsOf($this->adminId));
    }

    public function test_an_admin_who_adds_themselves_is_not_told(): void
    {
        $id = $this->insertJob('Sari job', 'draft', [$this->secondRecruiterId]);

        $this->api('PUT', "/company/jobs/{$id}/assignees", ['user_ids' => [$this->secondRecruiterId, $this->adminId]])->assertOk();

        $this->assertSame([], $this->notificationsOf($this->adminId));
        $this->assertSame([], $this->notificationsOf($this->secondRecruiterId));
    }

    public function test_the_same_set_changes_nothing_and_writes_nothing(): void
    {
        $id = $this->insertJob('Shared', 'draft', [$this->adminId, $this->recruiterId]);

        $this->api('PUT', "/company/jobs/{$id}/assignees", ['user_ids' => [$this->recruiterId, $this->adminId]])->assertOk();

        $this->assertSame([], $this->auditEntries());
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_the_set_needs_at_least_one_member_of_this_company(): void
    {
        $id = $this->insertJob('Shared', 'draft', [$this->adminId]);

        $this->api('PUT', "/company/jobs/{$id}/assignees", ['user_ids' => []])->assertStatus(422)->assertJsonValidationErrors(['user_ids']);
        $this->api('PUT', "/company/jobs/{$id}/assignees", [])->assertStatus(422)->assertJsonValidationErrors(['user_ids']);
        $this->api('PUT', "/company/jobs/{$id}/assignees", ['user_ids' => [$this->adminId, $this->otherAdminId]])->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:invalid-assignees')->assertJsonStructure(['errors' => ['user_ids']]);
        $this->api('PUT', "/company/jobs/{$id}/assignees", ['user_ids' => [999999]])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-assignees');
        $this->api('PUT', "/company/jobs/{$id}/assignees", ['user_ids' => [$this->adminId, $this->adminId]])->assertStatus(422)->assertJsonValidationErrors(['user_ids.1']);

        $this->assertSame([$this->adminId], $this->assigneesOf($id));
        $this->assertSame([], $this->auditEntries());
    }

    public function test_a_recruiter_cannot_change_the_assignees(): void
    {
        $id = $this->insertJob('Mine', 'draft', [$this->recruiterId]);

        $this->api('PUT', "/company/jobs/{$id}/assignees", ['user_ids' => [$this->recruiterId, $this->secondRecruiterId]], $this->recruiterToken)
            ->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:company-permission-required');

        $this->assertSame([$this->recruiterId], $this->assigneesOf($id));
    }

    public function test_another_company_admin_cannot_change_them_either(): void
    {
        $id = $this->insertJob('Mine', 'draft', [$this->adminId]);

        $this->api('PUT', "/company/jobs/{$id}/assignees", ['user_ids' => [$this->otherAdminId]], $this->otherAdminToken)->assertNotFound();
        $this->api('PUT', '/company/jobs/999999/assignees', ['user_ids' => [$this->adminId]])->assertNotFound();
    }

    public function test_the_assignees_can_be_changed_in_every_status(): void
    {
        foreach (['pending_review', 'active', 'closed', 'expired', 'rejected', 'taken_down'] as $status) {
            $id = $this->insertJob("Job {$status}", $status, [$this->adminId]);

            $this->api('PUT', "/company/jobs/{$id}/assignees", ['user_ids' => [$this->adminId, $this->recruiterId]])->assertOk();
            $this->assertEqualsCanonicalizing([$this->adminId, $this->recruiterId], $this->assigneesOf($id), $status);
        }
    }

    // ----- a member leaves -----

    public function test_removing_a_member_ends_their_assignments_and_a_job_left_without_assignees_goes_to_the_owner(): void
    {
        $alone = $this->insertJob('Only Rina', 'active', [$this->recruiterId]);
        $shared = $this->insertJob('Rina and Sari', 'active', [$this->recruiterId, $this->secondRecruiterId]);
        $other = $this->insertJob('Sari only', 'active', [$this->secondRecruiterId]);
        $theirs = $this->insertJob('Other company', 'active', [$this->otherAdminId], $this->otherCompanyId);

        $this->api('DELETE', "/company/members/{$this->recruiterId}")->assertNoContent();

        $this->assertSame([$this->adminId], $this->assigneesOf($alone));
        $this->assertSame([$this->secondRecruiterId], $this->assigneesOf($shared));
        $this->assertSame([$this->secondRecruiterId], $this->assigneesOf($other));
        $this->assertSame([$this->otherAdminId], $this->assigneesOf($theirs));
        $this->assertSame(0, DB::table('job_assignments')->where('user_id', $this->recruiterId)->count());
    }

    public function test_the_hand_over_is_audited_as_the_system_and_the_new_assignee_is_told(): void
    {
        $alone = $this->insertJob('Only Rina', 'active', [$this->recruiterId]);
        $shared = $this->insertJob('Rina and Sari', 'active', [$this->recruiterId, $this->secondRecruiterId]);

        $this->api('DELETE', "/company/members/{$this->recruiterId}")->assertNoContent();

        $entries = $this->auditEntries();
        $this->assertCount(2, $entries);

        $byJob = collect($entries)->keyBy(fn ($entry): int => (int) $entry->subject_id);
        $this->assertSame(['system', null], [$byJob[$alone]->actor_type, $byJob[$alone]->actor_id]);
        $this->assertSame(['added' => [$this->adminId], 'removed' => [$this->recruiterId]], json_decode((string) $byJob[$alone]->metadata, true));
        $this->assertSame(['added' => [], 'removed' => [$this->recruiterId]], json_decode((string) $byJob[$shared]->metadata, true));

        $this->assertSame([['type' => 'job.assigned', 'data' => ['job_id' => $alone]]], $this->notificationsOf($this->adminId));
        $this->assertSame([], $this->notificationsOf($this->recruiterId), 'the removed member account is closed');
        $this->assertSame([], $this->notificationsOf($this->secondRecruiterId));
    }

    public function test_a_removed_company_admin_hands_their_jobs_to_the_owner(): void
    {
        $secondAdmin = $this->jobMember($this->companyId, 'Dewi Admin', 'dewi@example.test', 'company_admin');
        $job = $this->insertJob('Admin only', 'draft', [$secondAdmin]);

        $this->api('DELETE', "/company/members/{$secondAdmin}")->assertNoContent();

        $this->assertSame([$this->adminId], $this->assigneesOf($job));
    }

    public function test_running_the_hand_over_again_changes_nothing(): void
    {
        $alone = $this->insertJob('Only Rina', 'active', [$this->recruiterId]);
        $this->api('DELETE', "/company/members/{$this->recruiterId}")->assertNoContent();

        $this->app->make(ReassignJobsOfRemovedMember::class)->execute($this->companyId, $this->recruiterId, now()->toDateTimeImmutable());

        $this->assertSame([$this->adminId], $this->assigneesOf($alone));
        $this->assertCount(1, $this->auditEntries());
    }

    public function test_the_remover_may_pick_the_company_admin_who_takes_the_jobs(): void
    {
        $secondAdmin = $this->jobMember($this->companyId, 'Dewi Admin', 'dewi@example.test', 'company_admin');
        $alone = $this->insertJob('Only Rina', 'active', [$this->recruiterId]);
        $shared = $this->insertJob('Rina and Sari', 'active', [$this->recruiterId, $this->secondRecruiterId]);

        $this->api('DELETE', "/company/members/{$this->recruiterId}", ['transfer_to_user_id' => $secondAdmin])->assertNoContent();

        $this->assertSame([$secondAdmin], $this->assigneesOf($alone));
        $this->assertSame([$this->secondRecruiterId], $this->assigneesOf($shared));
        $this->assertSame(
            $secondAdmin,
            (int) json_decode((string) DB::table('platform_audit_logs')->where('action', 'company.member_removed')->value('metadata'), true)['transfer_to_user_id'],
        );
    }

    public function test_the_recipient_of_the_jobs_must_be_an_active_company_admin_of_the_company(): void
    {
        $this->insertJob('Only Rina', 'active', [$this->recruiterId]);
        $secondAdmin = $this->jobMember($this->companyId, 'Dewi Admin', 'dewi@example.test', 'company_admin');
        DB::table('company_members')->where('user_id', $secondAdmin)->update(['status' => 'suspended']);

        foreach ([$this->secondRecruiterId, $this->otherAdminId, $this->recruiterId, $secondAdmin, 999999] as $target) {
            $this->api('DELETE', "/company/members/{$this->recruiterId}", ['transfer_to_user_id' => $target])
                ->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:transfer-target-not-company-admin');
        }

        $this->assertTrue(DB::table('company_members')->where('user_id', $this->recruiterId)->exists());
    }
}
