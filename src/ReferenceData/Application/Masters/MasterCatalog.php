<?php

namespace Src\ReferenceData\Application\Masters;

/** The 17 masters, in one place. Adding a master means adding a table and an entry here. */
final class MasterCatalog
{
    /** @var array<string, MasterDefinition>|null */
    private static ?array $definitions = null;

    /** @return array<string, MasterDefinition> keyed by URL name */
    public static function all(): array
    {
        return self::$definitions ??= self::build();
    }

    public static function find(string $key): ?MasterDefinition
    {
        return self::all()[$key] ?? null;
    }

    /** @return array<string, MasterDefinition> */
    private static function build(): array
    {
        $definitions = [
            new MasterDefinition('job-categories', 'reference_job_categories'),
            new MasterDefinition('job-roles', 'reference_job_roles', paginated: true),
            new MasterDefinition('job-types', 'reference_job_types'),
            new MasterDefinition('job-levels', 'reference_job_levels'),
            new MasterDefinition('work-times', 'reference_work_times'),
            new MasterDefinition('experience-levels', 'reference_experience_levels', [
                ExtraField::integer('min_years', required: false, max: 65_535),
                ExtraField::integer('max_years', required: false, max: 65_535),
            ]),
            new MasterDefinition('education-levels', 'reference_education_levels', [ExtraField::integer('rank', max: 65_535)]),
            new MasterDefinition('salary-periods', 'reference_salary_periods'),
            new MasterDefinition('skills', 'reference_skills', [ExtraField::choice('kind', ['hard', 'soft'])], paginated: true),
            // Tags are free keywords; their popularity and management come with the jobs in Phase 1.
            new MasterDefinition('tags', 'reference_tags', paginated: true, adminManaged: false),
            // Only approved benefits are global; company proposals (`pending`, `rejected`) are handled in Phase 1.
            new MasterDefinition('benefits', 'reference_benefits', fixedColumns: ['status' => 'active']),
            new MasterDefinition('industries', 'reference_industries'),
            new MasterDefinition('organization-types', 'reference_organization_types'),
            new MasterDefinition('team-sizes', 'reference_team_sizes', [
                ExtraField::integer('min_size', required: false),
                ExtraField::integer('max_size', required: false),
            ]),
            new MasterDefinition('religions', 'reference_religions'),
            new MasterDefinition('nationalities', 'reference_nationalities'),
            new MasterDefinition('languages', 'reference_languages', [ExtraField::text('code', 10, unique: true)]),
        ];

        $byKey = [];

        foreach ($definitions as $definition) {
            $byKey[$definition->key] = $definition;
        }

        return $byKey;
    }
}
