<?php

namespace Tests\Feature\Newsletter;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Newsletter\Application\DeliverNewsletter;
use Src\Newsletter\Application\DispatchIssue;
use Src\Newsletter\Application\IssueMessage;
use Src\Newsletter\Application\NewsletterMailer;
use Src\Newsletter\Application\PurgePendingSubscribers;
use Src\Newsletter\Application\UnsubscribeTokens;
use Src\Newsletter\Infrastructure\Jobs\SendNewsletterDeliveryJob;
use Symfony\Component\Mime\Email;
use Tests\FeatureTestCase;

final class NewsletterTest extends FeatureTestCase
{
    private const string CONFIRM_URL = 'https://app.example.test/newsletter/confirm?token={token}';

    private const string UNSUBSCRIBE_URL = 'https://app.example.test/newsletter/unsubscribe?token={token}';

    private string $adminToken;

    private string $weakToken;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-10 09:00:00');
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        config(['app.newsletter_confirm_url' => self::CONFIRM_URL, 'app.newsletter_unsubscribe_url' => self::UNSUBSCRIBE_URL]);

        $this->adminToken = $this->adminToken(['newsletter.manage'], 'ops@example.test');
        $this->weakToken = $this->adminToken([], 'weak@example.test');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param  list<string>  $permissions */
    private function adminToken(array $permissions, string $email): string
    {
        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => $email, 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo($permissions);

        return $admin->createToken('console', ['*'], now()->addDays(30))->plainTextToken;
    }

    private function admin(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->adminToken)->json($method, '/api/v1/admin/newsletter'.$uri, $body);
    }

    private function visitor(string $uri, array $body = [], array $headers = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->postJson('/api/v1/newsletter'.$uri, $body, $headers);
    }

    /** @return list<Email> */
    private function sent(): array
    {
        return array_map(
            static fn ($message): Email => $message->getOriginalMessage(),
            $this->app->make('mail.manager')->mailer()->getSymfonyTransport()->messages()->all(),
        );
    }

    private function lastMail(): Email
    {
        $sent = $this->sent();

        return $sent[count($sent) - 1];
    }

    private function tokenIn(Email $mail, string $pattern = '/confirm\?token=([A-Za-z0-9_-]{43})/'): string
    {
        $this->assertSame(1, preg_match($pattern, (string) $mail->getTextBody().' '.(string) $mail->getHtmlBody(), $match), 'No token found in the mail.');

        return $match[1];
    }

    /** Subscribes an address and confirms it through the email, like a visitor would. */
    private function confirmedSubscriber(string $email, string $locale = 'id'): int
    {
        $this->visitor('/subscribers', ['email' => $email], ['Accept-Language' => $locale])->assertStatus(202);
        $this->visitor('/confirm', ['token' => $this->tokenIn($this->lastMail())])->assertNoContent();

        return (int) DB::table('newsletter_subscribers')->where('email', $email)->value('id');
    }

    private function issue(string $subject = 'October news', string $body = "Hello **friends**\n\n<script>alert(1)</script>"): int
    {
        return (int) $this->admin('POST', '/issues', ['subject' => $subject, 'body' => $body])->assertCreated()->json('data.id');
    }

    // ----- subscribing -----

    public function test_a_visitor_asks_to_join_and_gets_a_confirmation_email_without_learning_anything(): void
    {
        $this->visitor('/subscribers', ['email' => 'Budi@Example.Test'], ['Accept-Language' => 'en'])->assertStatus(202)->assertNoContent(202);

        $row = DB::table('newsletter_subscribers')->first();
        $this->assertSame('budi@example.test', $row->email);
        $this->assertSame('pending', $row->status);
        $this->assertSame('en', $row->locale);

        $mail = $this->lastMail();
        $this->assertSame('budi@example.test', $mail->getTo()[0]->getAddress());
        $this->assertSame('Confirm your newsletter subscription', $mail->getSubject());

        $token = $this->tokenIn($mail);
        $this->assertSame(hash('sha256', $token), $row->confirm_token_hash);
        $this->assertStringNotContainsString($token, json_encode((array) $row, JSON_THROW_ON_ERROR));
        $this->assertStringContainsString('https://app.example.test/newsletter/confirm?token='.$token, (string) $mail->getHtmlBody());
    }

    public function test_the_confirmation_link_confirms_once_and_only_in_time(): void
    {
        $this->visitor('/subscribers', ['email' => 'budi@example.test'])->assertStatus(202);
        $token = $this->tokenIn($this->lastMail());

        $this->visitor('/confirm', ['token' => 'wrong'])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:newsletter-token-invalid');
        $this->visitor('/confirm', ['token' => $token])->assertNoContent();

        $row = DB::table('newsletter_subscribers')->first();
        $this->assertSame('confirmed', $row->status);
        $this->assertNull($row->confirm_token_hash);
        $this->assertNotNull($row->confirmed_at);

        $this->visitor('/confirm', ['token' => $token])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:newsletter-token-invalid');
    }

    public function test_a_link_older_than_three_days_is_refused(): void
    {
        $this->visitor('/subscribers', ['email' => 'budi@example.test'])->assertStatus(202);
        $token = $this->tokenIn($this->lastMail());

        Carbon::setTestNow('2026-10-13 09:00:01');

        $this->visitor('/confirm', ['token' => $token])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:newsletter-token-invalid');
        $this->assertSame('pending', DB::table('newsletter_subscribers')->value('status'));
    }

    public function test_every_address_gets_the_same_answer_and_only_the_right_ones_get_an_email(): void
    {
        $this->confirmedSubscriber('confirmed@example.test');
        $this->visitor('/subscribers', ['email' => 'pending@example.test'])->assertStatus(202);
        $before = count($this->sent());

        $this->visitor('/subscribers', ['email' => 'confirmed@example.test'])->assertStatus(202)->assertNoContent(202);
        $this->visitor('/subscribers', ['email' => 'PENDING@example.test'])->assertStatus(202)->assertNoContent(202);
        $this->assertCount($before, $this->sent(), 'a confirmed address and a pending one asked again at once get nothing');

        Carbon::setTestNow('2026-10-10 09:10:00');
        $oldToken = DB::table('newsletter_subscribers')->where('email', 'pending@example.test')->value('confirm_token_hash');
        $this->visitor('/subscribers', ['email' => 'pending@example.test'])->assertStatus(202);

        $this->assertCount($before + 1, $this->sent());
        $this->assertNotSame($oldToken, DB::table('newsletter_subscribers')->where('email', 'pending@example.test')->value('confirm_token_hash'));
        $this->assertSame(2, DB::table('newsletter_subscribers')->count());
    }

    public function test_someone_who_left_must_confirm_again(): void
    {
        $id = $this->confirmedSubscriber('budi@example.test');
        $this->visitor('/unsubscribe', ['token' => app(UnsubscribeTokens::class)->forSubscriber($id)])->assertNoContent();
        $this->assertSame('unsubscribed', DB::table('newsletter_subscribers')->value('status'));

        $this->visitor('/subscribers', ['email' => 'budi@example.test'])->assertStatus(202);

        $row = DB::table('newsletter_subscribers')->first();
        $this->assertSame('pending', $row->status);
        $this->assertNull($row->unsubscribed_at);
        $this->visitor('/confirm', ['token' => $this->tokenIn($this->lastMail())])->assertNoContent();
        $this->assertSame('confirmed', DB::table('newsletter_subscribers')->value('status'));
    }

    public function test_a_bad_address_and_a_switched_off_newsletter_are_refused(): void
    {
        $this->visitor('/subscribers', ['email' => 'not-an-email'])->assertStatus(422);
        $this->visitor('/subscribers', [])->assertStatus(422);

        config(['app.newsletter_confirm_url' => null]);
        $this->visitor('/subscribers', ['email' => 'budi@example.test'])->assertStatus(503)->assertJsonPath('type', 'urn:jobportal:error:newsletter-unavailable');
        $this->assertSame(0, DB::table('newsletter_subscribers')->count());

        config(['app.newsletter_confirm_url' => self::CONFIRM_URL, 'app.newsletter_unsubscribe_url' => 'https://no-placeholder.example.test']);
        $this->visitor('/subscribers', ['email' => 'budi@example.test'])->assertStatus(503);
    }

    public function test_subscribing_is_limited_per_address(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->visitor('/subscribers', ['email' => 'same@example.test'])->assertStatus(202);
        }

        $this->visitor('/subscribers', ['email' => 'same@example.test'])->assertStatus(429);
    }

    public function test_subscribing_is_limited_per_client(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->visitor('/subscribers', ['email' => "other{$i}@example.test"])->assertStatus(202);
        }

        $this->visitor('/subscribers', ['email' => 'other9@example.test'])->assertStatus(429);
    }

    // ----- unsubscribing -----

    public function test_unsubscribing_works_from_the_page_and_in_one_click_and_can_be_repeated(): void
    {
        $first = $this->confirmedSubscriber('one@example.test');
        $second = $this->confirmedSubscriber('two@example.test');
        $tokens = app(UnsubscribeTokens::class);

        $this->visitor('/unsubscribe', ['token' => $tokens->forSubscriber($first)])->assertNoContent();
        $this->visitor('/unsubscribe', ['token' => $tokens->forSubscriber($first)])->assertNoContent();
        $this->visitor('/unsubscribe/'.$tokens->forSubscriber($second))->assertNoContent();

        $this->assertSame(['unsubscribed', 'unsubscribed'], DB::table('newsletter_subscribers')->orderBy('id')->pluck('status')->all());
        $this->assertNotNull(DB::table('newsletter_subscribers')->where('id', $first)->value('unsubscribed_at'));
    }

    public function test_a_token_that_is_not_genuine_is_refused_and_one_of_a_deleted_subscriber_does_nothing(): void
    {
        $id = $this->confirmedSubscriber('one@example.test');
        $token = app(UnsubscribeTokens::class)->forSubscriber($id);
        [$number, $signature] = explode('.', $token);

        $this->visitor('/unsubscribe', ['token' => ($number + 1).'.'.$signature])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:newsletter-token-invalid');
        $this->visitor('/unsubscribe', ['token' => $number.'.'.str_repeat('0', 64)])->assertStatus(422);
        $this->visitor('/unsubscribe', ['token' => 'garbage'])->assertStatus(422);
        $this->visitor('/unsubscribe/'.$number.'.'.str_repeat('a', 64))->assertStatus(422);
        $this->assertSame('confirmed', DB::table('newsletter_subscribers')->value('status'));

        DB::table('newsletter_subscribers')->delete();
        $this->visitor('/unsubscribe', ['token' => $token])->assertNoContent();
    }

    // ----- the admin: access and the list -----

    public function test_the_admin_routes_need_the_permission(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->getJson('/api/v1/admin/newsletter/subscribers')->assertStatus(401);
        $this->getJson('/api/v1/admin/newsletter/issues')->assertStatus(401);

        foreach ([['GET', '/subscribers'], ['DELETE', '/subscribers/1'], ['GET', '/issues'], ['POST', '/issues'], ['GET', '/issues/1'], ['PUT', '/issues/1'], ['DELETE', '/issues/1'], ['POST', '/issues/1/test'], ['POST', '/issues/1/send']] as [$method, $uri]) {
            $this->admin($method, $uri, [], $this->weakToken)->assertStatus(403);
        }
    }

    public function test_the_list_filters_and_counts_every_status(): void
    {
        $this->confirmedSubscriber('anna@example.test');
        $this->confirmedSubscriber('budi@example.test');
        $this->visitor('/subscribers', ['email' => 'cakra@example.test'])->assertStatus(202);
        $left = $this->confirmedSubscriber('dewi@example.test');
        $this->visitor('/unsubscribe/'.app(UnsubscribeTokens::class)->forSubscriber($left))->assertNoContent();

        $all = $this->admin('GET', '/subscribers')->assertOk();
        $all->assertJsonCount(4, 'data')->assertJsonPath('counts', ['pending' => 1, 'confirmed' => 2, 'unsubscribed' => 1])->assertJsonPath('data.0.email', 'dewi@example.test');
        $this->assertArrayNotHasKey('confirm_token_hash', $all->json('data.0'));

        $this->admin('GET', '/subscribers?filter[status]=confirmed')->assertJsonCount(2, 'data')->assertJsonPath('counts.confirmed', 2);
        $this->admin('GET', '/subscribers?filter[q]=CAKRA')->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'pending');
        $this->admin('GET', '/subscribers?filter[status]=gone')->assertStatus(422);
        $this->admin('GET', '/subscribers?filter[plan]=x')->assertStatus(422);
        $this->admin('GET', '/subscribers?per_page=2&page=2')->assertJsonCount(2, 'data');
    }

    public function test_an_admin_erases_a_subscriber_and_it_is_audited_without_the_address(): void
    {
        $id = $this->confirmedSubscriber('anna@example.test');

        $this->admin('DELETE', "/subscribers/{$id}")->assertNoContent();
        $this->admin('DELETE', "/subscribers/{$id}")->assertStatus(404);

        $this->assertSame(0, DB::table('newsletter_subscribers')->count());
        $entry = DB::table('platform_audit_logs')->where('action', 'newsletter.subscriber_deleted')->first();
        $this->assertSame('admin', $entry->actor_type);
        $this->assertSame($id, (int) $entry->subject_id);
        $this->assertStringNotContainsString('anna@', json_encode((array) $entry, JSON_THROW_ON_ERROR));
    }

    // ----- issues -----

    public function test_an_issue_is_a_draft_that_can_be_changed_and_deleted(): void
    {
        $id = $this->issue();
        $created = $this->admin('GET', "/issues/{$id}")->assertOk();
        $created->assertJsonPath('data.status', 'draft')->assertJsonPath('data.recipients', 0)->assertJsonPath('data.delivered', 0)->assertJsonPath('data.sent_by_admin_id', null);

        $this->admin('PUT', "/issues/{$id}", ['subject' => '  Changed  ', 'body' => 'New body'])->assertOk()->assertJsonPath('data.subject', 'Changed')->assertJsonPath('data.body', 'New body');
        $this->admin('POST', '/issues', ['subject' => '', 'body' => 'x'])->assertStatus(422);
        $this->admin('POST', '/issues', ['subject' => str_repeat('a', 151), 'body' => 'x'])->assertStatus(422);
        $this->admin('POST', '/issues', ['subject' => 'x', 'body' => str_repeat('a', 20001)])->assertStatus(422);
        $this->admin('GET', '/issues?filter[status]=draft')->assertJsonCount(1, 'data');
        $this->admin('GET', '/issues?filter[status]=sent')->assertJsonCount(0, 'data');
        $this->admin('GET', '/issues/999')->assertStatus(404);

        $this->admin('DELETE', "/issues/{$id}")->assertNoContent();
        $this->admin('GET', "/issues/{$id}")->assertStatus(404);
    }

    public function test_sending_reaches_only_the_confirmed_subscribers_in_their_language_with_their_own_unsubscribe_link(): void
    {
        $this->confirmedSubscriber('anna@example.test', 'id');
        $this->confirmedSubscriber('budi@example.test', 'en');
        $this->visitor('/subscribers', ['email' => 'pending@example.test'])->assertStatus(202);
        $left = $this->confirmedSubscriber('left@example.test');
        $this->visitor('/unsubscribe/'.app(UnsubscribeTokens::class)->forSubscriber($left))->assertNoContent();
        $before = count($this->sent());
        $id = $this->issue();

        $this->admin('POST', "/issues/{$id}/send")->assertStatus(202)->assertJsonPath('data.status', 'sent')->assertJsonPath('data.recipients', 2)->assertJsonPath('data.delivered', 2)
            ->assertJsonPath('data.failed', 0)->assertJsonPath('data.sent_by_admin_id', (int) DB::table('identity_admins')->where('email', 'ops@example.test')->value('id'));

        $mails = array_slice($this->sent(), $before);
        $this->assertCount(2, $mails);
        $byAddress = [];

        foreach ($mails as $mail) {
            $byAddress[$mail->getTo()[0]->getAddress()] = $mail;
            $this->assertSame('October news', $mail->getSubject());
            $this->assertMatchesRegularExpression('/<strong[^>]*>friends<\/strong>/', (string) $mail->getHtmlBody());
            $this->assertStringNotContainsString('<script>', (string) $mail->getHtmlBody());
            $this->assertSame('List-Unsubscribe=One-Click', $mail->getHeaders()->get('List-Unsubscribe-Post')->getBodyAsString());
        }

        $this->assertSame(['anna@example.test', 'budi@example.test'], array_keys($byAddress));
        $this->assertStringContainsString('Berhenti berlangganan', (string) $byAddress['anna@example.test']->getHtmlBody());
        $this->assertStringContainsString('Unsubscribe', (string) $byAddress['budi@example.test']->getHtmlBody());

        $anna = $this->tokenIn($byAddress['anna@example.test'], '/unsubscribe\?token=([0-9]+\.[0-9a-f]{64})/');
        $budi = $this->tokenIn($byAddress['budi@example.test'], '/unsubscribe\?token=([0-9]+\.[0-9a-f]{64})/');
        $this->assertNotSame($anna, $budi);
        $this->assertSame('<http://localhost/api/v1/newsletter/unsubscribe/'.$anna.'>', $byAddress['anna@example.test']->getHeaders()->get('List-Unsubscribe')->getBodyAsString());

        $this->visitor('/unsubscribe/'.$anna)->assertNoContent();
        $this->assertSame('unsubscribed', DB::table('newsletter_subscribers')->where('email', 'anna@example.test')->value('status'));
        $this->assertSame('confirmed', DB::table('newsletter_subscribers')->where('email', 'budi@example.test')->value('status'));

        $audit = DB::table('platform_audit_logs')->where('action', 'newsletter.issue_sent')->first();
        $this->assertSame($id, (int) $audit->subject_id);
        $this->assertSame(2, json_decode($audit->metadata, true)['recipients']);
    }

    public function test_an_issue_is_sent_once(): void
    {
        $this->confirmedSubscriber('anna@example.test');
        $id = $this->issue();
        $this->admin('POST', "/issues/{$id}/send")->assertStatus(202);
        $before = count($this->sent());

        $this->admin('POST', "/issues/{$id}/send")->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:newsletter-issue-not-draft');
        $this->admin('PUT', "/issues/{$id}", ['subject' => 'x', 'body' => 'y'])->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:newsletter-issue-not-draft');
        $this->admin('DELETE', "/issues/{$id}")->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:newsletter-issue-not-draft');

        $this->app->make(DispatchIssue::class)->execute($id);
        $this->assertCount($before, $this->sent(), 'a repeated dispatch sends nothing again');
        $this->assertSame(1, DB::table('newsletter_deliveries')->where('issue_id', $id)->count());
        $this->admin('GET', '/issues?filter[status]=sent')->assertJsonCount(1, 'data');
    }

    public function test_an_issue_with_nobody_to_send_to_stays_a_draft(): void
    {
        $this->visitor('/subscribers', ['email' => 'pending@example.test'])->assertStatus(202);
        $id = $this->issue();

        $this->admin('POST', "/issues/{$id}/send")->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:newsletter-no-recipients');
        $this->assertSame('draft', DB::table('newsletter_issues')->value('status'));
        $this->assertSame(0, DB::table('platform_audit_logs')->where('action', 'newsletter.issue_sent')->count());
    }

    public function test_sending_needs_the_newsletter_to_be_switched_on(): void
    {
        $this->confirmedSubscriber('anna@example.test');
        $id = $this->issue();
        config(['app.newsletter_unsubscribe_url' => null]);

        $this->admin('POST', "/issues/{$id}/send")->assertStatus(503)->assertJsonPath('type', 'urn:jobportal:error:newsletter-unavailable');
        $this->assertSame('draft', DB::table('newsletter_issues')->value('status'));
    }

    public function test_a_test_send_goes_only_to_the_admin_and_changes_nothing(): void
    {
        $this->confirmedSubscriber('anna@example.test');
        $id = $this->issue();
        $before = count($this->sent());

        $this->admin('POST', "/issues/{$id}/test")->assertStatus(202);

        $mails = array_slice($this->sent(), $before);
        $this->assertCount(1, $mails);
        $this->assertSame('ops@example.test', $mails[0]->getTo()[0]->getAddress());
        $this->assertSame('[Test] October news', $mails[0]->getSubject());
        $this->assertSame('draft', DB::table('newsletter_issues')->value('status'));
        $this->assertSame(0, DB::table('newsletter_deliveries')->count());
        $this->admin('POST', '/issues/999/test')->assertStatus(404);
    }

    // ----- failures -----

    public function test_a_delivery_that_could_not_be_made_is_counted_and_finishes_the_issue(): void
    {
        $this->confirmedSubscriber('anna@example.test');
        $this->confirmedSubscriber('budi@example.test');
        $id = $this->issue();

        Queue::fake([SendNewsletterDeliveryJob::class]);
        $this->admin('POST', "/issues/{$id}/send")->assertStatus(202);

        $deliveries = DB::table('newsletter_deliveries')->where('issue_id', $id)->orderBy('id')->pluck('id')->all();
        $this->assertCount(2, $deliveries);
        Queue::assertPushed(SendNewsletterDeliveryJob::class, 2);
        $this->assertSame('sending', DB::table('newsletter_issues')->value('status'));

        $this->app->make(DeliverNewsletter::class)->execute($deliveries[0]);
        $this->assertSame('sending', DB::table('newsletter_issues')->value('status'), 'one delivery is still queued');

        $this->app->make(DeliverNewsletter::class)->failed($deliveries[1]);
        $this->app->make(DeliverNewsletter::class)->failed($deliveries[1]);

        $issue = DB::table('newsletter_issues')->first();
        $this->assertSame('sent', $issue->status);
        $this->assertSame(1, (int) $issue->delivered);
        $this->assertSame(1, (int) $issue->failed);
        $this->assertNotNull($issue->finished_at);
    }

    public function test_a_mail_that_cannot_be_sent_is_left_for_the_queue_to_retry(): void
    {
        $this->confirmedSubscriber('anna@example.test');
        $id = $this->issue();
        Queue::fake([SendNewsletterDeliveryJob::class]);
        $this->admin('POST', "/issues/{$id}/send")->assertStatus(202);
        $deliveryId = (int) DB::table('newsletter_deliveries')->value('id');

        $this->app->instance(NewsletterMailer::class, new class implements NewsletterMailer
        {
            public function send(IssueMessage $message): void
            {
                throw new \RuntimeException('SMTP is down');
            }
        });

        try {
            $this->app->make(DeliverNewsletter::class)->execute($deliveryId);
            $this->fail('The failure must reach the queue job.');
        } catch (\RuntimeException) {
            // The job retries; the delivery is still queued.
        }

        $this->assertSame('queued', DB::table('newsletter_deliveries')->value('status'));
        $this->assertSame('sending', DB::table('newsletter_issues')->value('status'));
    }

    public function test_someone_who_left_after_the_dispatch_gets_nothing(): void
    {
        $id = $this->confirmedSubscriber('anna@example.test');
        $issue = $this->issue();
        Queue::fake([SendNewsletterDeliveryJob::class]);
        $this->admin('POST', "/issues/{$issue}/send")->assertStatus(202);
        $before = count($this->sent());

        $this->visitor('/unsubscribe/'.app(UnsubscribeTokens::class)->forSubscriber($id))->assertNoContent();
        $this->app->make(DeliverNewsletter::class)->execute((int) DB::table('newsletter_deliveries')->value('id'));

        $this->assertCount($before, $this->sent());
        $row = DB::table('newsletter_issues')->first();
        $this->assertSame('sent', $row->status);
        $this->assertSame(1, (int) $row->failed);
        $this->assertSame(0, (int) $row->delivered);
    }

    // ----- cleanup -----

    public function test_addresses_that_never_confirmed_are_purged_after_the_grace_period(): void
    {
        $this->confirmedSubscriber('kept@example.test');
        $this->visitor('/subscribers', ['email' => 'old@example.test'])->assertStatus(202);

        Carbon::setTestNow('2026-10-13 09:00:00');
        $this->visitor('/subscribers', ['email' => 'recent@example.test'])->assertStatus(202);

        Carbon::setTestNow('2026-10-21 09:00:00');
        $this->assertSame(1, $this->app->make(PurgePendingSubscribers::class)->execute());
        $this->assertSame(['kept@example.test', 'recent@example.test'], DB::table('newsletter_subscribers')->orderBy('id')->pluck('email')->all());

        $this->artisan('newsletter:purge-pending')->assertSuccessful();
    }
}
