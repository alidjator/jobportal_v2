<?php

namespace Tests\Feature\Platform;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Src\Identity\Application\Auth\VerificationCodeSender;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\Concerns\SignsInAdmins;
use Tests\FeatureTestCase;
use Tests\Support\RecordingVerificationCodeSender;

final class IdentityAuditTest extends FeatureTestCase
{
    use SignsInAdmins;

    private const string PASSWORD = 'correct-horse-battery';

    private const array AGENT = ['User-Agent' => 'TestAgent/1.0'];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-03 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $attributes */
    private function user(array $attributes = []): UserModel
    {
        return UserModel::query()->create([
            'type' => 'job_seeker', 'name' => 'Siti', 'email' => 'siti@example.test', 'password' => Hash::make(self::PASSWORD),
            'status' => 'active', 'locale' => 'en', ...$attributes,
        ]);
    }

    /** @return list<object> */
    private function rows(?string $action = null): array
    {
        return DB::table('platform_audit_logs')
            ->when($action, fn ($query) => $query->where('action', $action))
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function test_a_successful_sign_in_is_recorded_with_address_and_agent(): void
    {
        $user = $this->user();

        $this->postJson('/api/v1/auth/login', ['email' => 'siti@example.test', 'password' => self::PASSWORD], self::AGENT)->assertOk();

        $rows = $this->rows('auth.login');
        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame('user', $row->actor_type);
        $this->assertSame($user->id, (int) $row->actor_id);
        $this->assertSame('user', $row->subject_type);
        $this->assertSame($user->id, (int) $row->subject_id);
        $this->assertSame('127.0.0.1', $row->ip_address);
        $this->assertSame('TestAgent/1.0', $row->user_agent);
        $this->assertNull($row->metadata);
        $this->assertStringStartsWith('2026-10-03 10:00:00', $row->occurred_at);
    }

    public function test_failed_sign_ins_are_recorded_without_the_typed_email_or_password(): void
    {
        $user = $this->user();
        $this->user(['email' => 'suspended@example.test', 'status' => 'suspended']);

        $this->postJson('/api/v1/auth/login', ['email' => 'ghost@example.test', 'password' => 'secret-guess'], self::AGENT)->assertStatus(401);
        $this->postJson('/api/v1/auth/login', ['email' => 'siti@example.test', 'password' => 'secret-guess'], self::AGENT)->assertStatus(401);
        $this->postJson('/api/v1/auth/login', ['email' => 'suspended@example.test', 'password' => self::PASSWORD], self::AGENT)->assertStatus(403);

        $rows = $this->rows('auth.login_failed');
        $this->assertCount(3, $rows);

        $this->assertNull($rows[0]->actor_id);
        $this->assertNull($rows[0]->subject_type);
        $this->assertSame('invalid-credentials', json_decode($rows[0]->metadata, true)['reason']);
        $this->assertSame($user->id, (int) $rows[1]->actor_id);
        $this->assertSame('account-suspended', json_decode($rows[2]->metadata, true)['reason']);

        $dump = json_encode($rows);
        foreach (['ghost@example.test', 'siti@example.test', 'secret-guess', self::PASSWORD] as $secret) {
            $this->assertStringNotContainsString($secret, $dump);
        }
    }

    public function test_an_admin_sign_in_is_recorded_as_an_admin_actor(): void
    {
        $admin = AdminModel::query()->create(['name' => 'Root', 'email' => 'root@example.test', 'password' => Hash::make(self::PASSWORD), 'status' => 'active']);

        $this->completeAdminLogin('root@example.test', self::PASSWORD)->assertOk();

        $row = $this->rows('auth.login')[0];
        $this->assertSame('admin', $row->actor_type);
        $this->assertSame($admin->id, (int) $row->actor_id);
        $this->assertSame('admin', $row->subject_type);
    }

    public function test_a_password_reset_is_recorded(): void
    {
        $sender = new RecordingVerificationCodeSender;
        $this->app->instance(VerificationCodeSender::class, $sender);
        $user = $this->user();

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'siti@example.test'])->assertNoContent();
        $this->postJson('/api/v1/auth/password/reset', ['email' => 'siti@example.test', 'code' => $sender->lastCode(), 'password' => 'my-new-password'], self::AGENT)->assertNoContent();

        $row = $this->rows('auth.password_reset')[0];
        $this->assertSame($user->id, (int) $row->actor_id);
        $this->assertSame('TestAgent/1.0', $row->user_agent);
        $this->assertStringNotContainsString($sender->lastCode(), json_encode($this->rows()));
        $this->assertStringNotContainsString('my-new-password', json_encode($this->rows()));
    }

    public function test_the_account_deletion_lifecycle_is_recorded(): void
    {
        $user = $this->user();
        $token = $user->createToken('phone', ['*'], now()->addDay())->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/me/deletion', ['password' => self::PASSWORD, 'confirm' => true], self::AGENT)->assertOk();

        $requested = $this->rows('account.deletion_requested')[0];
        $this->assertSame($user->id, (int) $requested->actor_id);
        $this->assertSame('2026-11-02T10:00:00+00:00', json_decode($requested->metadata, true)['scheduled_for']);
        $this->assertSame('TestAgent/1.0', $requested->user_agent);

        $this->postJson('/api/v1/auth/account/restore', ['email' => 'siti@example.test', 'password' => self::PASSWORD, 'confirm' => true], self::AGENT)->assertOk();
        $this->assertCount(1, $this->rows('account.deletion_cancelled'));

        $this->app['auth']->forgetGuards();
        $this->withToken($this->postJson('/api/v1/auth/login', ['email' => 'siti@example.test', 'password' => self::PASSWORD])->json('data.token'))
            ->postJson('/api/v1/me/deletion', ['password' => self::PASSWORD, 'confirm' => true])->assertOk();

        Carbon::setTestNow('2026-11-02 10:00:00');
        Artisan::call('identity:anonymize-due-accounts');

        $anonymized = $this->rows('account.anonymized')[0];
        $this->assertSame('system', $anonymized->actor_type);
        $this->assertNull($anonymized->actor_id);
        $this->assertSame('user', $anonymized->subject_type);
        $this->assertSame($user->id, (int) $anonymized->subject_id);
    }

    public function test_registration_and_email_verification_are_not_audited(): void
    {
        $this->app->instance(VerificationCodeSender::class, new RecordingVerificationCodeSender);

        $this->postJson('/api/v1/auth/register', ['name' => 'Siti', 'email' => 'new@example.test', 'password' => 'a-long-enough-password'])->assertCreated();

        $this->assertSame(['auth.login'], array_map(fn ($row) => $row->action, $this->rows()));
    }

    public function test_a_very_long_user_agent_is_truncated_to_the_column_length(): void
    {
        $this->user();

        $this->postJson('/api/v1/auth/login', ['email' => 'siti@example.test', 'password' => self::PASSWORD], ['User-Agent' => str_repeat('A', 400)])->assertOk();

        $this->assertSame(255, mb_strlen($this->rows('auth.login')[0]->user_agent));
    }
}
