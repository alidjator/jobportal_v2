<?php

namespace Tests\Feature\Messaging;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Messaging\Application\EraseMessagingData;
use Tests\Concerns\BuildsChatFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-platform-admin.md` section 5: chat reports by both sides, the admin's decision, and the job seeker's block. */
final class ChatReportTest extends FeatureTestCase
{
    use BuildsChatFixtures;

    private string $modToken;

    private int $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildChatFixtures();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');

        $admin = AdminModel::query()->create(['name' => 'Moderator', 'email' => 'mod@example.test', 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo('messages.moderate');
        $this->modToken = $admin->createToken('console', ['*'], now()->addDays(30))->plainTextToken;

        $this->conversation = $this->seekerStarts('Hello, is the job still open?');
    }

    protected function tearDown(): void
    {
        $this->tearDownChatFixtures();

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $body */
    private function report(array $body = [], string $side = 'seeker', ?int $conversation = null): TestResponse
    {
        $token = $side === 'seeker' ? $this->seekerToken : $this->recruiterToken;
        $prefix = $side === 'seeker' ? 'me' : 'company';

        return $this->api('POST', "/{$prefix}/conversations/".($conversation ?? $this->conversation).'/report', ['reason' => 'harassment', ...$body], $token);
    }

    /** @param  array<string, mixed>  $body */
    private function moderate(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        return $this->api($method, '/admin'.$uri, $body, $token ?? $this->modToken);
    }

    private function reportRow(int $id): object
    {
        return DB::table('messaging_reports')->where('id', $id)->first();
    }

    /** @return list<array{type: string, data: array<string, mixed>}> */
    private function reviewedNotices(int $userId): array
    {
        return array_map(
            fn ($row): array => ['type' => (string) $row->type, 'data' => json_decode((string) $row->data, true)],
            DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $userId)->where('type', 'report.reviewed')->orderBy('id')->get()->all(),
        );
    }

    public function test_a_job_seeker_reports_a_conversation_and_the_report_keeps_a_snapshot(): void
    {
        $this->say($this->conversation, 'Send me 500k for the uniform.', $this->recruiterToken)->assertCreated();
        $file = $this->say($this->conversation, 'See the attachment', $this->recruiterToken, [$this->upload('account.png', $this->png())])->assertCreated();
        $messageId = (int) $file->json('data.id');

        $response = $this->report(['reason' => 'scam_or_fee', 'note' => 'Asks for money', 'message_id' => $messageId])->assertCreated();

        $this->assertSame(['id', 'conversation_id', 'reason', 'status', 'created_at'], array_keys($response->json('data')));
        $this->assertSame('open', $response->json('data.status'));

        $row = $this->reportRow($response->json('data.id'));
        $this->assertSame('job_seeker', $row->reporter_side);
        $this->assertSame($this->seekerId, (int) $row->reporter_user_id);
        $this->assertSame($messageId, (int) $row->message_id);
        $this->assertSame('Asks for money', $row->note);

        $snapshot = json_decode((string) $row->snapshot, true);
        $this->assertCount(3, $snapshot);
        $this->assertSame(['Hello, is the job still open?', 'Send me 500k for the uniform.', 'See the attachment'], array_column($snapshot, 'text'));
        $this->assertSame(['job_seeker', 'company', 'company'], array_column($snapshot, 'side'));
        $this->assertSame([false, false, true], array_column($snapshot, 'reported'));
        $this->assertSame(['account.png'], $snapshot[2]['attachments']);
        $this->assertSame(['message_id', 'side', 'sent_at', 'text', 'attachments', 'deleted', 'reported'], array_keys($snapshot[2]));
    }

    public function test_the_snapshot_holds_the_reported_message_and_ten_earlier_ones(): void
    {
        $ids = [];

        for ($i = 1; $i <= 14; $i++) {
            $ids[$i] = (int) $this->say($this->conversation, "Message {$i}", $this->recruiterToken)->assertCreated()->json('data.id');
        }

        $this->say($this->conversation, 'Message 15, after the reported one', $this->recruiterToken)->assertCreated();

        $id = $this->report(['message_id' => $ids[12]])->assertCreated()->json('data.id');

        $snapshot = json_decode((string) $this->reportRow($id)->snapshot, true);
        $this->assertCount(11, $snapshot);
        $this->assertSame('Message 2', $snapshot[0]['text']);
        $this->assertSame('Message 12', $snapshot[10]['text']);
        $this->assertTrue($snapshot[10]['reported']);
    }

    public function test_without_a_message_the_snapshot_is_the_latest_messages(): void
    {
        $this->say($this->conversation, 'Latest', $this->recruiterToken)->assertCreated();

        $id = $this->report()->assertCreated()->json('data.id');

        $snapshot = json_decode((string) $this->reportRow($id)->snapshot, true);
        $this->assertSame(['Hello, is the job still open?', 'Latest'], array_column($snapshot, 'text'));
        $this->assertSame([false, false], array_column($snapshot, 'reported'));
    }

    public function test_a_deleted_message_is_marked_and_has_no_text_in_the_snapshot(): void
    {
        $messageId = (int) $this->say($this->conversation, 'Oops', $this->recruiterToken)->assertCreated()->json('data.id');
        $this->api('DELETE', "/company/messages/{$messageId}", token: $this->recruiterToken)->assertNoContent();

        $id = $this->report()->assertCreated()->json('data.id');

        $snapshot = json_decode((string) $this->reportRow($id)->snapshot, true);
        $this->assertTrue($snapshot[1]['deleted']);
        $this->assertNull($snapshot[1]['text']);
    }

    public function test_a_company_member_reports_a_conversation_they_can_see(): void
    {
        $this->report(['reason' => 'spam'], 'company')->assertCreated();

        $this->assertSame('company', DB::table('messaging_reports')->value('reporter_side'));
        $this->assertSame($this->recruiterId, (int) DB::table('messaging_reports')->value('reporter_user_id'));
    }

    public function test_one_open_report_per_reporter_and_a_decision_makes_room_for_a_new_one(): void
    {
        $first = $this->report()->assertCreated()->json('data.id');

        $this->report()->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:already-reported');
        $this->report([], 'company')->assertCreated();

        $this->moderate('POST', "/message-reports/{$first}/dismiss")->assertOk();

        $this->report()->assertCreated();
        $this->assertSame(3, DB::table('messaging_reports')->count());
    }

    public function test_the_report_is_validated(): void
    {
        $this->report(['reason' => 'unknown'])->assertUnprocessable();
        $this->report(['reason' => 'other'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-report')->assertJsonPath('errors.note.0', 'Tell us what is wrong with this conversation.');
        $this->report(['note' => str_repeat('a', 1001)])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-report');
        $this->report(['message_id' => 999999])->assertStatus(422)->assertJsonPath('errors.message_id.0', 'The message is not part of this conversation.');
        $this->assertSame(0, DB::table('messaging_reports')->count());
    }

    public function test_a_message_of_another_conversation_cannot_be_reported(): void
    {
        $otherSeeker = $this->seeker('other@example.test');
        $other = (int) DB::table('messaging_conversations')->insertGetId([
            'company_id' => $this->otherCompanyId, 'job_seeker_user_id' => $otherSeeker, 'started_by_user_id' => $otherSeeker, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $foreign = (int) DB::table('messaging_messages')->insertGetId(['conversation_id' => $other, 'sender_side' => 'seeker', 'sender_user_id' => $otherSeeker, 'body' => 'Hi from another', 'created_at' => now()]);

        $this->report(['message_id' => $foreign])->assertStatus(422);
    }

    public function test_only_a_party_of_the_conversation_can_report_it(): void
    {
        $stranger = $this->tokenFor($this->seeker('stranger@example.test'));

        $this->api('POST', "/me/conversations/{$this->conversation}/report", ['reason' => 'spam'], $stranger)->assertNotFound();
        $this->api('POST', "/company/conversations/{$this->conversation}/report", ['reason' => 'spam'], $this->tokenFor($this->otherAdminId))->assertNotFound();
        $this->assertSame(0, DB::table('messaging_reports')->count());
    }

    public function test_a_closed_or_blocked_conversation_can_still_be_reported(): void
    {
        $this->api('POST', "/company/conversations/{$this->conversation}/close", token: $this->recruiterToken)->assertOk();
        $this->api('POST', "/me/conversations/{$this->conversation}/block", token: $this->seekerToken)->assertOk();

        $this->report()->assertCreated();
        $this->report([], 'company')->assertCreated();
    }

    public function test_the_admin_lists_reads_and_dismisses_reports(): void
    {
        $this->say($this->conversation, 'Pay a fee first.', $this->recruiterToken)->assertCreated();
        $first = $this->report(['reason' => 'scam_or_fee', 'note' => 'Wants money'])->assertCreated()->json('data.id');
        $second = $this->report(['reason' => 'spam'], 'company')->assertCreated()->json('data.id');

        $list = $this->moderate('GET', '/message-reports')->assertOk();
        $this->assertSame([$first, $second], $list->json('data.*.id'));
        $this->assertSame(['id', 'conversation_id', 'reporter_side', 'reason', 'status', 'created_at', 'decided_at'], array_keys($list->json('data.0')));

        $detail = $this->moderate('GET', "/message-reports/{$first}")->assertOk();
        $this->assertSame('Wants money', $detail->json('data.note'));
        $this->assertSame(['Hello, is the job still open?', 'Pay a fee first.'], array_column($detail->json('data.snapshot'), 'text'));
        $this->assertSame($this->seekerId, $detail->json('data.job_seeker.id'));
        $this->assertSame($this->companyId, $detail->json('data.company.id'));
        $this->assertNotSame('', $detail->json('data.company.name'));
        $this->assertSame('open', $detail->json('data.status'));

        $dismissed = $this->moderate('POST', "/message-reports/{$first}/dismiss", ['note' => 'Normal hiring talk'])->assertOk();
        $this->assertSame('dismissed', $dismissed->json('data.status'));
        $this->assertSame('Normal hiring talk', $dismissed->json('data.decision_note'));
        $this->assertNotNull($dismissed->json('data.decided_at'));

        $this->assertSame([$second], $this->moderate('GET', '/message-reports?filter[status]=open')->json('data.*.id'));
        $this->assertSame([$first], $this->moderate('GET', '/message-reports?filter[status]=dismissed')->json('data.*.id'));
        $this->assertSame([$second, $first], $this->moderate('GET', '/message-reports')->json('data.*.id'));
        $this->moderate('GET', '/message-reports?filter[status]=bogus')->assertUnprocessable();
    }

    public function test_actioning_a_report_needs_a_note_and_records_the_decision(): void
    {
        $id = $this->report()->assertCreated()->json('data.id');

        $this->moderate('POST', "/message-reports/{$id}/action")->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-decision');
        $this->moderate('POST', "/message-reports/{$id}/action", ['note' => str_repeat('a', 1001)])->assertStatus(422);

        $actioned = $this->moderate('POST', "/message-reports/{$id}/action", ['note' => 'Company suspended'])->assertOk();

        $this->assertSame('actioned', $actioned->json('data.status'));
        $this->moderate('POST', "/message-reports/{$id}/dismiss")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:report-decided');
        $this->moderate('POST', "/message-reports/{$id}/action", ['note' => 'Again'])->assertStatus(409);
    }

    public function test_a_decision_is_audited_without_the_text_and_the_reporter_is_told_without_the_outcome(): void
    {
        $this->say($this->conversation, 'Secret words', $this->recruiterToken)->assertCreated();
        $id = $this->report(['reason' => 'scam_or_fee'])->assertCreated()->json('data.id');
        $this->assertSame([], $this->reviewedNotices($this->seekerId));

        $this->moderate('POST', "/message-reports/{$id}/action", ['note' => 'Warned the company'])->assertOk();

        $this->assertSame([['type' => 'report.reviewed', 'data' => ['conversation_id' => $this->conversation]]], $this->reviewedNotices($this->seekerId));
        $this->assertSame([], $this->reviewedNotices($this->recruiterId));

        $entry = DB::table('platform_audit_logs')->where('action', 'messaging.report_actioned')->first();
        $this->assertSame($id, (int) $entry->subject_id);
        $this->assertStringContainsString('scam_or_fee', (string) $entry->metadata);
        $this->assertStringNotContainsString('Secret words', (string) $entry->metadata);
        $this->assertStringNotContainsString('Warned the company', (string) $entry->metadata);
        $this->assertSame(0, DB::table('platform_audit_logs')->where('action', 'messaging.report_dismissed')->count());
    }

    public function test_the_admin_endpoints_need_the_moderate_permission(): void
    {
        $id = $this->report()->assertCreated()->json('data.id');
        $other = AdminModel::query()->create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'hash', 'status' => 'active']);
        $token = $other->createToken('console', ['*'], now()->addHour())->plainTextToken;

        $this->moderate('GET', '/message-reports', token: $token)->assertForbidden();
        $this->moderate('GET', "/message-reports/{$id}", token: $token)->assertForbidden();
        $this->moderate('POST', "/message-reports/{$id}/dismiss", token: $token)->assertForbidden();
        $this->moderate('POST', "/message-reports/{$id}/action", ['note' => 'x'], $token)->assertForbidden();
        $this->moderate('GET', '/message-reports/999999')->assertNotFound();
    }

    public function test_snapshots_are_cleared_180_days_after_the_decision_and_the_report_stays(): void
    {
        $old = $this->report()->assertCreated()->json('data.id');
        $recent = $this->report([], 'company')->assertCreated()->json('data.id');
        $open = $this->report(['reason' => 'spam'], 'seeker', $this->secondConversation())->assertCreated()->json('data.id');
        $this->moderate('POST', "/message-reports/{$old}/dismiss")->assertOk();
        $this->moderate('POST', "/message-reports/{$recent}/dismiss")->assertOk();
        DB::table('messaging_reports')->where('id', $old)->update(['decided_at' => Carbon::now()->subDays(181)]);
        DB::table('messaging_reports')->where('id', $recent)->update(['decided_at' => Carbon::now()->subDays(179)]);

        $this->artisan('messaging:purge-report-snapshots')->expectsOutputToContain('Snapshots cleared: 1')->assertSuccessful();

        $this->assertNull($this->reportRow($old)->snapshot);
        $this->assertSame('dismissed', $this->reportRow($old)->status);
        $this->assertNotNull($this->reportRow($recent)->snapshot);
        $this->assertNotNull($this->reportRow($open)->snapshot);
        $this->assertNull($this->moderate('GET', "/message-reports/{$old}")->assertOk()->json('data.snapshot'));
    }

    public function test_erasing_the_reporter_clears_the_reporter_the_note_and_the_snapshot(): void
    {
        $id = $this->report(['note' => 'Private words'])->assertCreated()->json('data.id');
        $companyReport = $this->report([], 'company')->assertCreated()->json('data.id');

        $this->app->make(EraseMessagingData::class)->execute($this->seekerId);

        $row = $this->reportRow($id);
        $this->assertNull($row->reporter_user_id);
        $this->assertNull($row->note);
        $this->assertNull($row->snapshot);
        $this->assertSame('harassment', $row->reason);
        // The seeker is also the other party of the company's report: its snapshot holds their words too.
        $this->assertNull($this->reportRow($companyReport)->snapshot);
        $this->assertSame($this->recruiterId, (int) $this->reportRow($companyReport)->reporter_user_id);
    }

    public function test_reporting_is_limited_to_ten_an_hour(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $conversation = $this->secondConversation($i);
            $this->report([], 'seeker', $conversation)->assertCreated();
        }

        $this->report([], 'seeker', $this->secondConversation(11))->assertStatus(429);
    }

    /** Another conversation of the seeker's, with a company of its own (the seeker can then report several conversations). */
    private function secondConversation(int $n = 0): int
    {
        $companyId = $this->companyRowId("pt-extra-{$n}");

        return (int) DB::table('messaging_conversations')->insertGetId([
            'company_id' => $companyId,
            'job_seeker_user_id' => $this->seekerId,
            'started_by_user_id' => $this->seekerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
