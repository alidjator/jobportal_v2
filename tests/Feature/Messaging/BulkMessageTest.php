<?php

namespace Tests\Feature\Messaging;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\BuildsChatFixtures;
use Tests\FeatureTestCase;

final class BulkMessageTest extends FeatureTestCase
{
    use BuildsChatFixtures;

    private int $secondApplicationId;

    private int $secondSeekerId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildChatFixtures();
        $this->secondSeekerId = $this->seeker('budi.s@example.test', name: 'Budi Santoso');
        $token = $this->tokenFor($this->secondSeekerId);
        $resume = $this->completeProfile($token);
        $this->secondApplicationId = (int) $this->api('POST', "/jobs/{$this->job}/applications", ['resume_id' => $resume], $token)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        $this->tearDownChatFixtures();

        parent::tearDown();
    }

    /** @param  list<int>  $ids */
    private function bulk(array $ids, ?string $body = 'We have an update for you.', ?string $token = null, array $files = [], ?int $job = null): TestResponse
    {
        return $this->multipartWithFiles('POST', '/company/jobs/'.($job ?? $this->job).'/applications/bulk-message', ['application_ids' => $ids, ...($body === null ? [] : ['body' => $body])], $files, $token ?? $this->recruiterToken);
    }

    public function test_a_bulk_message_goes_to_each_applicant_in_their_own_conversation(): void
    {
        $response = $this->bulk([$this->applicationId, $this->secondApplicationId])->assertOk();

        $this->assertSame(['sent', 'sent'], array_column($response->json('data'), 'status'));
        $this->assertSame(2, DB::table('messaging_conversations')->count());
        $this->assertSame(2, DB::table('messaging_messages')->where('sender_side', 'company')->count());

        $mine = $this->api('GET', '/me/conversations', token: $this->seekerToken)->json('data');
        $this->assertCount(1, $mine);
        $this->assertSame('We have an update for you.', $mine[0]['last_message']['body']);
        $this->assertTrue($mine[0]['unread']);
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $this->secondSeekerId)->where('type', 'message.received')->count());
    }

    public function test_a_bulk_message_reuses_an_existing_conversation(): void
    {
        $id = $this->seekerStarts('Earlier');

        $this->bulk([$this->applicationId])->assertOk()->assertJsonPath('data.0.conversation_id', $id);

        $this->assertSame(1, DB::table('messaging_conversations')->count());
        $this->assertSame(2, DB::table('messaging_messages')->where('conversation_id', $id)->count());
    }

    public function test_each_application_is_reported_and_one_failure_does_not_stop_the_others(): void
    {
        $closed = $this->seekerStarts('Earlier');
        $this->api('POST', "/company/conversations/{$closed}/close", token: $this->recruiterToken)->assertOk();
        DB::table('identity_users')->where('id', $this->secondSeekerId)->update(['anonymized_at' => now()]);
        $otherJob = $this->insertJob('Other', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $foreign = (int) $this->apply($otherJob)->assertCreated()->json('data.id');

        $response = $this->bulk([$this->applicationId, $this->secondApplicationId, $foreign, 999999])->assertOk();

        $this->assertSame(
            [
                ['status' => 'skipped', 'code' => 'conversation-closed'],
                ['status' => 'skipped', 'code' => 'recipient-unavailable'],
                ['status' => 'skipped', 'code' => 'application-not-found'],
                ['status' => 'skipped', 'code' => 'application-not-found'],
            ],
            array_map(fn (array $item): array => ['status' => $item['status'], 'code' => $item['code']], $response->json('data')),
        );
    }

    public function test_two_applications_of_one_person_get_one_message(): void
    {
        $copy = (array) DB::table('recruitment_applications')->where('id', $this->applicationId)->first();
        unset($copy['id']);

        DB::table('recruitment_applications')->insert([...$copy, 'active_key' => null, 'stage_type' => 'withdrawn']);
        $second = (int) DB::table('recruitment_applications')->max('id');

        $response = $this->bulk([$this->applicationId, $second])->assertOk();

        $this->assertSame(['sent', 'skipped'], array_column($response->json('data'), 'status'));
        $this->assertSame('duplicate-recipient', $response->json('data.1.code'));
    }

    public function test_the_attachments_are_stored_once_and_shared_until_the_last_message_goes(): void
    {
        Storage::fake();

        $this->bulk([$this->applicationId, $this->secondApplicationId], 'See the file', files: [$this->upload('info.pdf', $this->pdf())])->assertOk();

        $this->assertSame(1, DB::table('messaging_attachments')->count());
        $this->assertSame(2, DB::table('messaging_message_attachments')->count());
        $path = DB::table('messaging_attachments')->value('path');
        $messages = DB::table('messaging_messages')->orderBy('id')->pluck('id')->all();

        $this->api('DELETE', "/company/messages/{$messages[0]}", token: $this->recruiterToken)->assertNoContent();
        Storage::assertExists($path);
        $this->assertSame(1, DB::table('messaging_attachments')->count());

        $this->api('DELETE', "/company/messages/{$messages[1]}", token: $this->recruiterToken)->assertNoContent();
        Storage::assertMissing($path);
        $this->assertSame(0, DB::table('messaging_attachments')->count());
    }

    public function test_a_refused_bulk_message_leaves_no_files_behind(): void
    {
        Storage::fake();
        $this->app->make(SettingsStore::class)->setGlobal('messaging.max_bulk_recipients', 1, null);

        $this->bulk([$this->applicationId, $this->secondApplicationId], files: [$this->upload('info.pdf', $this->pdf())])
            ->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:too-many-recipients');

        $this->assertSame(0, DB::table('messaging_attachments')->count());
        $this->assertSame([], Storage::allFiles('messaging'));
        $this->assertSame(0, DB::table('messaging_messages')->count());
    }

    public function test_the_daily_cap_counts_every_recipient_and_the_rest_are_skipped(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal('messaging.max_daily_company_messages', 1, null);

        $response = $this->bulk([$this->applicationId, $this->secondApplicationId])->assertOk();

        $this->assertSame(['sent', 'skipped'], array_column($response->json('data'), 'status'));
        $this->assertSame('daily-limit', $response->json('data.1.code'));
        $this->bulk([$this->applicationId])->assertStatus(429)->assertJsonPath('type', 'urn:jobportal:error:daily-limit');
    }

    public function test_who_may_send_a_bulk_message(): void
    {
        $this->bulk([$this->applicationId], token: $this->secondRecruiterToken)->assertNotFound();
        $this->bulk([$this->applicationId], token: $this->otherAdminToken)->assertNotFound();
        $this->bulk([$this->applicationId], token: $this->seekerToken)->assertForbidden();
        $this->bulk([$this->applicationId], token: $this->adminToken)->assertOk();
        $this->bulk([], token: $this->adminToken)->assertStatus(422);
        $this->bulk([$this->applicationId], null, $this->adminToken)->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:empty-message');
    }
}
