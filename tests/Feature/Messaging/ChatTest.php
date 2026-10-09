<?php

namespace Tests\Feature\Messaging;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Src\Messaging\Application\ChannelAuthorizer;
use Src\Messaging\Application\CompanyPresence;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyMembership;
use Tests\Concerns\BuildsChatFixtures;
use Tests\FeatureTestCase;

final class ChatTest extends FeatureTestCase
{
    use BuildsChatFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildChatFixtures();
    }

    protected function tearDown(): void
    {
        $this->tearDownChatFixtures();

        parent::tearDown();
    }

    public function test_a_job_seeker_can_start_a_conversation_only_with_a_company_they_applied_to(): void
    {
        $stranger = $this->tokenFor($this->seeker('stranger@example.test'));

        $this->multipartWithFiles('POST', '/me/conversations', ['company_id' => $this->companyId, 'body' => 'Hi'], [], $stranger)->assertNotFound();
        $this->multipartWithFiles('POST', '/me/conversations', ['company_id' => $this->otherCompanyId, 'body' => 'Hi'], [], $this->seekerToken)->assertNotFound();

        $id = $this->seekerStarts('First');
        $again = $this->multipartWithFiles('POST', '/me/conversations', ['company_id' => $this->companyId, 'body' => 'Second'], [], $this->seekerToken)->assertOk();

        $this->assertSame($id, $again->json('data.id'));
        $this->assertSame(1, DB::table('messaging_conversations')->count());
        $this->assertSame(2, DB::table('messaging_messages')->where('conversation_id', $id)->count());
    }

    public function test_an_application_that_was_withdrawn_or_rejected_still_lets_the_job_seeker_write(): void
    {
        $this->api('POST', "/me/applications/{$this->applicationId}/withdraw", token: $this->seekerToken)->assertNoContent();

        $this->seekerStarts('Can I ask why?');
    }

    public function test_a_message_needs_a_verified_email_to_be_sent(): void
    {
        $unverified = $this->tokenFor($this->seeker('new@example.test', null));

        $this->multipartWithFiles('POST', '/me/conversations', ['company_id' => $this->companyId, 'body' => 'Hi'], [], $unverified)
            ->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:email-not-verified');
    }

    public function test_a_member_can_write_first_to_a_job_seeker_who_has_not_applied_and_only_the_starter_and_admins_see_it(): void
    {
        $outsider = $this->seeker('outsider@example.test', name: 'Out Sider');

        $response = $this->companyStarts($outsider)->assertCreated();
        $id = $response->json('data.id');

        $this->assertSame('Out Sider', $response->json('data.job_seeker.name'));
        $this->assertArrayNotHasKey('company_online', $response->json('data'));
        $this->api('GET', "/company/conversations/{$id}", token: $this->adminToken)->assertOk();
        $this->api('GET', "/company/conversations/{$id}", token: $this->secondRecruiterToken)->assertNotFound();
        $this->assertSame([], $this->api('GET', '/company/conversations', token: $this->secondRecruiterToken)->json('data'));

        // Another member who may not see the thread cannot start a second one in it.
        $this->companyStarts($outsider, token: $this->secondRecruiterToken)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:conversation-exists');
        // The admin joins the existing thread.
        $this->companyStarts($outsider, 'Admin here', $this->adminToken)->assertOk();
    }

    public function test_a_member_cannot_start_a_conversation_with_a_company_member_or_an_unknown_account(): void
    {
        $this->companyStarts($this->secondRecruiterId)->assertNotFound();
        $this->companyStarts(999999)->assertNotFound();
    }

    public function test_a_recruiter_sees_the_conversations_of_applicants_of_their_jobs_and_an_unassigned_one_does_not(): void
    {
        $id = $this->seekerStarts();

        $this->api('GET', "/company/conversations/{$id}/messages", token: $this->recruiterToken)->assertOk()->assertJsonCount(1, 'data');
        $this->api('GET', "/company/conversations/{$id}/messages", token: $this->adminToken)->assertOk();
        $this->api('GET', "/company/conversations/{$id}/messages", token: $this->secondRecruiterToken)->assertNotFound();
        $this->api('GET', "/company/conversations/{$id}/messages", token: $this->otherAdminToken)->assertNotFound();
        $this->api('GET', "/me/conversations/{$id}/messages", token: $this->tokenFor($this->seeker('other@example.test')))->assertNotFound();

        $this->assertCount(1, $this->api('GET', '/company/conversations', token: $this->recruiterToken)->json('data'));
        $this->assertCount(1, $this->api('GET', '/company/conversations', token: $this->adminToken)->json('data'));
        $this->assertCount(0, $this->api('GET', '/company/conversations', token: $this->secondRecruiterToken)->json('data'));
        $this->assertCount(0, $this->api('GET', '/company/conversations', token: $this->otherAdminToken)->json('data'));
    }

    public function test_a_message_carries_pdf_jpg_and_png_files_read_by_their_content(): void
    {
        Storage::fake();
        $id = $this->seekerStarts();

        $response = $this->say($id, 'My documents', $this->recruiterToken, [
            $this->upload('cv.pdf', $this->pdf()),
            $this->upload('photo.png', $this->jpeg()),
            $this->upload('scan.jpg', $this->png()),
        ])->assertCreated();

        $files = $response->json('data.attachments');
        $this->assertSame(['application/pdf', 'image/jpeg', 'image/png'], array_column($files, 'mime'));
        $this->assertSame(['cv.pdf', 'photo.png', 'scan.jpg'], array_column($files, 'name'));
        $this->assertSame('Rina Rekruter', $response->json('data.sender.name'));

        $messageId = $response->json('data.id');
        $download = $this->api('GET', "/me/messages/{$messageId}/attachments/{$files[1]['id']}", token: $this->seekerToken);
        $download->assertOk();
        $this->assertSame('image/jpeg', $download->headers->get('Content-Type'));
        $this->assertSame('nosniff', $download->headers->get('X-Content-Type-Options'));
        $this->assertSame($this->jpeg(), $download->getContent());
        $this->api('GET', "/company/messages/{$messageId}/attachments/{$files[0]['id']}", token: $this->adminToken)->assertOk();

        // Another member or another company cannot download them.
        $this->api('GET', "/company/messages/{$messageId}/attachments/{$files[0]['id']}", token: $this->secondRecruiterToken)->assertNotFound();
        $this->api('GET', "/company/messages/{$messageId}/attachments/{$files[0]['id']}", token: $this->otherAdminToken)->assertNotFound();
        $this->api('GET', "/me/messages/{$messageId}/attachments/999999", token: $this->seekerToken)->assertNotFound();
    }

    public function test_a_file_that_is_not_a_pdf_jpg_or_png_is_refused_whatever_its_name(): void
    {
        $id = $this->seekerStarts();

        $this->say($id, null, $this->seekerToken, [$this->upload('cv.pdf', 'plain text, not a pdf')], 'seeker')
            ->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-attachment');
        $this->say($id, null, $this->seekerToken, [$this->upload('cv.pdf', "%PDF-1.4\n/JavaScript (alert(1))")], 'seeker')
            ->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-attachment');
        $this->assertSame(0, DB::table('messaging_attachments')->count());
    }

    public function test_the_limits_on_text_files_and_size_are_enforced_and_nothing_is_stored_when_a_send_is_refused(): void
    {
        Storage::fake();
        $id = $this->seekerStarts();

        $this->say($id, '   ', $this->seekerToken, side: 'seeker')->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:empty-message');
        $this->say($id, str_repeat('a', 5001), $this->seekerToken, side: 'seeker')->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:body-too-long');
        $this->say($id, str_repeat('a', 5000), $this->seekerToken, side: 'seeker')->assertCreated();

        $six = array_map(fn (int $n) => $this->upload("f{$n}.png", $this->png()), range(1, 6));
        $this->say($id, 'Too many', $this->seekerToken, $six, 'seeker')->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:too-many-attachments');

        $big = "%PDF-1.4\n".str_repeat('a', 5 * 1024 * 1024);
        $this->say($id, 'Too big', $this->seekerToken, [$this->upload('big.pdf', $big)], 'seeker')->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:file-too-large');

        $this->assertSame(0, DB::table('messaging_attachments')->count());
        $this->assertSame([], Storage::allFiles('messaging'));
    }

    public function test_the_admin_can_change_the_largest_attachment(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal('messaging.max_attachment_bytes', 102_400, $this->adminId);
        $id = $this->seekerStarts();

        $this->say($id, null, $this->seekerToken, [$this->upload('a.png', $this->png().str_repeat('a', 102_400))], 'seeker')->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:file-too-large');
        $this->say($id, null, $this->seekerToken, [$this->upload('a.png', $this->png())], 'seeker')->assertCreated();
    }

    public function test_messages_are_listed_newest_first_with_a_cursor(): void
    {
        $id = $this->seekerStarts('one');
        $this->say($id, 'two', $this->recruiterToken)->assertCreated();
        $this->say($id, 'three', $this->seekerToken, side: 'seeker')->assertCreated();

        $first = $this->api('GET', "/me/conversations/{$id}/messages?per_page=2", token: $this->seekerToken)->assertOk();
        $this->assertSame(['three', 'two'], array_column($first->json('data'), 'body'));

        $second = $this->api('GET', "/me/conversations/{$id}/messages?per_page=2&cursor=".$first->json('meta.next_cursor'), token: $this->seekerToken)->assertOk();
        $this->assertSame(['one'], array_column($second->json('data'), 'body'));
        $this->assertNull($second->json('meta.next_cursor'));
        $this->api('GET', "/me/conversations/{$id}/messages?cursor=garbage", token: $this->seekerToken)->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-cursor');
    }

    public function test_unread_counts_are_per_person_and_reading_clears_them(): void
    {
        $id = $this->seekerStarts();

        $this->assertSame(1, $this->api('GET', '/company/conversations/unread-count', token: $this->recruiterToken)->json('data.unread_conversations'));
        $this->assertSame(1, $this->api('GET', '/company/conversations/unread-count', token: $this->adminToken)->json('data.unread_conversations'));
        $this->assertSame(0, $this->api('GET', '/me/conversations/unread-count', token: $this->seekerToken)->json('data.unread_conversations'));
        $this->assertTrue($this->api('GET', "/company/conversations/{$id}", token: $this->recruiterToken)->json('data.unread'));

        $this->api('POST', "/company/conversations/{$id}/read", token: $this->recruiterToken)->assertNoContent();

        $this->assertSame(0, $this->api('GET', '/company/conversations/unread-count', token: $this->recruiterToken)->json('data.unread_conversations'));
        $this->assertSame(1, $this->api('GET', '/company/conversations/unread-count', token: $this->adminToken)->json('data.unread_conversations'));

        $this->say($id, 'Yes it is', $this->recruiterToken)->assertCreated();

        $this->assertSame(1, $this->api('GET', '/me/conversations/unread-count', token: $this->seekerToken)->json('data.unread_conversations'));
        $this->assertSame(0, $this->api('GET', '/company/conversations/unread-count', token: $this->recruiterToken)->json('data.unread_conversations'));
        $this->api('POST', "/me/conversations/{$id}/read", token: $this->seekerToken)->assertNoContent();
        $this->assertSame(0, $this->api('GET', '/me/conversations/unread-count', token: $this->seekerToken)->json('data.unread_conversations'));
    }

    public function test_a_sender_can_delete_their_own_message_within_fifteen_minutes_and_its_files_go(): void
    {
        Storage::fake();
        $id = $this->seekerStarts();
        $message = $this->say($id, 'Oops', $this->recruiterToken, [$this->upload('cv.pdf', $this->pdf())])->assertCreated();
        $messageId = $message->json('data.id');
        $path = DB::table('messaging_attachments')->value('path');
        Storage::assertExists($path);

        $this->api('DELETE', "/me/messages/{$messageId}", token: $this->seekerToken)->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:not-your-message');
        $this->api('DELETE', "/company/messages/{$messageId}", token: $this->adminToken)->assertForbidden();

        $this->api('DELETE', "/company/messages/{$messageId}", token: $this->recruiterToken)->assertNoContent();
        $this->api('DELETE', "/company/messages/{$messageId}", token: $this->recruiterToken)->assertNoContent();

        Storage::assertMissing($path);
        $this->assertSame(0, DB::table('messaging_attachments')->count());
        $shown = $this->api('GET', "/me/conversations/{$id}/messages", token: $this->seekerToken)->json('data.0');
        $this->assertTrue($shown['deleted']);
        $this->assertNull($shown['body']);
        $this->assertSame([], $shown['attachments']);
        $this->api('GET', "/me/messages/{$messageId}/attachments/1", token: $this->seekerToken)->assertNotFound();
    }

    public function test_a_message_can_no_longer_be_deleted_after_fifteen_minutes(): void
    {
        $id = $this->seekerStarts();
        $messageId = $this->say($id, 'Late', $this->seekerToken, side: 'seeker')->json('data.id');

        Carbon::setTestNow(now()->addMinutes(16));

        $this->api('DELETE', "/me/messages/{$messageId}", token: $this->seekerToken)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:delete-window-over');
    }

    public function test_the_company_can_close_a_conversation_and_reopen_it(): void
    {
        $id = $this->seekerStarts();

        $this->api('POST', "/me/conversations/{$id}/close", token: $this->seekerToken)->assertNotFound();
        $this->api('POST', "/company/conversations/{$id}/close", token: $this->secondRecruiterToken)->assertNotFound();
        $closed = $this->api('POST', "/company/conversations/{$id}/close", token: $this->recruiterToken)->assertOk();
        $this->assertTrue($closed->json('data.closed'));

        $this->say($id, 'Hello?', $this->seekerToken, side: 'seeker')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:conversation-closed');
        $this->say($id, 'Hello', $this->recruiterToken)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:conversation-closed');
        $this->multipartWithFiles('POST', '/me/conversations', ['company_id' => $this->companyId, 'body' => 'Hi'], [], $this->seekerToken)->assertStatus(409);

        $this->assertFalse($this->api('POST', "/company/conversations/{$id}/reopen", token: $this->adminToken)->assertOk()->json('data.closed'));
        $this->say($id, 'Hello?', $this->seekerToken, side: 'seeker')->assertCreated();
    }

    public function test_a_suspended_company_cannot_chat_and_a_job_seeker_cannot_write_to_it(): void
    {
        $id = $this->seekerStarts();
        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'suspended']);

        $this->api('GET', '/company/conversations', token: $this->adminToken)->assertForbidden();
        $this->say($id, 'Hi', $this->seekerToken, side: 'seeker')->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:company-suspended');
        $this->api('GET', "/me/conversations/{$id}/messages", token: $this->seekerToken)->assertOk();
    }

    public function test_the_company_side_cannot_write_to_a_job_seeker_whose_account_was_erased(): void
    {
        $id = $this->seekerStarts();
        DB::table('identity_users')->where('id', $this->seekerId)->update(['anonymized_at' => now()]);

        $this->say($id, 'Hello', $this->recruiterToken)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:recipient-unavailable');
    }

    public function test_only_the_job_seeker_is_told_whether_the_company_is_online(): void
    {
        $id = $this->seekerStarts();
        $this->app->instance(CompanyPresence::class, new class implements CompanyPresence
        {
            public function isOnline(int $companyId): bool
            {
                return true;
            }
        });

        $this->assertTrue($this->api('GET', "/me/conversations/{$id}", token: $this->seekerToken)->json('data.company_online'));
        $this->assertArrayNotHasKey('company_online', $this->api('GET', "/company/conversations/{$id}", token: $this->recruiterToken)->json('data'));
    }

    public function test_only_people_who_may_read_a_conversation_can_join_its_channel_and_only_members_the_presence_channel(): void
    {
        $id = $this->seekerStarts();
        $authorizer = $this->app->make(ChannelAuthorizer::class);
        $member = fn (int $userId, string $role, int $company) => Actor::companyMember($userId)->withMembership(new CompanyMembership($company, $role));

        $this->assertTrue($authorizer->mayJoinConversation(Actor::jobSeeker($this->seekerId), $id));
        $this->assertTrue($authorizer->mayJoinConversation($member($this->recruiterId, 'recruiter', $this->companyId), $id));
        $this->assertTrue($authorizer->mayJoinConversation($member($this->adminId, 'company_admin', $this->companyId), $id));
        $this->assertFalse($authorizer->mayJoinConversation($member($this->secondRecruiterId, 'recruiter', $this->companyId), $id));
        $this->assertFalse($authorizer->mayJoinConversation($member($this->otherAdminId, 'company_admin', $this->otherCompanyId), $id));
        $this->assertFalse($authorizer->mayJoinConversation(Actor::jobSeeker($this->seeker('x@example.test')), $id));

        $this->assertTrue($authorizer->mayJoinCompanyPresence($member($this->recruiterId, 'recruiter', $this->companyId), $this->companyId));
        $this->assertFalse($authorizer->mayJoinCompanyPresence($member($this->otherAdminId, 'company_admin', $this->otherCompanyId), $this->companyId));
        $this->assertFalse($authorizer->mayJoinCompanyPresence(Actor::jobSeeker($this->seekerId), $this->companyId));
    }

    public function test_the_company_has_a_daily_cap_on_messages(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal('messaging.max_daily_company_messages', 2, $this->adminId);
        $id = $this->seekerStarts();

        $this->say($id, 'one', $this->recruiterToken)->assertCreated();
        $this->say($id, 'two', $this->recruiterToken)->assertCreated();
        $this->say($id, 'three', $this->recruiterToken)->assertStatus(429)->assertJsonPath('type', 'urn:jobportal:error:daily-limit');
        // The job seeker's messages are not counted.
        $this->say($id, 'still free', $this->seekerToken, side: 'seeker')->assertCreated();

        Carbon::setTestNow(now()->addDay());

        $this->say($id, 'tomorrow', $this->recruiterToken)->assertCreated();
    }

    public function test_the_same_conversation_serves_every_application_of_the_job_seeker(): void
    {
        $id = $this->seekerStarts();
        $otherJob = $this->insertJob('Frontend Developer', 'active', [$this->secondRecruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->apply($otherJob)->assertCreated();

        // Both recruiters now see the one thread.
        $this->api('GET', "/company/conversations/{$id}", token: $this->recruiterToken)->assertOk();
        $this->api('GET', "/company/conversations/{$id}", token: $this->secondRecruiterToken)->assertOk();
        $this->assertSame(1, DB::table('messaging_conversations')->count());
    }
}
