<?php

namespace Tests\Feature\Database\Schema;

use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\FeatureTestCase;

/**
 * The contract of the cross-context table reads (`docs/design/architecture.md` section 2.11). A reader outside the owning context may query a table of another
 * context only when it is listed here with the columns it uses: a schema change that drops one of them fails this test instead of a query at run time, and a
 * new unlisted read fails the scan below until it is listed here and in the document.
 */
final class CrossContextReadContractTest extends FeatureTestCase
{
    /** The table prefix of each context that owns tables; a context missing here owns none. */
    private const PREFIXES = [
        'Campaign' => 'campaign', 'Company' => 'company', 'Content' => 'content', 'Identity' => 'identity', 'Job' => 'job', 'JobAlert' => 'jobalert',
        'JobInvitation' => 'jobinvitation', 'JobSeeker' => 'jobseeker', 'Matching' => 'matching', 'Messaging' => 'messaging', 'Newsletter' => 'newsletter',
        'Notification' => 'notification', 'Platform' => 'platform', 'Recruitment' => 'recruitment', 'ReferenceData' => 'reference', 'Review' => 'review',
        'TalentPool' => 'talentpool',
    ];

    /** @var array<string, array<string, list<string>>> the file under `src/`, then the foreign table and the columns it reads */
    private const READS = [
        'Campaign/Infrastructure/Persistence/DatabaseCampaignStatistics.php' => [
            'identity_users' => ['id', 'created_at', 'registration_campaign_code'],
            'recruitment_applications' => ['id', 'user_id', 'company_id'],
        ],
        'Job/Infrastructure/Persistence/DatabaseJobReader.php' => [
            'recruitment_applications' => ['id', 'job_id'],
        ],
        'Job/Infrastructure/Persistence/DatabaseJobSignalReader.php' => [
            'reference_job_roles' => ['id', 'name'],
            'reference_experience_levels' => ['id', 'slug', 'min_years', 'max_years'],
            'reference_education_levels' => ['id', 'rank'],
        ],
        'Job/Infrastructure/Tasks/JobsWithoutApplicantsTask.php' => [
            'recruitment_applications' => ['id', 'job_id'],
        ],
        'JobSeeker/Infrastructure/Persistence/CompleteProfiles.php' => [
            'identity_users' => ['id', 'type', 'status', 'anonymized_at'],
        ],
        'JobSeeker/Infrastructure/Persistence/DatabaseAccountPictures.php' => [
            'identity_users' => ['id', 'avatar_path'],
        ],
        'JobSeeker/Infrastructure/Persistence/DatabaseCandidateReader.php' => [
            'identity_users' => ['id', 'name', 'avatar_path', 'anonymized_at', 'status'],
            'reference_education_levels' => ['id', 'rank'],
        ],
        'JobSeeker/Infrastructure/Persistence/DatabaseCandidateSignalSource.php' => [
            'reference_education_levels' => ['id', 'rank'],
        ],
    ];

    /** @return array<string, array{string, string, list<string>}> */
    public static function reads(): array
    {
        $cases = [];

        foreach (self::READS as $file => $tables) {
            foreach ($tables as $table => $columns) {
                $cases["$file reads $table"] = [$file, $table, $columns];
            }
        }

        return $cases;
    }

    /** @param  list<string>  $columns */
    #[DataProvider('reads')]
    public function test_a_cross_context_read_finds_its_table_and_columns(string $file, string $table, array $columns): void
    {
        $this->assertTrue(Schema::hasTable($table), "$file reads $table, which does not exist.");
        $this->assertTrue(Schema::hasColumns($table, $columns), "$file reads columns of $table that no longer exist: ".implode(', ', $columns));
    }

    public function test_every_cross_context_read_in_the_source_is_listed(): void
    {
        $found = [];

        foreach (self::sourceFiles() as $path => $file) {
            $own = self::PREFIXES[explode('/', $file)[0]] ?? null;

            foreach ($this->tablesOf($path) as $table) {
                if ($own === null || ! str_starts_with($table, $own.'_')) {
                    $found[$file][] = $table;
                }
            }
        }

        $actual = array_map(fn (array $tables): array => $this->sorted(array_unique($tables)), $found);
        $expected = array_map(fn (array $tables): array => $this->sorted(array_keys($tables)), self::READS);

        ksort($actual);
        ksort($expected);

        $this->assertSame($expected, $actual, 'The cross-context reads in src/ differ from the contract: list each one here and in docs/design/architecture.md section 2.11.');
    }

    /**
     * @param  array<int, string>  $values
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }

    /** @return array<string, string> the absolute path of each file under `src/` (the legacy import excluded), keyed by its path under `src/` */
    private static function sourceFiles(): array
    {
        $files = [];
        $root = base_path('src');

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $item) {
            $relative = substr($item->getPathname(), strlen($root) + 1);

            // The Domain layer reads no table: a quoted word there (an enum value such as `company_members`) is a label, not a read.
            if ($item->getExtension() === 'php' && ! str_starts_with($relative, 'LegacyImport/') && ! str_contains($relative, '/Domain/')) {
                $files[$item->getPathname()] = $relative;
            }
        }

        return $files;
    }

    /** @return list<string> the context tables named in a quoted string (`'identity_users as u'`, `'from identity_users'`); a quoted name used as an array key is a label, not a read */
    private function tablesOf(string $path): array
    {
        $tables = array_values(array_filter(Schema::getTableListing(schemaQualified: false), fn (string $table): bool => $this->isContextTable($table)));

        if ($tables === []) {
            return [];
        }

        $alternatives = implode('|', array_map(fn (string $table): string => preg_quote($table, '/'), $tables));
        preg_match_all('/[\'"](?:[^\'"\n]*\b(?:from|join)\s+)?('.$alternatives.')(?:\s+(?:as\s+)?\w+)?[\'"](?!\s*=>)/i', (string) file_get_contents($path), $matches);

        return $matches[1];
    }

    private function isContextTable(string $table): bool
    {
        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($table, $prefix.'_')) {
                return true;
            }
        }

        return false;
    }
}
