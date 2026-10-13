<?php

namespace Src\LegacyImport\Application;

/** The masters whose legacy ids are mapped to the new masters. The keys are the names used in the mapping file and in the report. */
final class MasterCatalog
{
    /** @return list<MasterSpec> */
    public static function all(): array
    {
        return [
            new MasterSpec('job_categories', 'job_categories', 'job_category_translations', 'job_category_id', 'reference_job_categories', createsMissing: true),
            new MasterSpec('job_roles', 'job_roles', 'job_role_translations', 'job_role_id', 'reference_job_roles', createsMissing: true),
            new MasterSpec('job_types', 'job_types', 'job_type_translations', 'job_type_id', 'reference_job_types'),
            new MasterSpec('job_levels', 'job_levels', 'job_level_translations', 'job_level_id', 'reference_job_levels'),
            new MasterSpec('work_times', 'job_work_times', 'job_work_time_translations', 'job_work_time_id', 'reference_work_times'),
            new MasterSpec('experience_levels', 'experiences', 'experience_translations', 'experience_id', 'reference_experience_levels'),
            new MasterSpec('education_levels', 'education', 'education_translations', 'education_id', 'reference_education_levels'),
            new MasterSpec('salary_periods', 'salary_types', 'salary_type_translations', 'salary_type_id', 'reference_salary_periods'),
            new MasterSpec('industries', 'industry_types', 'industry_type_translations', 'industry_type_id', 'reference_industries'),
            new MasterSpec('organization_types', 'organization_types', 'organization_type_translations', 'organization_type_id', 'reference_organization_types'),
            new MasterSpec('team_sizes', 'team_sizes', 'team_size_translations', 'team_size_id', 'reference_team_sizes'),
            new MasterSpec('religions', 'religions', 'religion_translations', 'religion_id', 'reference_religions'),
            new MasterSpec('skills', 'skills', 'skill_translations', 'skill_id', 'reference_skills', createsMissing: true),
            new MasterSpec('benefits', 'benefits', 'benefit_translations', 'benefit_id', 'reference_benefits'),
            new MasterSpec('languages', 'candidate_languages', null, null, 'reference_languages', createsMissing: true, needsCode: true),
        ];
    }

    /** The id-map entity under which the legacy ids of a master are remembered. */
    public static function entityFor(string $masterKey): string
    {
        return 'master.'.$masterKey;
    }
}
