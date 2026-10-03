<?php

namespace Tests\Feature\Identity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Identity\Domain\VerifiedSocialIdentity;

class SocialAuthTest extends IdentityTestCase
{
    private function body(array $override = []): array
    {
        return array_merge(['firebaseToken' => 'tok', 'provider' => 'google', 'actionKey' => 'registration', 'role' => 'candidate'], $override);
    }

    private function identity(?string $email = null): VerifiedSocialIdentity
    {
        return new VerifiedSocialIdentity('uid-'.Str::random(12), 'Social Person', $email ?? Str::lower(Str::random(10)).'@example.test');
    }

    public function test_registration_creates_user_and_returns_token(): void
    {
        $identity = $this->identity();
        $this->fakeVerifier($identity);
        $this->setting(['candidate_account_auto_activation' => 1]);

        $response = $this->postJson('/api/social-media-authentication', $this->body())
            ->assertOk()
            ->assertJsonPath('data.message', 'User data retrieved successfully')
            ->assertJsonPath('data.user.profile_for', 'candidate');

        $user = DB::table('users')->where('firebase_uid', $identity->uid)->first();
        $this->assertSame($identity->email, $user->email);
        $this->assertSame('Social Person', $user->name);
        $this->assertNull($user->password);
        $this->assertSame(1, DB::table('candidates')->where('user_id', $user->id)->count());

        $this->withToken($response->json('data.token'))->getJson('/api/profile')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_company_registration_provisions_company(): void
    {
        $identity = $this->identity();
        $this->fakeVerifier($identity);

        $this->postJson('/api/social-media-authentication', $this->body(['provider' => 'facebook', 'role' => 'company']))
            ->assertOk()->assertJsonPath('data.user.profile_for', 'company');

        $id = DB::table('users')->where('firebase_uid', $identity->uid)->value('id');
        $this->assertSame(1, DB::table('companies')->where('user_id', $id)->count());
    }

    public function test_login_of_existing_user(): void
    {
        $identity = $this->identity();
        $user = $this->makeUser();
        DB::table('users')->where('id', $user->id)->update(['firebase_uid' => $identity->uid]);
        $this->fakeVerifier($identity);

        $this->postJson('/api/social-media-authentication', $this->body(['actionKey' => 'login', 'role' => null]))
            ->assertStatus(422);

        $this->postJson('/api/social-media-authentication', ['firebaseToken' => 't', 'provider' => 'google', 'actionKey' => 'login'])
            ->assertOk()
            ->assertJsonPath('data.user.user_id', $user->id);
    }

    public function test_login_for_unknown_user_returns_200_message(): void
    {
        $this->fakeVerifier($this->identity());

        $this->postJson('/api/social-media-authentication', ['firebaseToken' => 't', 'provider' => 'google', 'actionKey' => 'login'])
            ->assertOk()
            ->assertExactJson(['data' => ['message' => 'User does not exist, please register an account first']]);
    }

    public function test_registration_without_role_returns_200_message(): void
    {
        $identity = $this->identity();
        $this->fakeVerifier($identity);

        $this->postJson('/api/social-media-authentication', ['firebaseToken' => 't', 'provider' => 'google', 'actionKey' => 'registration'])
            ->assertOk()
            ->assertExactJson(['data' => ['message' => 'Role is missing']]);
        $this->assertDatabaseMissing('users', ['firebase_uid' => $identity->uid]);
    }

    public function test_registration_with_taken_email_is_rejected_without_leaking_sql(): void
    {
        $existing = $this->makeUser();
        $this->fakeVerifier($this->identity($existing->email));

        $response = $this->postJson('/api/social-media-authentication', $this->body())->assertStatus(409);
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
    }

    public function test_invalid_provider_token_is_401(): void
    {
        $this->fakeVerifier(null, 'Unauthorized - Token is invalide: expired');

        $this->postJson('/api/social-media-authentication', $this->body())
            ->assertUnauthorized()
            ->assertExactJson(['data' => ['message' => 'Unauthorized - Token is invalide: expired']]);
    }

    public function test_validation(): void
    {
        $this->postJson('/api/social-media-authentication', [])->assertStatus(422)->assertJsonValidationErrors(['firebaseToken', 'provider', 'actionKey']);
        $this->postJson('/api/social-media-authentication', $this->body(['provider' => 'twitter', 'actionKey' => 'x', 'role' => 'admin']))
            ->assertStatus(422)->assertJsonValidationErrors(['provider', 'actionKey', 'role']);
    }
}
