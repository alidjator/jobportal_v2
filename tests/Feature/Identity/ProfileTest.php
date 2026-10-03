<?php

namespace Tests\Feature\Identity;

class ProfileTest extends IdentityTestCase
{
    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/profile')->assertUnauthorized()->assertJsonStructure(['error']);
        $this->withToken('bogus')->getJson('/api/profile')->assertUnauthorized();
    }

    public function test_profile_returns_user_row_without_secrets(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonStructure(['data' => ['image_url']])
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');
    }

    public function test_user_endpoint_returns_unwrapped_user(): void
    {
        $user = $this->makeUser();

        $this->getJson('/api/user')->assertUnauthorized();
        $this->withToken($user->createToken('t')->plainTextToken)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonMissingPath('data')
            ->assertJsonMissingPath('password');
    }

    public function test_user_info_returns_profile_and_echoes_token(): void
    {
        $user = $this->makeUser('company');
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->getJson('/api/user-info')
            ->assertOk()
            ->assertJsonPath('data.token', $token)
            ->assertJsonPath('data.message', 'User data retrieved successfully')
            ->assertJsonPath('data.user.profile_for', 'company');
    }

    public function test_user_info_without_valid_token_is_401(): void
    {
        $this->getJson('/api/user-info')->assertUnauthorized()->assertExactJson(['error' => 'Unauthenticated User']);
        $this->withToken('bogus')->getJson('/api/user-info')->assertUnauthorized()->assertExactJson(['error' => 'Unauthenticated User']);
    }
}
