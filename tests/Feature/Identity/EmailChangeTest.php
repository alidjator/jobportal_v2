<?php

namespace Tests\Feature\Identity;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Src\Identity\Application\Account\AccountNoticeSender;
use Src\Identity\Application\Account\UserDataEraser;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;
use Tests\Support\CompanyMemberRows;
use Tests\Support\RecordingAccountNoticeSender;

/** `docs/design/phase-8-activation.md` section 7: the self-service change of the sign-in email (J15, C25). */
final class EmailChangeTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private const string PASSWORD = 'correct-horse-battery';

    private RecordingAccountNoticeSender $notices;

    private UserModel $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->notices = new RecordingAccountNoticeSender;
        $this->app->instance(AccountNoticeSender::class, $this->notices);

        $this->user = $this->makeUser('siti@example.test');
        $this->token = $this->tokenOf($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $attributes */
    private function makeUser(string $email, array $attributes = []): UserModel
    {
        return UserModel::query()->create([
            'type' => 'job_seeker', 'name' => 'Siti', 'email' => $email, 'password' => Hash::make(self::PASSWORD),
            'status' => 'active', 'locale' => 'id', 'email_verified_at' => '2026-10-01 00:00:00', ...$attributes,
        ]);
    }

    private function tokenOf(UserModel $user): string
    {
        return $user->createToken('phone', ['*'], now()->addDay())->plainTextToken;
    }

    /** @param  array<string, mixed>  $body */
    private function api(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->json($method, '/api/v1'.$uri, $body);
    }

    private function request(string $email = 'baru@example.test', ?string $token = null): TestResponse
    {
        return $this->api('POST', '/me/email-change', ['email' => $email], $token);
    }

    /** @param  array<string, mixed>  $extra */
    private function confirm(string $code, array $extra = ['password' => self::PASSWORD], ?string $token = null): TestResponse
    {
        return $this->api('POST', '/me/email-change/confirm', ['code' => $code, ...$extra], $token);
    }

    private function wrongCode(string $correct): string
    {
        return $correct === '000000' ? '111111' : '000000';
    }

    public function test_a_free_address_gets_a_code_and_the_answer_is_202(): void
    {
        $this->request('Baru@Example.test')->assertStatus(202);

        $codes = $this->notices->of('code');
        $this->assertCount(1, $codes);
        $this->assertSame('baru@example.test', $codes[0]['to']);
        $this->assertSame('id', $codes[0]['locale']);
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $codes[0]['code']);

        $row = DB::table('identity_email_changes')->where('user_id', $this->user->id)->first();
        $this->assertSame('baru@example.test', $row->new_email);
        $this->assertNull($row->old_code_hash);
        $this->assertNotSame($codes[0]['code'], $row->new_code_hash);
        $this->assertSame('siti@example.test', $this->user->refresh()->email);
    }

    public function test_an_address_in_use_gives_the_same_answer_but_only_a_notice_to_its_owner(): void
    {
        $this->makeUser('baru@example.test', ['name' => 'Budi']);

        $this->request('baru@example.test')->assertStatus(202);

        $this->assertSame([], $this->notices->of('code'));
        $taken = $this->notices->of('taken');
        $this->assertCount(1, $taken);
        $this->assertSame(['baru@example.test', 'Budi'], [$taken[0]['to'], $taken[0]['name']]);
        $this->assertSame(0, DB::table('identity_email_changes')->count());
    }

    public function test_asking_for_the_current_address_does_nothing(): void
    {
        $this->request('siti@example.test')->assertStatus(202);

        $this->assertSame([], $this->notices->sent);
        $this->assertSame(0, DB::table('identity_email_changes')->count());
    }

    public function test_an_address_that_does_not_receive_email_is_refused(): void
    {
        DB::table('platform_email_suppressions')->insert(['email' => 'baru@example.test', 'reason' => 'hard_bounce', 'source' => 'test', 'created_at' => now()]);

        $this->request('baru@example.test')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:email-undeliverable');

        $this->assertSame([], $this->notices->sent);
    }

    public function test_an_invalid_address_is_refused_and_a_session_is_required(): void
    {
        $this->request('not-an-email')->assertStatus(422);

        $this->app['auth']->forgetGuards();
        $this->withoutToken()->postJson('/api/v1/me/email-change', ['email' => 'baru@example.test'])->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withoutToken()->deleteJson('/api/v1/me/email-change')->assertUnauthorized();
    }

    public function test_an_account_may_ask_three_times_an_hour(): void
    {
        $this->request('a@example.test')->assertStatus(202);
        $this->request('b@example.test')->assertStatus(202);
        $this->request('c@example.test')->assertStatus(202);

        $this->request('d@example.test')->assertStatus(429)->assertJsonPath('type', 'urn:onesis:error:email-change-limit-reached');

        $this->assertCount(3, $this->notices->of('code'));
    }

    public function test_a_new_request_replaces_the_pending_one(): void
    {
        $this->request('first@example.test')->assertStatus(202);
        $firstCode = $this->notices->codeFor('first@example.test');
        $this->request('second@example.test')->assertStatus(202);

        $this->assertSame(1, DB::table('identity_email_changes')->count());
        $this->confirm($firstCode)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:email-change-code-invalid');
        $this->confirm($this->notices->codeFor('second@example.test'))->assertNoContent();
        $this->assertSame('second@example.test', $this->user->refresh()->email);
    }

    public function test_confirming_changes_the_email_marks_it_verified_and_signs_the_other_devices_out(): void
    {
        $other = $this->tokenOf($this->user);
        $this->request('baru@example.test')->assertStatus(202);

        $this->confirm($this->notices->codeFor('baru@example.test'))->assertNoContent();

        $user = $this->user->refresh();
        $this->assertSame('baru@example.test', $user->email);
        $this->assertSame('2026-10-05 09:00:00', $user->email_verified_at->format('Y-m-d H:i:s'));
        $this->assertSame(0, DB::table('identity_email_changes')->count());
        $this->assertSame(1, $user->tokens()->count());
        $this->api('GET', '/me')->assertOk()->assertJsonPath('data.email', 'baru@example.test')->assertJsonPath('data.pending_email_change', null);
        $this->api('GET', '/me', token: $other)->assertUnauthorized();

        $changed = $this->notices->of('changed');
        $this->assertCount(1, $changed);
        $this->assertSame(['siti@example.test', 'baru@example.test'], [$changed[0]['to'], $changed[0]['newEmail']]);

        $entry = DB::table('platform_audit_logs')->where('action', 'account.email_changed')->first();
        $this->assertSame($user->id, (int) $entry->subject_id);
        $this->assertEquals(['old_email' => 's***@example.test', 'new_email' => 'b***@example.test'], json_decode($entry->metadata, true));
    }

    public function test_the_pending_change_shows_on_me_masked(): void
    {
        $this->api('GET', '/me')->assertOk()->assertJsonPath('data.pending_email_change', null);

        $this->request('baru@example.test')->assertStatus(202);

        $this->api('GET', '/me')->assertOk()
            ->assertJsonPath('data.pending_email_change.email_masked', 'b***@example.test')
            ->assertJsonPath('data.pending_email_change.expires_at', '2026-10-05T09:15:00+00:00');

        Carbon::setTestNow('2026-10-05 09:16:00');
        $this->api('GET', '/me')->assertOk()->assertJsonPath('data.pending_email_change', null);
    }

    public function test_a_wrong_password_is_refused_and_does_not_spend_the_code(): void
    {
        $this->request('baru@example.test')->assertStatus(202);
        $code = $this->notices->codeFor('baru@example.test');

        $this->confirm($code, ['password' => 'not-the-password'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-password');
        $this->confirm($code, [])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-password');

        $this->assertSame(0, (int) DB::table('identity_email_changes')->value('attempts'));
        $this->confirm($code)->assertNoContent();
    }

    public function test_five_wrong_codes_end_the_request(): void
    {
        $this->request('baru@example.test')->assertStatus(202);
        $code = $this->notices->codeFor('baru@example.test');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->confirm($this->wrongCode($code))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:email-change-code-invalid');
        }

        $this->confirm($code)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:email-change-expired');
        $this->assertSame('siti@example.test', $this->user->refresh()->email);
    }

    public function test_a_code_expires_after_fifteen_minutes_and_nothing_pending_is_the_same_answer(): void
    {
        $this->confirm('123456')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:email-change-expired');

        $this->request('baru@example.test')->assertStatus(202);
        $code = $this->notices->codeFor('baru@example.test');
        Carbon::setTestNow('2026-10-05 09:15:01');

        $this->confirm($code)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:email-change-expired');
    }

    public function test_an_account_without_a_password_confirms_with_a_second_code_sent_to_its_current_address(): void
    {
        $google = $this->makeUser('gita@example.test', ['password' => null]);
        $token = $this->tokenOf($google);

        $this->request('baru@example.test', $token)->assertStatus(202);

        $this->assertCount(2, $this->notices->of('code'));
        $newCode = $this->notices->codeFor('baru@example.test');
        $oldCode = $this->notices->codeFor('gita@example.test');
        $this->assertNotNull(DB::table('identity_email_changes')->where('user_id', $google->id)->value('old_code_hash'));

        $this->confirm($newCode, [], $token)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:email-change-code-invalid');
        $this->confirm($newCode, ['old_code' => $this->wrongCode($oldCode)], $token)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:email-change-code-invalid');
        $this->confirm($newCode, ['old_code' => $oldCode], $token)->assertNoContent();

        $this->assertSame('baru@example.test', $google->refresh()->email);
    }

    public function test_another_change_within_a_day_is_refused(): void
    {
        $this->request('baru@example.test')->assertStatus(202);
        $this->confirm($this->notices->codeFor('baru@example.test'))->assertNoContent();

        $this->request('again@example.test')->assertStatus(429)->assertJsonPath('type', 'urn:onesis:error:email-change-too-soon');
    }

    public function test_cancelling_drops_the_pending_change(): void
    {
        $this->request('baru@example.test')->assertStatus(202);
        $code = $this->notices->codeFor('baru@example.test');

        $this->api('DELETE', '/me/email-change')->assertNoContent();
        $this->api('DELETE', '/me/email-change')->assertNoContent();

        $this->assertSame(0, DB::table('identity_email_changes')->count());
        $this->confirm($code)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:email-change-expired');
    }

    public function test_an_address_taken_meanwhile_is_refused_at_the_confirmation(): void
    {
        $this->request('baru@example.test')->assertStatus(202);
        $code = $this->notices->codeFor('baru@example.test');
        $this->makeUser('baru@example.test', ['name' => 'Budi']);

        $this->confirm($code)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:email-in-use');

        $this->assertSame('siti@example.test', $this->user->refresh()->email);
    }

    public function test_the_email_domain_limit_of_the_company_applies_to_a_member(): void
    {
        $companyId = (int) DB::table('company_companies')->insertGetId([
            'name' => 'PT Maju', 'slug' => 'pt-maju', 'status' => 'active', 'industry_id' => $this->industryId(), 'organization_type_id' => $this->organizationTypeId(),
            'invitation_email_domains' => json_encode(['example.test']), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $member = $this->makeUser('andi@example.test', ['type' => 'company_member', 'name' => 'Andi']);
        CompanyMemberRows::insert($companyId, $member->id, 'recruiter');
        $token = $this->tokenOf($member);

        $this->request('andi@elsewhere.test', $token)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:email-domain-not-allowed');
        $this->request('andi.baru@example.test', $token)->assertStatus(202);

        $this->assertCount(1, $this->notices->of('code'));
    }

    public function test_the_domain_limit_does_not_touch_a_job_seeker_and_the_eraser_drops_the_pending_change(): void
    {
        $this->request('baru@elsewhere.test')->assertStatus(202);
        $this->assertSame(1, DB::table('identity_email_changes')->count());

        $this->app->make(UserDataEraser::class)->erase((int) $this->user->id);

        $this->assertSame(0, DB::table('identity_email_changes')->count());
    }
}
