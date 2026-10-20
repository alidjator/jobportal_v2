<?php

namespace Src\LegacyImport\Application;

use DateTimeZone;
use Src\Shared\Application\TransactionManager;
use stdClass;

/**
 * Imports the job-alert opt-ins: **one daily alert per account**, built from the roles in `candidate_job_alerts` (the only criterion they had) with the email channel on, named "Dari karir.pkp", and
 * with the import time as its cursor, so the first digest holds only jobs that go live later (a listener moves the cursor again at the first sign-in). The account switch `candidates.received_job_alert`
 * decides the state: on is active, off with roles present is **paused**, so nothing is lost and the owner can resume. A role that matches no new master is reported; an account none of whose roles match
 * gets no alert. The other `*_alert` flags are not carried. `docs/design/phase-8-activation.md` section 9.
 */
final readonly class JobAlertsImporter implements Importer
{
    public const string ENTITY = 'job-alerts';

    /** The id-map entity under which a legacy candidate id is remembered (one alert per candidate). */
    public const string ALERT = 'job_alert';

    private const string NAME = 'Dari karir.pkp';

    /** The most values one filter of an alert holds. */
    private const int MAX_ROLES = 20;

    public function __construct(
        private LegacySource $legacy,
        private JobAlertWriter $writer,
        private TransactionManager $transactions,
    ) {}

    public function entity(): string
    {
        return self::ENTITY;
    }

    public function dependsOn(): array
    {
        return [MastersImporter::ENTITY, AccountsImporter::ENTITY, ApplicationsImporter::ENTITY, SavedJobsImporter::ENTITY];
    }

    public function run(ImportContext $context): void
    {
        /** @var array<int, list<int>> $roles the legacy role ids of each legacy candidate */
        $roles = [];

        foreach ($this->legacy->all('candidate_job_alerts', ['id', 'candidate_id', 'job_role_id']) as $row) {
            $roles[(int) $row->candidate_id][(int) $row->job_role_id] = (int) $row->job_role_id;
        }

        $candidateIds = array_keys($roles);
        $candidates = [];

        foreach ($this->legacy->whereIn('candidates', 'id', $candidateIds, ['id', 'user_id', 'received_job_alert']) as $candidate) {
            $candidates[(int) $candidate->id] = $candidate;
        }

        $users = $context->idMap->findMany(AccountsImporter::USER, array_values(array_map(static fn (stdClass $candidate): int => (int) $candidate->user_id, $candidates)));
        $already = $context->idMap->findMany(self::ALERT, $candidateIds);
        $masters = $context->idMap->findMany(MasterCatalog::entityFor('job_roles'), array_values(array_unique(array_merge(...array_map('array_values', array_values($roles) ?: [[]])))));

        $this->transactions->run(function () use ($roles, $candidates, $users, $already, $masters, $context): void {
            foreach ($roles as $candidateId => $legacyRoles) {
                $this->importCandidate($candidateId, array_values($legacyRoles), $candidates[$candidateId] ?? null, $users, isset($already[$candidateId]), $masters, $context);
            }
        });
    }

    /**
     * @param  list<int>  $legacyRoles
     * @param  array<int, int>  $users
     * @param  array<int, int>  $masters
     */
    private function importCandidate(int $candidateId, array $legacyRoles, ?stdClass $candidate, array $users, bool $alreadyImported, array $masters, ImportContext $context): void
    {
        $report = $context->report;
        $report->read(self::ENTITY);

        if ($alreadyImported) {
            $report->note(self::ENTITY, 'already-imported');

            return;
        }

        $userId = $candidate === null ? null : ($users[(int) $candidate->user_id] ?? null);

        if ($candidate === null || $userId === null) {
            $report->skipped(self::ENTITY, $candidateId, $candidate === null ? 'not-a-job-seeker' : 'user-not-imported');

            return;
        }

        $matched = [];

        foreach ($legacyRoles as $legacyRole) {
            if (isset($masters[$legacyRole])) {
                $matched[$masters[$legacyRole]] = $masters[$legacyRole];
            }
        }

        $unmatched = count($legacyRoles) - count(array_filter($legacyRoles, static fn (int $role): bool => isset($masters[$role])));

        if ($unmatched > 0) {
            $report->note(self::ENTITY, 'role-not-matched', $unmatched);
        }

        if ($matched === []) {
            $report->skipped(self::ENTITY, $candidateId, 'no-role-matched');
            $context->summary->jobSeeker($userId, 'job_alert_skipped', 'no_role_matched');

            return;
        }

        sort($matched);
        $overLimit = max(0, count($matched) - self::MAX_ROLES);
        $roleIds = array_slice($matched, 0, self::MAX_ROLES);
        $paused = ! (bool) $candidate->received_job_alert;

        if ($context->write) {
            $newId = $this->writer->insert($userId, self::NAME, ['role_id' => $roleIds], $paused, $context->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
            $context->idMap->remember(self::ALERT, $candidateId, $newId, $context->clock->now());
        }

        $context->summary->jobSeeker($userId, 'job_alert_imported');
        $context->summary->jobSeeker($userId, 'job_alert_imported', 'roles', count($roleIds), inTotal: false);

        if ($paused) {
            $context->summary->jobSeeker($userId, 'job_alert_imported', 'paused', inTotal: false);
        }

        if ($unmatched + $overLimit > 0) {
            $context->summary->jobSeeker($userId, 'job_alert_imported', 'roles_left_out', $unmatched + $overLimit, inTotal: false);
        }

        $report->imported(self::ENTITY);
    }
}
