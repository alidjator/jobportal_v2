<?php

namespace Tests\Unit\Identity;

use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Src\Identity\Infrastructure\TaggedUserProfiles;
use Src\Shared\Application\Contracts\UserProfileProjection;

class TaggedUserProfilesTest extends TestCase
{
    private function projection(string $role): UserProfileProjection
    {
        return new class($role) implements UserProfileProjection
        {
            public function __construct(private string $role) {}

            public function role(): string
            {
                return $this->role;
            }

            public function project(int $userId): array
            {
                return ['role' => $this->role, 'id' => $userId];
            }
        };
    }

    public function test_selects_projection_by_role_and_returns_null_when_missing(): void
    {
        $container = new Container;
        $container->instance('p.candidate', $this->projection('candidate'));
        $container->instance('p.company', $this->projection('company'));
        $container->tag(['p.candidate', 'p.company'], 'user-profile-projections');

        $profiles = new TaggedUserProfiles($container);

        $this->assertSame(['role' => 'candidate', 'id' => 5], $profiles->forUser(['id' => 5, 'role' => 'candidate']));
        $this->assertSame(['role' => 'company', 'id' => 6], $profiles->forUser(['id' => 6, 'role' => 'company']));

        $empty = new Container;
        $empty->tag([], 'user-profile-projections');
        $this->assertNull((new TaggedUserProfiles($empty))->forUser(['id' => 1, 'role' => 'candidate']));
    }
}
