<?php

namespace Src\JobSeeker\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/**
 * The profiles of active job seekers that are complete enough to apply (see `ProfileCompleteness`): a phone number, a province and a city, a resume, and an
 * experience or an education entry. The query joins `identity_users` as `u` and the profile as `p`; the account must be a job seeker's, active and not anonymised.
 */
final class CompleteProfiles
{
    public static function query(ConnectionInterface $connection): Builder
    {
        return $connection->table('jobseeker_profiles as p')
            ->join('identity_users as u', 'u.id', '=', 'p.user_id')
            ->where('u.type', 'job_seeker')
            ->where('u.status', 'active')
            ->whereNull('u.anonymized_at')
            ->whereNotNull('p.phone')
            ->whereNotNull('p.province_id')
            ->whereNotNull('p.city_id')
            ->whereExists(fn (Builder $q) => $q->selectRaw('1')->from('jobseeker_resumes as r')->whereColumn('r.user_id', 'p.user_id'))
            ->where(fn (Builder $q) => $q
                ->whereExists(fn (Builder $e) => $e->selectRaw('1')->from('jobseeker_experiences as x')->whereColumn('x.user_id', 'p.user_id'))
                ->orWhereExists(fn (Builder $e) => $e->selectRaw('1')->from('jobseeker_educations as d')->whereColumn('d.user_id', 'p.user_id')));
    }
}
