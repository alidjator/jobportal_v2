<?php

namespace Src\DemoData\Application;

use DateTimeImmutable;
use LogicException;
use Src\Campaign\Application\CampaignInput;
use Src\Campaign\Application\CreateCampaign;
use Src\Identity\Application\Admin\ListAdmins;
use Src\Identity\Application\Auth\RegisterJobSeeker;
use Src\Identity\Application\Auth\RegisterJobSeekerCommand;
use Src\Identity\Application\Auth\SignOutEverywhere;
use Src\Identity\Application\Users\ListAdminUsers;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The demo campaigns (`docs/design/phase-8-demo-data.md`): the platform admin makes the campaigns of {@see DemoCampaignContent} (some of the platform, some of one of the two companies; running, not started, ended and
 * switched off), then the job seekers sign up through the running ones the way a visitor does with the campaign link, so the results of each campaign (sign-ups, applicants, applications) are not empty. A code that exists is
 * skipped and so is an email that is registered, so it can run again.
 */
final readonly class DemoCampaigns
{
    public function __construct(
        private ListAdmins $admins,
        private ListAdminUsers $users,
        private DemoActors $actors,
        private CreateCampaign $createCampaign,
        private RegisterJobSeeker $register,
        private SignOutEverywhere $signOut,
        private CompanyMemberAccounts $accounts,
        private Clock $clock,
    ) {}

    /** @throws StateConflict `demo-campaigns-need-demo-data` when the platform admin of the demo does not exist */
    public function admin(): Actor
    {
        foreach ($this->admins->execute() as $admin) {
            if (mb_strtolower($admin->email) === mb_strtolower(DemoCast::ADMIN_EMAIL)) {
                return Actor::admin($admin->id);
            }
        }

        throw new StateConflict('The demo platform admin does not exist. Run demo:seed first.', 'demo-campaigns-need-demo-data');
    }

    /**
     * The company id of each company of the demo, by its key.
     *
     * @return array<string, int>
     *
     * @throws StateConflict `demo-campaigns-need-demo-data`
     */
    public function companyIds(): array
    {
        $ids = [];

        foreach (DemoCast::COMPANIES as $key => $company) {
            $ids[$key] = $this->companyOf($company['admin_email']);
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $companyIds  by company key
     * @return int how many campaigns this run made
     */
    public function campaigns(Actor $admin, array $companyIds): int
    {
        $today = $this->clock->now();
        $made = 0;

        foreach (DemoCampaignContent::campaigns() as $code => $campaign) {
            $owner = $campaign['owner'];
            $input = new CampaignInput($campaign['name'], $campaign['type'], $this->day($today, $campaign['from']), $this->day($today, $campaign['to']), $campaign['active']);

            try {
                $this->createCampaign->execute($admin, $code, $input, $owner === null ? null : ($companyIds[$owner] ?? throw new LogicException("The company {$owner} of campaign {$code} is not in the demo.")), null, DemoCast::USER_AGENT);
                $made++;
            } catch (StateConflict $conflict) {
                if ($conflict->errorCode() !== 'campaign-code-taken') {
                    throw $conflict;
                }
            }
        }

        return $made;
    }

    /**
     * Signs up the job seekers of the running campaigns through their codes and ends the session the sign-up opened.
     *
     * @return int how many accounts this run made
     */
    public function registrants(string $password): int
    {
        $made = 0;

        foreach (DemoCampaignContent::registrants() as $code => $names) {
            foreach ($names as $name) {
                $email = DemoCampaignContent::email($name);

                if ($this->accounts->emailIsRegistered($email)) {
                    continue;
                }

                $registered = $this->register->execute(new RegisterJobSeekerCommand($name, $email, $password, DemoCast::LANGUAGE, $code, DemoCast::USER_AGENT, null, DemoCast::USER_AGENT));
                $this->signOut->execute(Actor::jobSeeker((int) $registered->user->id), null, DemoCast::USER_AGENT);
                $made++;
            }
        }

        return $made;
    }

    private function companyOf(string $email): int
    {
        foreach ($this->users->execute($email, new PageRequest(1, 5))->items as $view) {
            if (mb_strtolower($view->user->email()) === mb_strtolower($email)) {
                return (int) $this->actors->member((int) $view->user->id)->membership?->companyId;
            }
        }

        throw new StateConflict('The demo account :email does not exist. Run demo:seed first.', 'demo-campaigns-need-demo-data', parameters: ['email' => $email]);
    }

    /** The calendar day `$days` from today as `Y-m-d`, or null for an open end. */
    private function day(DateTimeImmutable $today, ?int $days): ?string
    {
        return $days === null ? null : $today->modify(sprintf('%+d days', $days))->format('Y-m-d');
    }
}
