<?php

namespace Src\Recruitment\Infrastructure\Tasks;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Recruitment\Application\RecruitmentSettings;
use Src\Recruitment\Domain\InterviewStatus;
use Src\Recruitment\Domain\OfferStatus;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\Tasks\ApplicantTask;
use Src\Shared\Application\Tasks\ApplicantTaskSource;

/**
 * What waits for a job seeker in Recruitment: an interview they have not answered and that has not started (`interview_confirm`, due at its start), an offer they have not answered and whose deadline has not passed
 * (`offer_answer`, due at the deadline) and the consent a pre-employment check asks for (`check_consent`, no due time; none while the feature is switched off). Applications that are rejected or withdrawn, and applicants whose
 * data was anonymised, give nothing.
 */
final readonly class ApplicantRecruitmentTasks implements ApplicantTaskSource
{
    public function __construct(private ConnectionInterface $connection, private Settings $settings) {}

    public function tasks(int $userId, DateTimeImmutable $now, int $limit): array
    {
        $at = $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $tasks = [];

        $interviews = $this->live($this->connection->table('recruitment_interviews as i')->join('recruitment_applications as a', 'a.id', '=', 'i.application_id'), $userId)
            ->where('i.status', InterviewStatus::Pending->value)
            ->where('i.starts_at', '>', $at)
            ->orderBy('i.starts_at')->orderBy('i.id')->limit($limit)
            ->get(['i.id', 'i.application_id', 'a.job_id', 'a.company_id', 'i.starts_at as due', 'i.created_at']);

        foreach ($interviews as $row) {
            $tasks[] = $this->task(ApplicantTask::INTERVIEW_CONFIRM, $row);
        }

        $offers = $this->live($this->connection->table('recruitment_offers as o')->join('recruitment_applications as a', 'a.id', '=', 'o.application_id'), $userId)
            ->where('o.status', OfferStatus::Pending->value)
            ->where('o.expires_at', '>', $at)
            ->orderBy('o.expires_at')->orderBy('o.id')->limit($limit)
            ->get(['o.id', 'o.application_id', 'a.job_id', 'a.company_id', 'o.expires_at as due', 'o.created_at']);

        foreach ($offers as $row) {
            $tasks[] = $this->task(ApplicantTask::OFFER_ANSWER, $row);
        }

        if ($this->settings->boolean(RecruitmentSettings::PRE_EMPLOYMENT_CHECKS_ENABLED)) {
            $checks = $this->live($this->connection->table('recruitment_application_checks as c')->join('recruitment_applications as a', 'a.id', '=', 'c.application_id'), $userId)
                ->where('c.status', 'requested')
                ->where('c.consent_required', true)
                ->whereNull('c.consented_at')->whereNull('c.consent_refused_at')
                ->orderBy('c.created_at')->orderBy('c.id')->limit($limit)
                ->get(['c.id', 'c.application_id', 'a.job_id', 'a.company_id', 'c.created_at', $this->connection->raw('NULL as due')]);

            foreach ($checks as $row) {
                $tasks[] = $this->task(ApplicantTask::CHECK_CONSENT, $row);
            }
        }

        return $tasks;
    }

    /** The applications of the account that are still in play and whose applicant is still known. */
    private function live(Builder $query, int $userId): Builder
    {
        return $query->where('a.user_id', $userId)->whereNotNull('a.profile_snapshot')->whereNotIn('a.stage_type', ['rejected', 'withdrawn']);
    }

    private function task(string $key, object $row): ApplicantTask
    {
        return new ApplicantTask(
            $key,
            (int) $row->id,
            (int) $row->application_id,
            (int) $row->job_id,
            (int) $row->company_id,
            $row->due === null ? null : new DateTimeImmutable((string) $row->due, new DateTimeZone('UTC')),
            new DateTimeImmutable((string) $row->created_at, new DateTimeZone('UTC')),
        );
    }
}
