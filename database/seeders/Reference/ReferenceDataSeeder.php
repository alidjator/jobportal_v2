<?php

namespace Database\Seeders\Reference;

use Illuminate\Database\Seeder;

/**
 * Seeds the initial values of every reference master that has any. The `reference_tags` table is
 * not seeded on purpose: tags are free keywords created while posting jobs. Location tables are not
 * seeded here either; their data source is decided separately.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            JobTypeSeeder::class,
            JobLevelSeeder::class,
            WorkTimeSeeder::class,
            ExperienceLevelSeeder::class,
            EducationLevelSeeder::class,
            SalaryPeriodSeeder::class,
            TeamSizeSeeder::class,
            ReligionSeeder::class,
            OrganizationTypeSeeder::class,
            LanguageSeeder::class,
            JobCategorySeeder::class,
            JobRoleSeeder::class,
            IndustrySeeder::class,
            SkillSeeder::class,
            NationalitySeeder::class,
            BenefitSeeder::class,
        ]);
    }
}
