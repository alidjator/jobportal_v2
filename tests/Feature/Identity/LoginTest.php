<?php

namespace Tests\Feature\Identity;

use Illuminate\Support\Facades\DB;

class LoginTest extends IdentityTestCase
{
    public function test_login_returns_token_and_profile(): void
    {
        $user = $this->makeUser('candidate', 'secret-pass-1');

        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret-pass-1'])
            ->assertOk()
            ->assertJsonPath('data.message', 'Login Succeeded')
            ->assertJsonPath('data.user.profile_for', 'candidate')
            ->assertJsonPath('data.user.user_id', $user->id);

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);

        // token memakai tokenable_type lama dan dapat dipakai langsung
        $this->assertSame('App\Models\User', DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->value('tokenable_type'));
        $this->withToken($token)->getJson('/api/profile')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_company_login_gets_company_profile(): void
    {
        $user = $this->makeUser('company');

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret-pass-1'])
            ->assertOk()
            ->assertJsonPath('data.user.profile_for', 'company');
    }

    public function test_wrong_password_and_unknown_email_are_indistinguishable(): void
    {
        $user = $this->makeUser();

        $a = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'nope-nope-1'])
            ->assertUnauthorized()->assertExactJson(['error' => 'Invalid Credentials']);
        $b = $this->postJson('/api/login', ['email' => 'nobody-'.uniqid().'@example.test', 'password' => 'x'])
            ->assertUnauthorized()->assertExactJson(['error' => 'Invalid Credentials']);

        $this->assertSame($a->getContent(), $b->getContent());
    }

    public function test_user_without_password_cannot_login_by_password(): void
    {
        $user = $this->makeUser();
        DB::table('users')->where('id', $user->id)->update(['password' => null]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'anything1'])->assertUnauthorized();
    }

    public function test_validation_errors(): void
    {
        $this->postJson('/api/login', [])->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);
        $this->postJson('/api/login', ['email' => 'bad', 'password' => 'x'])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }
}
