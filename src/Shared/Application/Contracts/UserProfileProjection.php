<?php

namespace Src\Shared\Application\Contracts;

/**
 * Kontrak lintas-context: Identity butuh representasi profil user (kandidat/perusahaan)
 * untuk respons login/user-info tanpa bergantung pada context lain.
 *
 * Diimplementasikan oleh CandidateProfile (role `candidate`) dan Company (role `company`),
 * lalu di-tag `user-profile-projections` pada service provider masing-masing:
 *
 *     $this->app->tag(CandidateProfileProjection::class, 'user-profile-projections');
 */
interface UserProfileProjection
{
    /** `candidate` atau `company`. */
    public function role(): string;

    /**
     * Bentuk sama dengan CandidateResource / CompanyResource aplikasi lama.
     *
     * @return array<string,mixed>
     */
    public function project(int $userId): array;
}
