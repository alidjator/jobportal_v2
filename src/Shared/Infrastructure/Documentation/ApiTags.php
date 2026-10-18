<?php

namespace Src\Shared\Infrastructure\Documentation;

use Dedoc\Scramble\Support\Generator\Tag;

/**
 * The groups of the API document, one per feature, in the order a reader sees them (public and job seeker features first, then the company side, then the admin
 * features, whose names start with `Admin: `). A route belongs to the first group with a pattern that matches its path (without the `api/v1/` prefix), so the
 * order of the entries is part of the rules. A new route that matches nothing falls into `Other` (a test in `ApiTagsTest` lists the routes in it).
 */
final class ApiTags
{
    public const string OTHER = 'Other';

    private const string ID = '\{[^}]+\}';

    /**
     * @return list<array{name: string, description: string, patterns: list<string>}>
     */
    public static function groups(): array
    {
        $id = self::ID;

        return [
            ['name' => 'Approvals', 'description' => 'The approval flows of a group (its default) and of an internal tenant (which replaces the default for itself), per subject type; the approvals that run through them, their decisions and the delegations.', 'patterns' => ['^organization/approval-flows', '^company/approval']],
            ['name' => 'Authentication', 'description' => 'Registering, signing in and out (job seekers, company members and admins), email verification, password reset, accepting a team invitation.', 'patterns' => ['^auth/', '^admin/auth/']],
            ['name' => 'Account', 'description' => 'The signed-in account: who am I, avatar, changing the password, the second factor of a company member, deleting the account, accepting the terms.', 'patterns' => ['^me$', '^me/avatar', '^me/password', '^me/deletion', '^me/terms-acceptance', '^company/me$', '^admin/me$', '^admin/me/(password|two-factor)', '^me/two-factor', '^me/data-export']],
            ['name' => 'Job seeker profile and CV', 'description' => 'The profile, experiences, educations, skills, languages, links, resumes and the generated CV.', 'patterns' => ['^me/(profile|experiences|educations|skills|languages|links|resumes|cv)']],
            ['name' => 'Reference data', 'description' => 'Public lists used by forms and filters: places, categories, roles, levels, skills, salary periods and the like.', 'patterns' => ['^(benefits|cities|education-levels|experience-levels|industries|job-categories|job-levels|job-roles|job-types|languages|nationalities|organization-types|postal-codes|provinces|religions|salary-periods|skills|subdistricts|tags|team-sizes|villages|work-times)$']],
            ['name' => 'Job search', 'description' => 'Finding and reading jobs, reporting a job, recommendations, salary insight, saved jobs and job alerts.', 'patterns' => ['^jobs$', "^jobs/{$id}$", "^jobs/{$id}/(view|screening-questions|reports|similar)$", '^salary-insight$', '^me/recommendations$', '^me/saved-jobs', '^me/job-alerts']],
            ['name' => 'Interviews and offers', 'description' => 'Interviews, interview kits and offers, from the company side and the job seeker side.', 'patterns' => ['^internal/janus/', '/(interview|interviews|offer|offers)(/|$)', '^company/interview-kits', "^company/jobs/{$id}/interview-kits"]],
            ['name' => 'Applications', 'description' => 'A job seeker applies, follows and withdraws applications.', 'patterns' => ["^jobs/{$id}/applications$", '^me/applications']],
            ['name' => 'Invitations to apply', 'description' => 'A company invites candidates to a job; the job seeker answers.', 'patterns' => ['^me/invitations', "^company/jobs/{$id}/invitations(/bulk)?$"]],
            ['name' => 'Chat', 'description' => 'Conversations, messages, attachments and the realtime channel authorisation.', 'patterns' => ['conversations', '/messages/', '^broadcasting/']],
            ['name' => 'Notifications', 'description' => 'In-app notifications, notification preferences and device tokens.', 'patterns' => ['^me/(notifications|notification-preferences|device-tokens)']],
            ['name' => 'Dashboards and analytics', 'description' => 'The dashboards and reports of job seekers and companies.', 'patterns' => ['^me/dashboard$', '^company/dashboard$', '^company/tasks$', '^company/calendar$', '^company/analytics/', "^company/jobs/{$id}/performance$"]],
            ['name' => 'Company page and team', 'description' => 'The public company page, the company profile, logo and banner, documents, members and team invitations, the company settings page, the default message texts, the company activity log and name change requests.', 'patterns' => ["^companies/{$id}(/(banner|logo))?$", '^company/(profile|slug|logo|banner|documents|members|invitations|permissions|team|settings|message-defaults|activity|name-change-requests)']],
            ['name' => 'Company jobs', 'description' => 'A company creates, publishes, changes and closes its jobs and assigns recruiters.', 'patterns' => ["^company/jobs(/{$id}(/(assignees|hiring-manager-boundary|clone|close|publish|reopen))?)?$"]],
            ['name' => 'Recruitment pipeline', 'description' => 'The applicants of a job, stages, moving and rejecting, notes, ratings, shortlist reviews by the hiring managers, screening questions, bulk actions and export.', 'patterns' => ['^company/(applications|stages|screening-questions|review-requests)', '^company/hires/export$', "^company/jobs/{$id}/(applications|screening-questions|review-requests|fill)"]],
            ['name' => 'Candidate search', 'description' => 'Companies search candidates who opted in, read their profiles and keep the ones they like in a talent pool.', 'patterns' => ['^company/candidates', '^company/talent-pool', "^company/jobs/{$id}/candidates$"]],
            ['name' => 'Company reviews', 'description' => 'Reviews of companies by job seekers who applied, the company reply and reports.', 'patterns' => ["^companies/{$id}/reviews$", '^me/reviews', '^reviews/', '^company/reviews']],
            ['name' => 'Campaigns', 'description' => 'Registration links and QR codes of a company, with their results.', 'patterns' => ['^campaigns/', '^company/campaigns']],
            ['name' => 'Newsletter', 'description' => 'Joining and leaving the public newsletter.', 'patterns' => ['^newsletter/']],
            ['name' => 'Training', 'description' => 'Training courses.', 'patterns' => ['training/']],
            ['name' => 'Legal and help', 'description' => 'Terms, privacy policy, the FAQ and the platform announcements.', 'patterns' => ['^faq$', '^legal-documents/', '^announcements$']],
            ['name' => 'Assessments', 'description' => 'Skill tests of a company, the catalogue of ready-made tests and granted psychological batteries, the tests sent to candidates, their results, the integrity log, the assessors\' scoring and the signed reports, and the tests a job seeker was asked to take with the explanation of a psychological result.', 'patterns' => ['^company/assessment/', '^me/assessment/']],
            ['name' => 'Psychologist portal', 'description' => 'Psychologist accounts of a partner provider: invitation, sign-in with a second factor, the batteries, norms and report templates they write, and the review, edit and signature of the reports of their provider.', 'patterns' => ['^provider/']],
            ['name' => 'Admin: Companies', 'description' => 'Company accounts, documents, document types, name change requests, settings and invitations.', 'patterns' => ['^admin/(companies|company-document-types|company-name-changes)']],
            ['name' => 'Organization', 'description' => 'The customer group of an ERP member: its legal entities and tenants, the group roles and the temporary support access it grants.', 'patterns' => ['^organization/']],
            ['name' => 'Workforce', 'description' => 'Manpower plans of a tenant for a fiscal year and the requisitions that open a vacancy against them, with their amendments and the jobs opened for them.', 'patterns' => ['^company/(workforce|requisitions)', '^organization/requisitions']],
            ['name' => 'Admin: Groups', 'description' => 'Customer groups of the ERP: creating, suspending, linking tenants to legal entities and giving group roles.', 'patterns' => ['^admin/groups']],
            ['name' => 'Admin: Group support', 'description' => 'Read-only access of a platform admin to a customer group, only while a group admin granted it: the groups that did, and the reads under a grant.', 'patterns' => ['^admin/support']],
            ['name' => 'Admin: Jobs', 'description' => 'Reviewing, approving and taking down jobs, and the reports about jobs.', 'patterns' => ['^admin/(jobs|job-reports)']],
            ['name' => 'Admin: Reviews', 'description' => 'Moderating reviews, replies and reports, and the reports about chat conversations.', 'patterns' => ['^admin/(review|message-reports)']],
            ['name' => 'Admin: Campaigns and newsletter', 'description' => 'Every campaign of the platform and the newsletter issues and subscribers.', 'patterns' => ['^admin/(campaigns|newsletter)']],
            ['name' => 'Admin: Content', 'description' => 'FAQ, legal documents, email templates, CV templates and announcements.', 'patterns' => ['^admin/(faqs|faq-categories|legal-documents|email-templates|cv-templates|announcements)']],
            ['name' => 'Admin: Platform', 'description' => 'The dashboard, analytics, settings, the audit log, the email suppression list and the accounts.', 'patterns' => ['^admin/(dashboard|analytics|settings|audit-logs|email-suppressions|users)']],
            ['name' => 'Admin: Assessments', 'description' => 'Partner providers and their psychologists, the system test library and the batteries granted to companies.', 'patterns' => ['^admin/assessment/']],
            ['name' => 'Operations', 'description' => 'Delivery webhooks of the email provider and the scheduler health check for an outside uptime monitor.', 'patterns' => ['^webhooks/', '^health/']],
            ['name' => 'Admin: Reference data', 'description' => 'The managed lists behind the public reference data.', 'patterns' => ['^admin/']],
        ];
    }

    /** The group of a route, from its path (with or without the `api/v1/` prefix). */
    public static function forPath(string $path): string
    {
        $path = ltrim(preg_replace('#^/?api/v1/#', '', ltrim($path, '/')) ?? $path, '/');

        foreach (self::groups() as $group) {
            foreach ($group['patterns'] as $pattern) {
                if (preg_match('#'.$pattern.'#', $path) === 1) {
                    return $group['name'];
                }
            }
        }

        return self::OTHER;
    }

    /** @return list<Tag> the groups in reading order, for the `tags` of the document */
    public static function tags(): array
    {
        return array_map(static fn (array $group): Tag => new Tag($group['name'], $group['description']), self::groups());
    }
}
