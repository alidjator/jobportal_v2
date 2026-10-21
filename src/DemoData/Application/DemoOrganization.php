<?php

namespace Src\DemoData\Application;

use LogicException;
use Src\Company\Application\CreateCompany;
use Src\Company\Application\CreateCompanyCommand;
use Src\Company\Application\Documents\ApproveCompanyDocument;
use Src\Company\Application\Documents\ListCompanyDocuments;
use Src\Company\Application\Documents\ListCompanyDocumentsForAdmin;
use Src\Company\Application\Documents\SubmitCompanyDocument;
use Src\Company\Application\Invitations\AcceptInvitation;
use Src\Company\Application\Invitations\AcceptInvitationCommand;
use Src\Company\Application\Team\InviteMember;
use Src\Company\Domain\CompanyRole;
use Src\DemoData\Domain\DemoPdf;
use Src\Identity\Application\Auth\SignOutEverywhere;
use Src\Identity\Application\Users\ListAdminUsers;
use Src\Organization\Application\Admin\CreateGroup;
use Src\Organization\Application\Admin\LinkTenant;
use Src\Organization\Application\Group\CreateLegalEntity;
use Src\Organization\Application\Group\LegalEntityInput;
use Src\Organization\Application\Roles\SetGroupRole;
use Src\Organization\Domain\LegalForm;
use Src\Platform\Application\Settings\SetCompanySetting;
use Src\Shared\Application\Accounts\AccountSession;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Workforce\Application\WorkforceSettings;

/**
 * The group, its two companies and their teams. The platform admin creates the group and the companies; each company admin accepts the invitation the
 * creation sent, and the platform admin lets both post jobs without a requisition; the admin of the verified company invites one member per preset and
 * holds every group permission. Both companies submit their required
 * verification documents; only those of the verified company are approved.
 */
final readonly class DemoOrganization
{
    public function __construct(
        private CreateGroup $createGroup,
        private CreateLegalEntity $createLegalEntity,
        private LinkTenant $linkTenant,
        private SetGroupRole $setGroupRole,
        private CreateCompany $createCompany,
        private InviteMember $inviteMember,
        private AcceptInvitation $acceptInvitation,
        private SignOutEverywhere $signOut,
        private ListAdminUsers $users,
        private SetCompanySetting $setCompanySetting,
        private ListCompanyDocuments $documents,
        private SubmitCompanyDocument $submitDocument,
        private ListCompanyDocumentsForAdmin $adminDocuments,
        private ApproveCompanyDocument $approveDocument,
        private DemoReferences $references,
        private DemoActors $actors,
        private DemoInbox $inbox,
    ) {}

    /**
     * The teams by company key; `members` holds the account id per preset value, the company admin included.
     *
     * @return array<string, array{company_id: int, admin_id: int, members: array<string, int>}>
     */
    public function build(Actor $admin, string $password): array
    {
        $agent = DemoCast::USER_AGENT;
        $sinarInfo = DemoCast::COMPANIES[DemoCast::SINAR];
        $arunikaInfo = DemoCast::COMPANIES[DemoCast::ARUNIKA];

        $group = $this->createGroup->execute($admin, DemoCast::GROUP_NAME, new LegalEntityInput($sinarInfo['legal_name'], LegalForm::Pt), null, $agent);
        $groupId = (int) $group->group->id;

        $sinar = $this->company($admin, $sinarInfo, $password);
        $arunika = $this->company($admin, $arunikaInfo, $password);

        $this->linkTenant->execute($admin, $groupId, (int) $group->legalEntities[0]->entity->id, $sinar['company_id'], null, $agent);
        $this->setGroupRole->execute($admin, $groupId, $sinar['admin_id'], GroupPermission::cases(), null, $agent);

        $sinarAdmin = $this->actors->member($sinar['admin_id']);
        $entity = $this->createLegalEntity->execute($sinarAdmin, new LegalEntityInput($arunikaInfo['legal_name'], LegalForm::Pt), null, $agent);
        $this->linkTenant->execute($admin, $groupId, (int) $entity->entity->id, $arunika['company_id'], null, $agent);

        foreach (DemoCast::sinarMembers() as $member) {
            $this->inviteMember->execute(
                $sinarAdmin, $member['name'], $member['email'], CompanyRole::Member, $member['preset']->permissionNames(), DemoCast::LANGUAGE, null, $agent,
            );
            $sinar['members'][$member['preset']->value] = $this->accept($member['email'], $member['name'], $password);
        }

        $this->submitDocuments($sinarAdmin, $sinarInfo['name']);
        $this->submitDocuments($this->actors->member($arunika['admin_id']), $arunikaInfo['name']);
        $this->approveDocuments($admin, $sinar['company_id']);

        return [DemoCast::SINAR => $sinar, DemoCast::ARUNIKA => $arunika];
    }

    /**
     * @param  array{name: string, legal_name: string, admin_name: string, admin_email: string}  $info
     * @return array{company_id: int, admin_id: int, members: array<string, int>}
     */
    private function company(Actor $admin, array $info, string $password): array
    {
        $created = $this->createCompany->execute($admin, new CreateCompanyCommand(
            $info['name'], $this->references->id('industries'), $this->references->id('organization-types'), $info['admin_email'], DemoCast::LANGUAGE, null, DemoCast::USER_AGENT,
        ));
        $adminId = $this->accept($info['admin_email'], $info['admin_name'], $password);
        // Both companies are tenants of the group, which open jobs only from approved requisitions by default; the demo posts jobs directly (decided 2026-10-21).
        $this->setCompanySetting->execute($admin, (int) $created->company->id, WorkforceSettings::REQUISITION_REQUIRED, false, null, DemoCast::USER_AGENT);

        return ['company_id' => (int) $created->company->id, 'admin_id' => $adminId, 'members' => ['company_admin' => $adminId]];
    }

    /**
     * Accepts the invitation sent to the email and returns the new account. A member gets a session, which is ended; a company admin gets the challenge of
     * the second factor instead (a company admin always enrols one, at the first real sign-in), so the account is found by its email.
     */
    private function accept(string $email, string $name, string $password): int
    {
        $session = $this->acceptInvitation->execute(new AcceptInvitationCommand(
            $this->inbox->invitationToken($email), $name, $password, DemoCast::LANGUAGE, DemoCast::USER_AGENT, null, DemoCast::USER_AGENT,
        ));

        if ($session instanceof AccountSession) {
            $this->signOut->execute(Actor::companyMember($session->userId), null, DemoCast::USER_AGENT);

            return $session->userId;
        }

        foreach ($this->users->execute($email, new PageRequest(1, 5))->items as $view) {
            if (mb_strtolower($view->user->email()) === mb_strtolower($email)) {
                return (int) $view->user->id;
            }
        }

        throw new LogicException("The account of {$email} was not found after accepting its invitation.");
    }

    private function submitDocuments(Actor $companyAdmin, string $companyName): void
    {
        foreach ($this->documents->execute($companyAdmin, DemoCast::LANGUAGE)->slots as $slot) {
            if (! $slot->type->isRequired || ! $slot->type->isActive) {
                continue;
            }

            $pdf = DemoPdf::make([$slot->typeName, $companyName, 'Demo document: fictitious, not a real record.']);
            $this->submitDocument->execute($companyAdmin, (int) $slot->type->id, $pdf, $slot->type->slug.'.pdf', null, DemoCast::USER_AGENT);
        }
    }

    private function approveDocuments(Actor $admin, int $companyId): void
    {
        foreach ($this->adminDocuments->execute($admin, $companyId)->slots as $slot) {
            if ($slot->document !== null && $slot->document->reviewedAt === null) {
                $this->approveDocument->execute($admin, $companyId, (int) $slot->type->id, $slot->document->submittedAt, null, DemoCast::USER_AGENT);
            }
        }
    }
}
