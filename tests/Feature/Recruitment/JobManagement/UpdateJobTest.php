<?php

namespace Tests\Feature\Recruitment\JobManagement;

use Illuminate\Support\Facades\DB;
use Tests\FeatureTestCase;

class UpdateJobTest extends FeatureTestCase
{
    use SeedsJobManagement;

    private function existingJob(array $override = []): array
    {
        $id = $this->insertJob($this->companyId, $override);

        return [$id, DB::table('jobs')->where('id', $id)->value('slug')];
    }

    public function test_requires_authentication(): void
    {
        $this->putJson('/api/company/update/x/job', [])->assertUnauthorized();
    }

    public function test_updates_in_place_when_edits_are_auto_approved(): void
    {
        $this->settings(['edited_job_auto_approved' => 1]);
        $this->actingAsCompany();
        $this->seedMaster();
        [$id, $slug] = $this->existingJob(['status' => 'active']);

        $response = $this->putJson("/api/company/update/{$slug}/job", $this->jobPayload(['title' => 'Renamed '.$this->companyId, 'vacancies' => '9']))
            ->assertOk();

        $this->assertSame('Job Updated Successfully', $response->json('data.data.message'));
        $this->assertSame($id, $response->json('data.data.job.id'));
        $this->assertSame('renamed-'.$this->companyId, $response->json('data.data.job.slug'));
        $this->assertSame('active', $response->json('data.data.job.status'));
        $this->assertSame('9', $response->json('data.data.job.vacancies'));
        $this->assertSame('Coblong, Kota Bandung', $response->json('data.data.job.exact_location'));
        $this->assertSame(1, DB::table('jobs')->where('company_id', $this->companyId)->count());
    }

    public function test_in_place_update_expires_job_when_deadline_passed_and_syncs_tags(): void
    {
        $this->settings(['edited_job_auto_approved' => 1]);
        $this->actingAsCompany();
        $this->seedMaster();
        [$id, $slug] = $this->existingJob(['status' => 'active']);
        $old = DB::table('tags')->insertGetId([]);
        DB::table('tag_translations')->insert(['tag_id' => $old, 'name' => 'oldtag'.$id, 'locale' => 'en']);
        DB::table('job_tag')->insert(['job_id' => $id, 'tag_id' => $old]);

        $this->putJson("/api/company/update/{$slug}/job", $this->jobPayload([
            'deadline' => now()->subDay()->format('Y-m-d'),
            'tags' => ['freshtag'.$id],
        ]))->assertOk()->assertJsonPath('data.data.job.status', 'expired');

        $tagIds = DB::table('job_tag')->where('job_id', $id)->pluck('tag_id')->all();
        $this->assertCount(1, $tagIds);
        $this->assertNotContains($old, $tagIds);
    }

    public function test_pending_job_stays_pending_after_edit_unless_auto_approved(): void
    {
        $this->settings(['edited_job_auto_approved' => 0, 'job_auto_approved' => 0]);
        $this->actingAsCompany();
        $this->seedMaster();
        [$id, $slug] = $this->existingJob(['status' => 'pending']);

        $this->putJson("/api/company/update/{$slug}/job", $this->jobPayload())
            ->assertOk()
            ->assertJsonPath('data.data.job.id', $id)
            ->assertJsonPath('data.data.job.status', 'pending')
            ->assertJsonPath('data.data.message', 'Job Updated Successfully');
    }

    public function test_edit_of_approved_job_creates_copy_waiting_for_approval(): void
    {
        $this->settings(['edited_job_auto_approved' => 0]);
        $this->actingAsCompany();
        $this->seedMaster();
        [$id, $slug] = $this->existingJob(['status' => 'active', 'title' => 'Original '.$this->companyId, 'featured' => 1]);

        $first = $this->putJson("/api/company/update/{$slug}/job", $this->jobPayload(['title' => 'Edited '.$this->companyId]))
            ->assertOk()->json('data.data');

        $this->assertNotSame($id, $first['job']['id']);
        $this->assertSame($id, $first['job']['parent_job_id']);
        $this->assertSame(1, $first['job']['waiting_for_edit_approval']);
        $this->assertSame('pending', $first['job']['status']);
        $this->assertFalse($first['job']['featured']);
        $this->assertSame('Your job successfully updated. Please wait for approve changes', $first['message']);
        $this->assertSame('Original '.$this->companyId, DB::table('jobs')->where('id', $id)->value('title'));
        $this->assertSame('active', DB::table('jobs')->where('id', $id)->value('status'));
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->userId,
            'type' => 'App\Notifications\Website\Company\EditApproveNotification',
        ]);

        // edit kedua memperbarui salinan yang sama
        $second = $this->putJson("/api/company/update/{$slug}/job", $this->jobPayload(['title' => 'Edited again '.$this->companyId]))
            ->assertOk()->json('data.data.job');
        $this->assertSame($first['job']['id'], $second['id']);
        $this->assertSame(2, DB::table('jobs')->where('company_id', $this->companyId)->count());
    }

    public function test_foreign_or_missing_job_is_404(): void
    {
        $this->makeCompanyUser();
        $foreign = $this->insertJob($this->companyId, ['title' => 'Foreign '.random_int(1, 9999999)]);
        $foreignSlug = DB::table('jobs')->where('id', $foreign)->value('slug');
        $this->actingAsCompany();
        $this->seedMaster();

        $this->putJson("/api/company/update/{$foreignSlug}/job", $this->jobPayload())->assertNotFound();
        $this->assertSame('active', DB::table('jobs')->where('id', $foreign)->value('status'));
        $this->putJson('/api/company/update/nope-'.random_int(1, 999999).'/job', $this->jobPayload())->assertNotFound();
    }

    public function test_validation_errors_and_own_job_order_id_allowed(): void
    {
        $this->settings();
        $this->actingAsCompany();
        $this->seedMaster();
        $orderId = random_int(100000, 99999999);
        [$id, $slug] = $this->existingJob(['job_order_id' => $orderId]);
        $this->insertJob($this->companyId, ['job_order_id' => $orderId + 1]);

        $this->putJson("/api/company/update/{$slug}/job", [])->assertStatus(422)->assertJsonValidationErrors(['title', 'category_id']);
        $this->putJson("/api/company/update/{$slug}/job", $this->jobPayload(['min_salary' => 10, 'max_salary' => 5]))
            ->assertStatus(422)->assertJsonValidationErrors(['max_salary']);
        $this->putJson("/api/company/update/{$slug}/job", $this->jobPayload(['job_order_id' => $orderId + 1]))
            ->assertStatus(422)->assertJsonValidationErrors(['job_order_id']);
        $this->putJson("/api/company/update/{$slug}/job", $this->jobPayload(['job_order_id' => $orderId]))->assertOk();
    }
}
