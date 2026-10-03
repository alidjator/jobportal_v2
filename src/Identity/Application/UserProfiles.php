<?php

namespace Src\Identity\Application;

interface UserProfiles
{
    /**
     * Profil (CandidateResource / CompanyResource) milik user, sesuai role-nya.
     *
     * @param  array<string,mixed>  $user
     * @return array<string,mixed>|null
     */
    public function forUser(array $user): ?array;
}
