<?php

namespace Tests\Feature\Messaging;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsChatFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-platform-admin.md` section 5: the job seeker blocks a conversation; the company cannot send until it is unblocked. */
final class ChatBlockTest extends FeatureTestCase
{
    use BuildsChatFixtures;

    private int $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildChatFixtures();
        $this->conversation = $this->seekerStarts('Hello, is the job still open?');
    }

    protected function tearDown(): void
    {
        $this->tearDownChatFixtures();

        parent::tearDown();
    }

    private function block(?string $token = null): TestResponse
    {
        return $this->api('POST', "/me/conversations/{$this->conversation}/block", token: $token ?? $this->seekerToken);
    }

    private function unblock(?string $token = null): TestResponse
    {
        return $this->api('DELETE', "/me/conversations/{$this->conversation}/block", token: $token ?? $this->seekerToken);
    }

    public function test_the_job_seeker_blocks_and_unblocks_a_conversation(): void
    {
        $blocked = $this->block()->assertOk();

        $this->assertTrue($blocked->json('data.blocked'));
        $this->assertNotNull($blocked->json('data.blocked_at'));
        $this->assertNotNull(DB::table('messaging_conversations')->where('id', $this->conversation)->value('blocked_by_seeker_at'));

        $unblocked = $this->unblock()->assertOk();

        $this->assertFalse($unblocked->json('data.blocked'));
        $this->assertNull($unblocked->json('data.blocked_at'));
        $this->assertNull(DB::table('messaging_conversations')->where('id', $this->conversation)->value('blocked_by_seeker_at'));
    }

    public function test_blocking_twice_keeps_the_first_time_and_unblocking_an_unblocked_one_changes_nothing(): void
    {
        $this->unblock()->assertOk()->assertJsonPath('data.blocked', false);

        $this->block()->assertOk();
        $first = DB::table('messaging_conversations')->where('id', $this->conversation)->value('blocked_by_seeker_at');
        $this->block()->assertOk();

        $this->assertSame($first, DB::table('messaging_conversations')->where('id', $this->conversation)->value('blocked_by_seeker_at'));
    }

    public function test_the_company_cannot_send_while_the_conversation_is_blocked(): void
    {
        $this->say($this->conversation, 'Before the block', $this->recruiterToken)->assertCreated();
        $this->block()->assertOk();

        $this->say($this->conversation, 'After the block', $this->recruiterToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:conversation-blocked');
        $this->assertSame(2, DB::table('messaging_messages')->where('conversation_id', $this->conversation)->count());

        $this->unblock()->assertOk();
        $this->say($this->conversation, 'After the unblock', $this->recruiterToken)->assertCreated();
    }

    public function test_a_company_cannot_open_a_new_message_through_the_blocked_conversation(): void
    {
        $this->block()->assertOk();

        $this->companyStarts($this->seekerId, 'Can we talk?')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:conversation-blocked');
        $this->assertSame(1, DB::table('messaging_messages')->where('conversation_id', $this->conversation)->count());
    }

    public function test_a_bulk_message_skips_a_blocked_conversation(): void
    {
        $this->block()->assertOk();

        $response = $this->multipartWithFiles('POST', "/company/jobs/{$this->job}/applications/bulk-message", ['application_ids' => [$this->applicationId], 'body' => 'An update'], [], $this->recruiterToken)->assertOk();

        $this->assertSame([['status' => 'skipped', 'code' => 'conversation-blocked']], array_map(fn (array $row): array => ['status' => $row['status'], 'code' => $row['code']], $response->json('data')));
    }

    public function test_both_sides_see_the_block_and_the_job_seeker_still_reads(): void
    {
        $this->say($this->conversation, 'Welcome', $this->recruiterToken)->assertCreated();
        $this->block()->assertOk();

        $this->assertTrue($this->api('GET', "/company/conversations/{$this->conversation}", token: $this->recruiterToken)->assertOk()->json('data.blocked'));
        $this->assertTrue($this->api('GET', "/me/conversations/{$this->conversation}", token: $this->seekerToken)->assertOk()->json('data.blocked'));
        $this->assertTrue($this->api('GET', '/me/conversations', token: $this->seekerToken)->assertOk()->json('data.0.blocked'));
        $this->assertCount(2, $this->api('GET', "/me/conversations/{$this->conversation}/messages", token: $this->seekerToken)->assertOk()->json('data'));
    }

    public function test_only_the_job_seeker_blocks_and_only_in_a_conversation_of_theirs(): void
    {
        $this->api('POST', "/me/conversations/{$this->conversation}/block", token: $this->recruiterToken)->assertForbidden();
        $this->block($this->tokenFor($this->seeker('stranger@example.test')))->assertNotFound();
        $this->unblock($this->tokenFor($this->seeker('other@example.test')))->assertNotFound();

        $this->assertNull(DB::table('messaging_conversations')->where('id', $this->conversation)->value('blocked_by_seeker_at'));
    }
}
