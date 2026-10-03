<?php

namespace Src\Recruitment\Domain\JobBoard;

/**
 * Pemberitahuan atas aktivitas kandidat (best-effort: kegagalan tidak boleh menggagalkan aksi utama).
 */
interface JobActivityNotifier
{
    /**
     * @param  array{id:int, name:string, role:string, recent_activities_alert:bool}  $candidateUser
     * @param  array{id:int, title:string, slug:string, company_user_id:int|null, company_user_name:string|null}  $job
     */
    public function applied(array $candidateUser, array $job): void;

    /**
     * @param  array{id:int, name:string, role:string, recent_activities_alert:bool}  $candidateUser
     * @param  array{id:int, title:string, slug:string, company_user_id:int|null, company_user_name:string|null}  $job
     */
    public function bookmarked(array $candidateUser, array $job): void;
}
