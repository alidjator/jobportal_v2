<?php

namespace Src\Company\Domain;

use Src\Shared\Application\Company\CompanyPermission;

/** The named sets of permissions the invitation and edit dialogs start from. Presets are labels: only the role and the permissions are stored. */
enum MemberPreset: string
{
    case CompanyAdmin = 'company_admin';
    case Recruiter = 'recruiter';
    case Interviewer = 'interviewer';

    public function role(): CompanyRole
    {
        return $this === self::CompanyAdmin ? CompanyRole::CompanyAdmin : CompanyRole::Member;
    }

    /** @return list<CompanyPermission> every permission for a company admin */
    public function permissions(): array
    {
        return match ($this) {
            self::CompanyAdmin => CompanyPermission::cases(),
            self::Recruiter => [
                CompanyPermission::JobsCreate, CompanyPermission::JobsPublish, CompanyPermission::ApplicationsView,
                CompanyPermission::ApplicationsManage, CompanyPermission::ApplicationsExport, CompanyPermission::InterviewsManage,
                CompanyPermission::OffersManage, CompanyPermission::AssessmentsSend, CompanyPermission::CandidatesSearch,
                CompanyPermission::TalentPoolManage, CompanyPermission::MessagesSend, CompanyPermission::InterviewKitsManage,
            ],
            self::Interviewer => [CompanyPermission::ApplicationsView, CompanyPermission::InterviewsManage],
        };
    }

    /** @return list<string> */
    public function permissionNames(): array
    {
        return array_map(static fn (CompanyPermission $permission): string => $permission->value, $this->permissions());
    }

    /** The preset whose role and permissions are exactly these, or null for a custom set. */
    public static function matching(CompanyRole $role, array $permissions): ?self
    {
        foreach (self::cases() as $preset) {
            if ($preset->role() !== $role) {
                continue;
            }

            $names = $preset->permissionNames();
            $held = $permissions;
            sort($names);
            sort($held);

            if ($role === CompanyRole::CompanyAdmin || $names === $held) {
                return $preset;
            }
        }

        return null;
    }
}
