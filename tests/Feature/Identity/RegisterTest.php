<?php

namespace Tests\Feature\Identity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Src\Identity\Infrastructure\Notifications\AccountMailNotification;

class RegisterTest extends IdentityTestCase
{
    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Budi Test '.Str::random(6),
            'email' => Str::lower(Str::random(10)).'@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'candidate',
        ], $override);
    }

    public function test_register_candidate_provisions_profile_and_hashes_password_once(): void
    {
        $this->setting(['candidate_account_auto_activation' => 1]);
        $payload = $this->payload();

        $response = $this->postJson('/api/register', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Registration Succeeded')
            ->assertJsonPath('data.email', $payload['email'])
            ->assertJsonPath('data.role', 'candidate')
            ->assertJsonPath('data.username', Str::slug($payload['name']))
            ->assertJsonMissingPath('data.password');

        $this->assertStringEndsWith('backend/image/default.png', $response->json('data.image_url'));

        $id = $response->json('data.id');
        $row = DB::table('users')->find($id);
        $this->assertTrue(Hash::check('password123', $row->password));
        $this->assertSame(1, (int) $row->status);
        $this->assertSame(1, DB::table('candidates')->where('user_id', $id)->count());
        $this->assertSame(0, DB::table('companies')->where('user_id', $id)->count());
        $this->assertSame($payload['email'], DB::table('contact_infos')->where('user_id', $id)->value('email'));

        $this->postJson('/api/login', ['email' => $payload['email'], 'password' => 'password123'])->assertOk();
    }

    public function test_any_other_role_becomes_company_and_pending_when_auto_activation_off(): void
    {
        $this->setting(['employer_auto_activation' => 0]);

        $id = $this->postJson('/api/register', $this->payload(['role' => 'admin']))
            ->assertOk()
            ->assertJsonPath('data.role', 'company')
            ->assertJsonPath('data.status', 0)
            ->json('data.id');

        $this->assertSame(0, (int) DB::table('users')->find($id)->status);
        $this->assertSame(1, DB::table('companies')->where('user_id', $id)->count());
    }

    public function test_duplicate_name_gets_suffixed_username(): void
    {
        $name = 'Dup Name '.Str::random(6);
        $first = $this->postJson('/api/register', $this->payload(['name' => $name]))->assertOk()->json('data.username');
        $second = $this->postJson('/api/register', $this->payload(['name' => $name]))->assertOk()->json('data.username');

        $this->assertSame(Str::slug($name), $first);
        $this->assertStringStartsWith(Str::slug($name).'_', $second);
    }

    public function test_admins_are_notified_and_welcome_mail_sent_when_mail_configured(): void
    {
        Notification::fake();
        config([
            'mail.mailers.smtp.transport' => 'smtp', 'mail.mailers.smtp.host' => 'h', 'mail.mailers.smtp.port' => 25,
            'mail.mailers.smtp.username' => 'u', 'mail.mailers.smtp.password' => 'p', 'mail.mailers.smtp.encryption' => 'tls',
            'mail.from.address' => 'a@b.test', 'mail.from.name' => 'N',
        ]);
        $this->setting(['candidate_account_auto_activation' => 1]);
        DB::table('email_templates')->insert(['name' => 'x', 'type' => 'new_candidate', 'subject' => 'Hi {user_name}', 'message' => 'Pass {user_password}']);
        $adminId = DB::table('admins')->insertGetId(['name' => 'Adm', 'email' => Str::random(8).'@a.test', 'password' => 'x']);

        $payload = $this->payload();
        $this->postJson('/api/register', $payload)->assertOk();

        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $adminId)->where('notifiable_type', 'App\Models\Admin')->count());
        Notification::assertSentOnDemand(AccountMailNotification::class, function ($n, $channels, $notifiable) use ($payload) {
            return $notifiable->routes['mail'] === $payload['email']
                && str_starts_with($n->subject, 'Hi Budi')
                && $n->body === 'Pass ********';
        });
    }

    public function test_pending_template_used_when_not_auto_activated_and_no_mail_without_config(): void
    {
        Notification::fake();
        $this->setting(['candidate_account_auto_activation' => 0]);

        $this->postJson('/api/register', $this->payload())->assertOk();
        Notification::assertNothingSent();
    }

    public function test_mail_failure_never_fails_registration(): void
    {
        config([
            'mail.mailers.smtp.transport' => 'smtp', 'mail.mailers.smtp.host' => 'h', 'mail.mailers.smtp.port' => 25,
            'mail.mailers.smtp.username' => 'u', 'mail.mailers.smtp.password' => 'p', 'mail.mailers.smtp.encryption' => 'tls',
            'mail.from.address' => 'a@b.test', 'mail.from.name' => 'N',
        ]);
        Notification::shouldReceive('route')->andThrow(new \RuntimeException('smtp down'));

        $this->postJson('/api/register', $this->payload())->assertOk();
    }

    public function test_validation(): void
    {
        $existing = $this->makeUser();

        $this->postJson('/api/register', [])->assertStatus(422)->assertJsonValidationErrors(['name', 'email', 'password']);
        $this->postJson('/api/register', $this->payload(['email' => $existing->email]))->assertStatus(422)->assertJsonValidationErrors(['email']);
        $this->postJson('/api/register', $this->payload(['password_confirmation' => 'different1']))->assertStatus(422)->assertJsonValidationErrors(['password']);
        $this->postJson('/api/register', $this->payload(['password' => 'short', 'password_confirmation' => 'short']))->assertStatus(422)->assertJsonValidationErrors(['password']);
    }
}
