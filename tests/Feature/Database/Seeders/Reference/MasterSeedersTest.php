<?php

namespace Tests\Feature\Database\Seeders\Reference;

use Database\Seeders\Reference\BenefitSeeder;
use Database\Seeders\Reference\EducationLevelSeeder;
use Database\Seeders\Reference\ExperienceLevelSeeder;
use Database\Seeders\Reference\IndustrySeeder;
use Database\Seeders\Reference\JobCategorySeeder;
use Database\Seeders\Reference\JobLevelSeeder;
use Database\Seeders\Reference\JobRoleSeeder;
use Database\Seeders\Reference\JobTypeSeeder;
use Database\Seeders\Reference\LanguageSeeder;
use Database\Seeders\Reference\NationalitySeeder;
use Database\Seeders\Reference\OrganizationTypeSeeder;
use Database\Seeders\Reference\ReligionSeeder;
use Database\Seeders\Reference\SalaryPeriodSeeder;
use Database\Seeders\Reference\SkillSeeder;
use Database\Seeders\Reference\TeamSizeSeeder;
use Database\Seeders\Reference\WorkTimeSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\FeatureTestCase;

final class MasterSeedersTest extends FeatureTestCase
{
    /**
     * @return array<string, array{0: class-string, 1: string, 2: list<string>, 3: array{id: string, en: string}}>
     */
    public static function seeders(): array
    {
        return [
            'job types' => [
                JobTypeSeeder::class,
                'reference_job_types',
                ['contract', 'permanent', 'internship', 'freelance'],
                ['id' => 'Kontrak', 'en' => 'Contract'],
            ],
            'job levels' => [
                JobLevelSeeder::class,
                'reference_job_levels',
                ['junior', 'middle', 'senior'],
                ['id' => 'Junior', 'en' => 'Junior'],
            ],
            'work times' => [
                WorkTimeSeeder::class,
                'reference_work_times',
                ['full-time', 'part-time', 'shift'],
                ['id' => 'Penuh Waktu', 'en' => 'Full Time'],
            ],
            'experience levels' => [
                ExperienceLevelSeeder::class,
                'reference_experience_levels',
                ['fresh-graduated', 'less-than-a-year', '1-3-years', '3-5-years', '5-10-years', '10-years'],
                ['id' => 'Lulusan Baru', 'en' => 'Fresh Graduated'],
            ],
            'education levels' => [
                EducationLevelSeeder::class,
                'reference_education_levels',
                ['s2', 's1', 'd4', 'd3', 'sma'],
                ['id' => 'S2 (Magister)', 'en' => 'S2'],
            ],
            'salary periods' => [
                SalaryPeriodSeeder::class,
                'reference_salary_periods',
                ['monthly', 'hourly', 'project-base'],
                ['id' => 'Bulanan', 'en' => 'Monthly'],
            ],
            'team sizes' => [
                TeamSizeSeeder::class,
                'reference_team_sizes',
                ['only-me', '10-members', '10-20-members', '20-50-members', '50-100-members', '100-200-members', '200-500-members', '500-members'],
                ['id' => 'Hanya Saya', 'en' => 'Only Me'],
            ],
            'religions' => [
                ReligionSeeder::class,
                'reference_religions',
                ['islam', 'catholic', 'protestant', 'buddhism', 'hindu', 'confucianism'],
                ['id' => 'Islam', 'en' => 'Islam'],
            ],
            'organization types' => [
                OrganizationTypeSeeder::class,
                'reference_organization_types',
                ['government', 'semi-government', 'public', 'private', 'ngo', 'international-agencies'],
                ['id' => 'Pemerintah', 'en' => 'Government'],
            ],
            'benefits' => [
                BenefitSeeder::class,
                'reference_benefits',
                [
                    'bpjs-health-insurance', 'bpjs-employment', 'religious-holiday-allowance-thr', 'annual-bonus', 'private-health-insurance',
                    'annual-leave', 'transportation-allowance', 'meal-allowance', 'training-development', 'flexible-working-hours',
                    'remote-work-option', 'overtime-pay',
                ],
                ['id' => 'BPJS Kesehatan', 'en' => 'BPJS Health Insurance'],
            ],
            'nationalities' => [
                NationalitySeeder::class,
                'reference_nationalities',
                [
                    'indonesian', 'malaysian', 'singaporean', 'filipino', 'thai', 'vietnamese', 'bruneian', 'timorese', 'chinese', 'japanese',
                    'south-korean', 'indian', 'pakistani', 'bangladeshi', 'australian', 'american', 'british', 'canadian', 'german', 'french',
                    'dutch', 'saudi-arabian', 'emirati', 'turkish', 'russian',
                ],
                ['id' => 'Indonesia', 'en' => 'Indonesian'],
            ],
            'skills' => [
                SkillSeeder::class,
                'reference_skills',
                [
                    'python', 'it-support', 'hardware-software-troubleshooting', 'networking', 'ms-project', 'access-control', 'audio-visual', 'autocad',
                    'data-center', 'interior-design', 'fire-alarm', 'mechanical-electrical', 'sketchup', 'smart-building', 'problem-solving', 'communication',
                    'teamwork', 'analytical-thinking', 'figma', 'drawio', 'microsoft-sql', 'sql', 'excel', 'microsoft-office', 'data-visualization',
                    'tableau', 'power-bi', 'r-programming', 'google-looker-studio',
                ],
                ['id' => 'Python', 'en' => 'Python'],
            ],
            'industries' => [
                IndustrySeeder::class,
                'reference_industries',
                [
                    'agro-based-industry', 'architecture-engineering-construction', 'automobile-industrial-machinery', 'banks-non-bank-financial-institutions',
                    'electronics-consumer-durables', 'energy-power-fuel', 'garments-textiles', 'government-semi-government-autonomous-bodies',
                    'pharmaceuticals', 'hospitals-diagnostic-centers', 'airlines-travel-tourism', 'manufacturing-light-industry',
                    'manufacturing-heavy-industry', 'hotels-restaurants', 'information-technology', 'logistics-transportation',
                    'entertainment-recreation', 'media-advertising-event-management', 'ngos-development', 'real-estate-development',
                    'wholesale-retail-export-import', 'telecommunications', 'food-beverage-industry', 'security-services', 'fire-safety-protection',
                ],
                ['id' => 'Industri Berbasis Agro', 'en' => 'Agro-Based Industry'],
            ],
            'job roles' => [
                JobRoleSeeder::class,
                'reference_job_roles',
                [
                    'account-manager', 'accounting', 'application-support', 'business-analyst', 'database-administrator', 'developer', 'driver', 'finance',
                    'general-affair', 'infrastructure', 'network-administrator', 'office-assistant', 'presales-infrastructure', 'president-director',
                    'department-head', 'project-admin', 'project-manager', 'quality-assurance', 'sales-executive', 'server-administrator', 'service-desk',
                    'system-administrator', 'system-analyst', 'uiux-designer',
                ],
                ['id' => 'Account Manager', 'en' => 'Account Manager'],
            ],
            'job categories' => [
                JobCategorySeeder::class,
                'reference_job_categories',
                [
                    'management', 'finance-accounting', 'information-technology', 'operational-administration',
                    'infrastructure-network', 'sales-marketing', 'project-management', 'other',
                ],
                ['id' => 'Manajemen dan Kepemimpinan', 'en' => 'Management'],
            ],
            'languages' => [
                LanguageSeeder::class,
                'reference_languages',
                [
                    'indonesian', 'english', 'chinese', 'arabic', 'japanese', 'korean', 'javanese', 'sundanese', 'malay', 'french', 'german', 'dutch', 'spanish',
                    'portuguese', 'russian', 'italian', 'hindi', 'tamil', 'thai', 'vietnamese', 'turkish', 'urdu', 'bengali', 'tagalog', 'persian',
                ],
                ['id' => 'Indonesia', 'en' => 'Indonesian'],
            ],
        ];
    }

    /**
     * The same data sets as `seeders()` without the expected first name.
     *
     * @return array<string, array{0: class-string, 1: string, 2: list<string>}>
     */
    public static function seedersWithoutNames(): array
    {
        return array_map(fn (array $set): array => array_slice($set, 0, 3), self::seeders());
    }

    /**
     * @param  class-string  $seeder
     * @param  list<string>  $slugs
     * @param  array{id: string, en: string}  $firstName
     */
    #[DataProvider('seeders')]
    public function test_it_seeds_the_rows_in_order_with_both_languages(string $seeder, string $table, array $slugs, array $firstName): void
    {
        $this->seed($seeder);

        $rows = DB::table($table)->orderBy('sort_order')->get();

        $this->assertSame($slugs, $rows->pluck('slug')->all());
        $this->assertSame($firstName, json_decode($rows->first()->name, true));
        $this->assertSame(range(1, count($slugs)), $rows->pluck('sort_order')->map(fn ($order) => (int) $order)->all());
    }

    public function test_experience_levels_cover_every_year_count_without_gaps(): void
    {
        $this->seed(ExperienceLevelSeeder::class);

        $ranges = DB::table('reference_experience_levels')->orderBy('sort_order')->get(['min_years', 'max_years']);

        $this->assertSame(
            [[0, 1], [0, 1], [1, 3], [3, 5], [5, 10], [10, null]],
            $ranges->map(fn ($row) => [(int) $row->min_years, $row->max_years === null ? null : (int) $row->max_years])->all(),
        );
    }

    public function test_seeded_benefits_are_approved_global_benefits(): void
    {
        $this->seed(BenefitSeeder::class);

        $this->assertSame(12, DB::table('reference_benefits')->where('status', 'active')->whereNull('proposed_by_company_id')->count());
        $this->assertSame(12, DB::table('reference_benefits')->count());
    }

    public function test_skills_are_classified_as_hard_or_soft(): void
    {
        $this->seed(SkillSeeder::class);

        $kinds = DB::table('reference_skills')->pluck('kind', 'slug');

        $this->assertSame(['problem-solving', 'communication', 'teamwork', 'analytical-thinking'], $kinds->filter(fn ($kind) => $kind === 'soft')->keys()->all());
        $this->assertSame(25, $kinds->filter(fn ($kind) => $kind === 'hard')->count());
    }

    public function test_language_codes_are_unique_two_letter_codes(): void
    {
        $this->seed(LanguageSeeder::class);

        $codes = DB::table('reference_languages')->pluck('code')->all();

        $this->assertSame($codes, array_values(array_unique($codes)));

        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[a-z]{2}$/', $code);
        }
    }

    public function test_team_size_ranges_join_without_gaps_or_overlaps(): void
    {
        $this->seed(TeamSizeSeeder::class);

        $ranges = DB::table('reference_team_sizes')->orderBy('sort_order')->get(['min_size', 'max_size']);

        foreach ($ranges->slice(0, -1)->values() as $index => $range) {
            $this->assertSame((int) $range->max_size, (int) $ranges[$index + 1]->min_size);
        }

        $this->assertNull($ranges->last()->max_size);
    }

    public function test_education_levels_rank_bachelor_degrees_equally(): void
    {
        $this->seed(EducationLevelSeeder::class);

        $ranks = DB::table('reference_education_levels')->pluck('rank', 'slug')->map(fn ($rank) => (int) $rank)->all();

        $this->assertSame(['s2' => 4, 's1' => 3, 'd4' => 3, 'd3' => 2, 'sma' => 1], $ranks);
    }

    /**
     * @param  class-string  $seeder
     * @param  list<string>  $slugs
     */
    #[DataProvider('seedersWithoutNames')]
    public function test_running_it_again_keeps_existing_rows_untouched(string $seeder, string $table, array $slugs): void
    {
        $this->seed($seeder);
        DB::table($table)->where('slug', $slugs[0])->update(['is_active' => false]);

        $this->seed($seeder);

        $this->assertSame(count($slugs), DB::table($table)->count());
        $this->assertSame(0, (int) DB::table($table)->where('slug', $slugs[0])->value('is_active'));
    }
}
