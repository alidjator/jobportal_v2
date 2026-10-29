<?php

namespace Src\DemoData\Application;

use Src\Company\Domain\MemberPreset;
use Src\Identity\Application\Admin\AdminRoles;
use Src\Identity\Application\Admin\CreateAdmin;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * Fills a development database with fictitious data (`docs/design/phase-8-demo-data.md`) through the same use cases the API runs, so the history, events
 * and audit records are those of real use. It runs once on a database without demo accounts; to start again the database is rebuilt (`migrate:fresh`,
 * `db:seed`). Every account gets the one password the caller passes, which is never stored anywhere but as the accounts' hashes.
 */
final readonly class SeedDemoData
{
    public function __construct(
        private CompanyMemberAccounts $accounts,
        private CreateAdmin $createAdmin,
        private DemoOrganization $organization,
        private DemoStructure $structure,
        private DemoActors $actors,
        private DemoProvider $provider,
        private DemoLibrary $library,
        private DemoBatteries $batteries,
        private DemoJobs $jobs,
        private DemoInterviewKits $interviewKits,
        private DemoScreening $screening,
        private DemoAssessments $assessments,
        private DemoJobSeekers $jobSeekers,
        private DemoApplications $applications,
        private DemoMyWork $myWork,
        private DemoWorkforce $workforce,
        private DemoCampaigns $campaigns,
        private DemoPresets $presets,
    ) {}

    /**
     * @return list<DemoAccount> the accounts to sign in with
     *
     * @throws StateConflict `demo-data-exists` when an account of the demo already exists, `demo-references-missing` without the reference data
     */
    public function execute(string $password, bool $fullBatteries = false): array
    {
        foreach (DemoCast::userEmails() as $email) {
            if ($this->accounts->emailIsRegistered($email)) {
                throw new StateConflict('The demo data already exists. Rebuild the database (migrate:fresh, db:seed) to seed it again.', 'demo-data-exists');
            }
        }

        $admin = $this->createAdmin->execute(DemoCast::ADMIN_NAME, DemoCast::ADMIN_EMAIL, $password, AdminRoles::SUPER_ADMIN);
        $adminActor = Actor::admin((int) $admin->id);

        $teams = $this->organization->build($adminActor, $password);
        $this->structure->build($this->actors->member($teams[DemoCast::SINAR]['admin_id']));
        $leadPsychologistId = $this->provider->build($adminActor, $password);
        $this->library->build($adminActor);
        $this->batteries->build($adminActor, $leadPsychologistId, $teams[DemoCast::SINAR]['company_id'], $fullBatteries);
        $jobIds = $this->jobs->build($adminActor, $teams);
        $this->interviewKits->build($teams, $jobIds);
        $screening = $this->screening->build($teams, $jobIds);
        $this->presets->build();
        $this->assessments->build($teams, $jobIds);
        $this->campaigns->campaigns($adminActor, array_map(static fn (array $team): int => $team['company_id'], $teams));
        $seekers = $this->jobSeekers->build($password, DemoCampaignContent::seekerCampaigns());
        $applicationIds = $this->applications->build($teams, $jobIds, $seekers, $screening);
        $this->myWork->build($teams, $jobIds, $applicationIds, $seekers);
        $this->workforce->build();
        $this->campaigns->registrants($password);

        return $this->accountList();
    }

    /** @return list<DemoAccount> */
    private function accountList(): array
    {
        $sinar = DemoCast::COMPANIES[DemoCast::SINAR];
        $arunika = DemoCast::COMPANIES[DemoCast::ARUNIKA];
        $accounts = [
            new DemoAccount('Console', 'Platform admin ('.AdminRoles::SUPER_ADMIN.')', DemoCast::ADMIN_NAME, DemoCast::ADMIN_EMAIL),
            new DemoAccount('Workspace', MemberPreset::CompanyAdmin->value.', '.$sinar['name'].', group member', $sinar['admin_name'], $sinar['admin_email']),
        ];

        foreach (DemoCast::sinarMembers() as $member) {
            $accounts[] = new DemoAccount('Workspace', $member['preset']->value.', '.$sinar['name'], $member['name'], $member['email']);
        }

        $accounts[] = new DemoAccount('Workspace', MemberPreset::CompanyAdmin->value.', '.$arunika['name'], $arunika['admin_name'], $arunika['admin_email']);

        foreach (DemoCast::psychologists() as $psychologist) {
            $accounts[] = new DemoAccount('Portal Mitra', 'psychologist ('.$psychologist['role']->value.'), '.DemoCast::PROVIDER_NAME, $psychologist['name'], $psychologist['email']);
        }

        foreach (DemoCast::jobSeekers() as $seeker) {
            $accounts[] = new DemoAccount('Career', 'job seeker', $seeker['name'], $seeker['email']);
        }

        return $accounts;
    }
}
