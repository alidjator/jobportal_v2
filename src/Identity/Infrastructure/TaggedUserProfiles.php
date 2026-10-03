<?php

namespace Src\Identity\Infrastructure;

use Illuminate\Contracts\Container\Container;
use Src\Identity\Application\UserProfiles;
use Src\Shared\Application\Contracts\UserProfileProjection;

/** Memilih projection ber-tag `user-profile-projections` sesuai role user. */
class TaggedUserProfiles implements UserProfiles
{
    public function __construct(private readonly Container $container) {}

    public function forUser(array $user): ?array
    {
        $role = $user['role'] === 'candidate' ? 'candidate' : 'company';

        foreach ($this->container->tagged('user-profile-projections') as $projection) {
            /** @var UserProfileProjection $projection */
            if ($projection->role() === $role) {
                return $projection->project((int) $user['id']);
            }
        }

        return null;
    }
}
