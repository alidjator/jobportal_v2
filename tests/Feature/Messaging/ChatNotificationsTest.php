<?php

namespace Tests\Feature\Messaging;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Messaging\Infrastructure\Mail\UnreadMessageMail;
use Tests\Concerns\BuildsChatFixtures;
use Tests\FeatureTestCase;

final class ChatNotificationsTest extends FeatureTestCase
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

    /** @return list<object> the unread chat notifications of the user */
    private function notifications(int $userId): array
    {
        return DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $userId)->where('type', 'message.received')->orderBy('created_at')->get()->all();
    }

    public function test_a_message_from_the_job_seeker_notifies_the_assigned_members_once_per_conversation_while_unread(): void
    {
        $id = $this->seekerStarts('one');

        $this->assertCount(1, $this->notifications($this->recruiterId));
        $this->assertSame([], $this->notifications($this->adminId));
        $this->assertSame([], $this->notifications($this->secondRecruiterId));

        $this->say($id, 'two', $this->seekerToken, side: 'seeker')->assertCreated();

        $rows = $this->notifications($this->recruiterId);
        $this->assertCount(1, $rows);
        $data = json_decode($rows[0]->data, true);
        $this->assertSame($id, $data['conversation_id']);
        $this->assertSame(2, DB::table('messaging_messages')->where('conversation_id', $id)->count());
        // Ids only: never the text or a name.
        $this->assertStringNotContainsString('two', $rows[0]->data);

        DB::table('notifications')->where('id', $rows[0]->id)->update(['read_at' => now()]);
        $this->say($id, 'three', $this->seekerToken, side: 'seeker')->assertCreated();

        $this->assertCount(2, $this->notifications($this->recruiterId));
    }

    public function test_the_member_who_last_wrote_is_the_one_who_is_notified_of_the_reply(): void
    {
        $id = $this->seekerStarts();
        $this->say($id, 'Reply', $this->adminToken)->assertCreated();
        DB::table('notifications')->delete();

        $this->say($id, 'Thanks', $this->seekerToken, side: 'seeker')->assertCreated();

        $this->assertCount(1, $this->notifications($this->adminId));
        $this->assertSame([], $this->notifications($this->recruiterId));
    }

    public function test_a_conversation_a_member_started_notifies_that_member(): void
    {
        $outsider = $this->seeker('outsider@example.test');
        $id = $this->companyStarts($outsider)->assertCreated()->json('data.id');
        DB::table('notifications')->delete();

        $this->say($id, 'Hi, interested', $this->tokenFor($outsider), side: 'seeker')->assertCreated();

        $this->assertCount(1, $this->notifications($this->recruiterId));
    }

    public function test_a_message_from_the_company_notifies_the_job_seeker(): void
    {
        $id = $this->seekerStarts();
        $this->say($id, 'Hello', $this->recruiterToken)->assertCreated();

        $this->assertCount(1, $this->notifications($this->seekerId));
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $this->seekerId)->whereNotNull('read_at')->count());
    }

    public function test_an_unread_message_is_emailed_once_after_fifteen_minutes_and_not_again_until_read(): void
    {
        Mail::fake();
        $id = $this->seekerStarts();
        $this->say($id, 'Hello', $this->recruiterToken)->assertCreated();

        $this->artisan('messaging:send-unread-emails')->assertSuccessful();
        Mail::assertNothingQueued();

        Carbon::setTestNow(now()->addMinutes(10));
        $this->say($id, 'Anything else?', $this->recruiterToken)->assertCreated();
        $this->artisan('messaging:send-unread-emails')->assertSuccessful();
        Mail::assertNothingQueued();

        // Fifteen minutes count from the first unread message.
        Carbon::setTestNow(now()->addMinutes(6));
        $this->artisan('messaging:send-unread-emails')->assertSuccessful();
        Mail::assertQueued(UnreadMessageMail::class, 1);
        Mail::assertQueued(UnreadMessageMail::class, fn (UnreadMessageMail $mail): bool => $mail->hasTo('siti@example.test'));

        Carbon::setTestNow(now()->addHour());
        $this->say($id, 'Still there?', $this->recruiterToken)->assertCreated();
        $this->artisan('messaging:send-unread-emails')->assertSuccessful();
        Mail::assertQueued(UnreadMessageMail::class, 1);

        // Reading starts the cycle again.
        $this->api('POST', "/me/conversations/{$id}/read", token: $this->seekerToken)->assertNoContent();
        $this->say($id, 'New topic', $this->recruiterToken)->assertCreated();
        Carbon::setTestNow(now()->addMinutes(16));
        $this->artisan('messaging:send-unread-emails')->assertSuccessful();
        Mail::assertQueued(UnreadMessageMail::class, 2);
    }

    public function test_a_message_read_in_time_is_not_emailed(): void
    {
        Mail::fake();
        $id = $this->seekerStarts();
        $this->say($id, 'Hello', $this->recruiterToken)->assertCreated();
        $this->api('POST', "/me/conversations/{$id}/read", token: $this->seekerToken)->assertNoContent();

        Carbon::setTestNow(now()->addMinutes(20));
        $this->artisan('messaging:send-unread-emails')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_the_preference_can_switch_the_reminder_email_off(): void
    {
        Mail::fake();
        $id = $this->seekerStarts();
        $this->api('PUT', '/me/notification-preferences/chat_message/email', ['enabled' => false], $this->seekerToken)->assertNoContent();
        $this->say($id, 'Hello', $this->recruiterToken)->assertCreated();

        Carbon::setTestNow(now()->addMinutes(16));
        $this->artisan('messaging:send-unread-emails')->assertSuccessful();

        // The seeker switched the reminder off, so nothing is sent (the recruiter wrote last and has nothing unread).
        Mail::assertNothingQueued();
    }

    public function test_anonymising_the_job_seeker_deletes_their_files_and_texts_but_keeps_the_thread(): void
    {
        Storage::fake();
        $id = $this->seekerStarts('My private words');
        $this->say($id, 'Company reply', $this->recruiterToken, [$this->upload('offer.pdf', $this->pdf())])->assertCreated();
        $mine = $this->say($id, 'More', $this->seekerToken, [$this->upload('cv.pdf', $this->pdf())], 'seeker')->assertCreated();
        $minePath = DB::table('messaging_attachments')->where('owner_user_id', $this->seekerId)->value('path');
        $companyPath = DB::table('messaging_attachments')->where('owner_user_id', $this->recruiterId)->value('path');

        DB::table('identity_users')->where('id', $this->seekerId)->update(['anonymized_at' => now()]);
        Event::dispatch(new UserAnonymized($this->seekerId, now()->toDateTimeImmutable()));

        Storage::assertMissing($minePath);
        Storage::assertExists($companyPath);
        $this->assertSame(0, DB::table('messaging_attachments')->where('owner_user_id', $this->seekerId)->count());
        $this->assertSame(3, DB::table('messaging_messages')->where('conversation_id', $id)->count());
        $this->assertSame(0, DB::table('messaging_messages')->where('sender_user_id', $this->seekerId)->whereNotNull('body')->count());
        $this->assertSame('Company reply', DB::table('messaging_messages')->where('sender_user_id', $this->recruiterId)->value('body'));

        $messages = $this->api('GET', "/company/conversations/{$id}/messages", token: $this->recruiterToken)->json('data');
        $this->assertSame('Deleted user', $messages[0]['sender']['name']);
        $this->assertSame([], $messages[0]['attachments']);
        $this->assertNotNull($mine->json('data.id'));

        // Running it again changes nothing.
        Event::dispatch(new UserAnonymized($this->seekerId, now()->toDateTimeImmutable()));
        $this->assertSame(3, DB::table('messaging_messages')->where('conversation_id', $id)->count());
    }
}
