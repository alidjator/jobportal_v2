<?php

namespace Tests\Feature\Identity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Identity\Application\UserProfiles;
use Src\Identity\Domain\Exceptions\SocialAuthFailed;
use Src\Identity\Domain\SocialIdentityVerifier;
use Src\Identity\Domain\VerifiedSocialIdentity;
use Src\Identity\Infrastructure\Persistence\UserModel;
use Tests\FeatureTestCase;

abstract class IdentityTestCase extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(UserProfiles::class, new class implements UserProfiles
        {
            public function forUser(array $user): ?array
            {
                return ['profile_for' => $user['role'], 'user_id' => $user['id']];
            }
        });

        foreach (['industry_types', 'organization_types', 'team_sizes'] as $table) {
            DB::table($table)->insert(['created_at' => now(), 'updated_at' => now()]);
        }
    }

    protected function setting(array $values): void
    {
        DB::table('settings')->delete();
        DB::table('settings')->insert($values);
    }

    protected function makeUser(string $role = 'candidate', string $password = 'secret-pass-1'): UserModel
    {
        return UserModel::query()->create([
            'role' => $role,
            'name' => 'User '.Str::random(5),
            'username' => 'u'.Str::lower(Str::random(10)),
            'email' => Str::lower(Str::random(10)).'@example.test',
            'password' => $password,
        ]);
    }

    protected function fakeVerifier(?VerifiedSocialIdentity $identity, ?string $failure = null): void
    {
        $this->app->instance(SocialIdentityVerifier::class, new class($identity, $failure) implements SocialIdentityVerifier
        {
            public function __construct(private ?VerifiedSocialIdentity $identity, private ?string $failure) {}

            public function verify(string $provider, string $accessToken): VerifiedSocialIdentity
            {
                if ($this->failure !== null) {
                    throw new SocialAuthFailed($this->failure);
                }

                return $this->identity;
            }
        });
    }
}
