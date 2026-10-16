<?php

namespace Src\Shared\Application\Company;

/**
 * What a company member may do, one permission per feature (decided 2026-10-13, `docs/design/phase-8-company-permissions.md`). The catalog is code, not a
 * table a company edits. A company admin holds every permission; a member holds the ones checked for them. Team management is not in the catalog:
 * it stays with company admins, so no member can raise their own permissions.
 */
enum CompanyPermission: string
{
    case JobsCreate = 'jobs.create';
    case JobsPublish = 'jobs.publish';
    case JobsAssign = 'jobs.assign';
    case JobsViewAll = 'jobs.view_all';
    case ApplicationsView = 'applications.view';
    case ApplicationsManage = 'applications.manage';
    case ApplicationsReview = 'applications.review';
    case ApplicationsExport = 'applications.export';
    case InterviewsManage = 'interviews.manage';
    case OffersManage = 'offers.manage';
    case AssessmentsSend = 'assessments.send';
    case CandidatesSearch = 'candidates.search';
    case TalentPoolManage = 'talent_pool.manage';
    case MessagesSend = 'messages.send';
    case InterviewKitsManage = 'interview_kits.manage';
    case ScreeningQuestionsManage = 'screening_questions.manage';
    case AssessmentsAuthor = 'assessments.author';
    case StagesManage = 'stages.manage';
    case CompanyProfile = 'company.profile';
    case CampaignsManage = 'campaigns.manage';
    case ReviewsReply = 'reviews.reply';
    case AnalyticsView = 'analytics.view';
    case ApprovalsManage = 'approvals.manage';
    case WorkforcePlansManage = 'workforce.plans.manage';
    case WorkforcePlansView = 'workforce.plans.view';
    case WorkforceCostsView = 'workforce.costs.view';
    case WorkforceRequisitionsCreate = 'workforce.requisitions.create';
    case WorkforceRequisitionsView = 'workforce.requisitions.view';

    /** The group the permission is listed under in the invitation and edit dialogs. */
    public function group(): string
    {
        return match ($this) {
            self::JobsCreate, self::JobsPublish, self::JobsAssign, self::JobsViewAll => 'jobs',
            self::ApplicationsView, self::ApplicationsManage, self::ApplicationsReview, self::ApplicationsExport, self::InterviewsManage, self::OffersManage,
            self::AssessmentsSend => 'applicants',
            self::CandidatesSearch, self::TalentPoolManage, self::MessagesSend => 'sourcing',
            self::InterviewKitsManage, self::ScreeningQuestionsManage, self::AssessmentsAuthor, self::StagesManage => 'library',
            self::CompanyProfile, self::CampaignsManage, self::ReviewsReply, self::AnalyticsView => 'company',
            self::ApprovalsManage, self::WorkforcePlansManage, self::WorkforcePlansView, self::WorkforceCostsView, self::WorkforceRequisitionsCreate,
            self::WorkforceRequisitionsView => 'workforce',
        };
    }

    /** Whether holding the permission needs `applications.view` too: every action on jobs and applicants starts with reading them (rule 3). */
    public function requiresView(): bool
    {
        return match ($this) {
            self::JobsCreate, self::JobsPublish, self::JobsAssign, self::JobsViewAll, self::ApplicationsManage, self::ApplicationsReview, self::ApplicationsExport,
            self::InterviewsManage, self::OffersManage, self::AssessmentsSend => true,
            default => false,
        };
    }

    /** Whether holding the permission needs `applications.review` too: managing applicants includes rating and noting them (`phase-8-hiring-managers.md` section 1). */
    public function requiresReview(): bool
    {
        return $this === self::ApplicationsManage;
    }

    /** @return list<string> every permission name, in catalog order */
    public static function values(): array
    {
        return array_map(static fn (self $permission): string => $permission->value, self::cases());
    }
}
