<?php

namespace Tests\Feature\Platform;

use DateTimeImmutable;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Company\Infrastructure\Mail\MemberNoticeMail;
use Src\Identity\Application\Auth\VerificationCodeSender;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Platform\Application\EmailSuppression\EmailEvent;
use Src\Platform\Application\EmailSuppression\EmailEventParser;
use Src\Platform\Application\EmailSuppression\EmailEventParsers;
use Src\Platform\Application\EmailSuppression\EmailSuppressionReason;
use Src\Platform\Application\EmailSuppression\InvalidWebhookSignature;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-platform-admin.md` section 6: suppression list, webhook intake and the send-time filter. */
final class EmailSuppressionTest extends FeatureTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => 'ops@example.test', 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo('settings.manage');
        $this->token = $admin->createToken('console', ['*'], now()->addHour())->plainTextToken;
    }

    /** @param  array<string, mixed>  $body */
    private function admin(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->json($method, '/api/v1/admin'.$uri, $body);
    }

    private function suppress(string $email, EmailSuppressionReason $reason = EmailSuppressionReason::HardBounce): void
    {
        DB::table('platform_email_suppressions')->insert(['email' => $email, 'reason' => $reason->value, 'source' => 'test', 'created_at' => now()]);
    }

    /** @return list<object> */
    private function sent(): array
    {
        return $this->app->make('mail.manager')->mailer()->getSymfonyTransport()->messages()->all();
    }

    private function registerParser(): void
    {
        $this->app->make(EmailEventParsers::class)->register(new class implements EmailEventParser
        {
            public function provider(): string
            {
                return 'testmail';
            }

            public function parse(string $body, array $headers): array
            {
                if (($headers['x-signature'] ?? '') !== 'good') {
                    throw new InvalidWebhookSignature;
                }

                $events = [];

                foreach ((array) json_decode($body, true) as $row) {
                    if ($row['type'] === 'hard_bounce' || $row['type'] === 'complaint') {
                        $events[] = new EmailEvent($row['email'], EmailSuppressionReason::from($row['type']), $row['detail'] ?? null);
                    }
                }

                return $events;
            }
        });
    }

    public function test_an_admin_suppresses_lists_and_lifts_an_address(): void
    {
        $created = $this->admin('POST', '/email-suppressions', ['email' => ' Siti@Example.TEST ', 'details' => 'Asked by phone'])->assertCreated();

        $this->assertSame('siti@example.test', $created->json('data.email'));
        $this->assertSame('manual', $created->json('data.reason'));
        $this->assertSame('admin', $created->json('data.source'));
        $id = $created->json('data.id');

        $this->admin('POST', '/email-suppressions', ['email' => 'siti@example.test'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:already-suppressed');
        $this->suppress('budi@example.test');

        $this->assertSame(['budi@example.test', 'siti@example.test'], $this->admin('GET', '/email-suppressions?q=EXAMPLE')->assertOk()->json('data.*.email'));
        $this->assertSame(['siti@example.test'], $this->admin('GET', '/email-suppressions?q=siti')->assertOk()->json('data.*.email'));

        $this->admin('DELETE', "/email-suppressions/{$id}")->assertNoContent();
        $this->admin('DELETE', "/email-suppressions/{$id}")->assertNotFound();
        $this->assertSame(0, DB::table('platform_email_suppressions')->where('email', 'siti@example.test')->count());

        $actions = DB::table('platform_audit_logs')->whereIn('action', ['platform.email_suppression_added', 'platform.email_suppression_removed'])->orderBy('id')->pluck('action')->all();
        $this->assertSame(['platform.email_suppression_added', 'platform.email_suppression_removed'], $actions);
        $this->assertStringNotContainsString('siti@example.test', (string) DB::table('platform_audit_logs')->where('action', 'platform.email_suppression_added')->value('metadata'));
    }

    public function test_the_list_needs_the_settings_permission_and_a_valid_address(): void
    {
        $other = AdminModel::query()->create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'hash', 'status' => 'active']);
        $token = $other->createToken('console', ['*'], now()->addHour())->plainTextToken;

        $this->admin('GET', '/email-suppressions', token: $token)->assertForbidden();
        $this->admin('POST', '/email-suppressions', ['email' => 'not-an-address'])->assertUnprocessable();
    }

    public function test_the_webhook_answers_404_until_a_provider_is_registered(): void
    {
        $this->flushHeaders()->postJson('/api/v1/webhooks/email/testmail', [])->assertNotFound();
    }

    public function test_the_webhook_checks_the_signature_and_stores_hard_bounces_and_complaints_only(): void
    {
        $this->registerParser();
        $this->suppress('old@example.test', EmailSuppressionReason::Complaint);
        $events = [
            ['type' => 'hard_bounce', 'email' => 'Gone@Example.test', 'detail' => 'mailbox does not exist'],
            ['type' => 'soft_bounce', 'email' => 'full@example.test'],
            ['type' => 'complaint', 'email' => 'angry@example.test'],
            ['type' => 'hard_bounce', 'email' => 'old@example.test'],
        ];

        $this->flushHeaders()->postJson('/api/v1/webhooks/email/testmail', $events, ['X-Signature' => 'bad'])->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:invalid-signature');
        $this->assertSame(1, DB::table('platform_email_suppressions')->count());

        $this->flushHeaders()->postJson('/api/v1/webhooks/email/testmail', $events, ['X-Signature' => 'good'])->assertNoContent();

        $this->assertSame(['angry@example.test', 'gone@example.test', 'old@example.test'], DB::table('platform_email_suppressions')->orderBy('email')->pluck('email')->all());
        $this->assertSame('complaint', DB::table('platform_email_suppressions')->where('email', 'old@example.test')->value('reason'));
        $this->assertSame('hard_bounce', DB::table('platform_email_suppressions')->where('email', 'gone@example.test')->value('reason'));
        $this->assertSame('testmail', DB::table('platform_email_suppressions')->where('email', 'gone@example.test')->value('source'));
        $this->assertSame('mailbox does not exist', DB::table('platform_email_suppressions')->where('email', 'gone@example.test')->value('details'));
    }

    public function test_a_non_essential_message_is_dropped_for_a_suppressed_recipient(): void
    {
        $this->suppress('gone@example.test');

        Mail::raw('Newsletter', fn ($message) => $message->to('gone@example.test')->subject('News'));
        $this->assertCount(0, $this->sent());

        Mail::raw('Newsletter', fn ($message) => $message->to('gone@example.test')->to('here@example.test')->subject('News'));
        $messages = $this->sent();

        $this->assertCount(1, $messages);
        $this->assertSame(['here@example.test'], array_map(fn ($address) => $address->getAddress(), $messages[0]->getOriginalMessage()->getTo()));
    }

    public function test_an_essential_message_is_sent_to_a_suppressed_recipient_without_the_marker_header(): void
    {
        $this->suppress('gone@example.test');

        $this->app->make(VerificationCodeSender::class)->sendPasswordReset('gone@example.test', 'Siti', 'en', '123456', new DateTimeImmutable('+15 minutes'));

        $messages = $this->sent();
        $this->assertCount(1, $messages);
        $this->assertFalse($messages[0]->getOriginalMessage()->getHeaders()->has('X-Essential'));
        $this->assertSame('gone@example.test', $messages[0]->getOriginalMessage()->getTo()[0]->getAddress());
    }

    public function test_only_the_suspension_notice_of_the_member_notices_is_essential(): void
    {
        $this->suppress('gone@example.test');
        $mailer = $this->app->make(Mailer::class);

        $mailer->to('gone@example.test')->send(new MemberNoticeMail('company.member_reactivated', 'en', ['company' => 'Maju']));
        $this->assertCount(0, $this->sent());

        $mailer->to('gone@example.test')->send(new MemberNoticeMail('company.member_suspended', 'en', ['company' => 'Maju', 'message' => '']));
        $this->assertCount(1, $this->sent());
    }

    public function test_the_address_is_matched_whatever_the_letter_case(): void
    {
        $this->suppress('gone@example.test');

        Mail::raw('Hello', fn ($message) => $message->to('GONE@Example.Test')->subject('Hi'));

        $this->assertCount(0, $this->sent());
    }
}
