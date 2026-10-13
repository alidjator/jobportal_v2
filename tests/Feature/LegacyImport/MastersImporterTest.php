<?php

namespace Tests\Feature\LegacyImport;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\UsesLegacyDatabase;
use Tests\FeatureTestCase;

final class MastersImporterTest extends FeatureTestCase
{
    use UsesLegacyDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyDatabase();
    }

    private function newItem(string $table, string $slug, string $en, array $extra = []): int
    {
        return (int) DB::table($table)->insertGetId(['name' => json_encode(['id' => $en, 'en' => $en]), 'slug' => $slug, 'sort_order' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(), ...$extra]);
    }

    private function runImport(bool $write = true, ?array $mapping = null): void
    {
        $arguments = ['entity' => 'masters', '--write' => $write];

        if ($mapping !== null) {
            $path = tempnam(sys_get_temp_dir(), 'map');
            file_put_contents($path, json_encode($mapping));
            $arguments['--map'] = ['masters='.$path];
        }

        try {
            $this->artisan('legacy:import', $arguments)->assertSuccessful();
        } finally {
            isset($path) && unlink($path);
        }
    }

    /** @return array<int, int> new id by legacy id, from the id map */
    private function mapped(string $master): array
    {
        return DB::table('legacyimport_id_map')->where('entity', 'master.'.$master)->pluck('new_id', 'legacy_id')->map(fn ($id): int => (int) $id)->all();
    }

    /** @return array<string, mixed> */
    private function report(string $master): array
    {
        $files = Storage::disk('local')->allFiles('legacy-import');

        return json_decode((string) Storage::disk('local')->get(end($files)), true)['entities']['master.'.$master];
    }

    public function test_an_unmatched_skill_becomes_an_active_skill_with_the_same_name_in_both_languages(): void
    {
        $this->legacyMasterItem('skills', 7, ['en' => 'Laravel  Octane']);

        $this->runImport();

        $skill = DB::table('reference_skills')->first();
        $this->assertEquals(['id' => 'Laravel Octane', 'en' => 'Laravel Octane'], json_decode($skill->name, true));
        $this->assertSame(['laravel-octane', 'hard', true, 0], [$skill->slug, $skill->kind, (bool) $skill->is_active, $skill->sort_order]);
        $this->assertSame([7 => (int) $skill->id], $this->mapped('skills'));
    }

    public function test_the_kind_of_a_created_skill_is_guessed_from_its_name(): void
    {
        $this->legacyMasterItem('skills', 1, ['en' => 'Negotiation Skills']);
        $this->legacyMasterItem('skills', 2, ['en' => 'Forklift Operation']);

        $this->runImport();

        $this->assertSame(['forklift-operation' => 'hard', 'negotiation-skills' => 'soft'], DB::table('reference_skills')->orderBy('slug')->pluck('kind', 'slug')->all());
    }

    public function test_legacy_items_with_the_same_normalised_name_become_one_master(): void
    {
        $this->legacyMasterItem('skills', 1, ['en' => 'MySQL']);
        $this->legacyMasterItem('skills', 2, ['en' => 'mysql']);
        $this->legacyMasterItem('skills', 3, ['en' => 'MySQL Database']);

        $this->runImport();

        $this->assertSame(2, DB::table('reference_skills')->count());
        $mapped = $this->mapped('skills');
        $this->assertSame($mapped[1], $mapped[2]);
        $this->assertNotSame($mapped[1], $mapped[3]);
        $this->assertSame(['read' => 3, 'imported' => 3, 'skipped' => 0], array_intersect_key($this->report('skills'), array_flip(['read', 'imported', 'skipped'])));
        $this->assertSame(['created' => 2], $this->report('skills')['notes']);
    }

    public function test_an_item_that_matches_an_existing_master_is_not_created(): void
    {
        $existing = $this->newItem('reference_skills', 'python', 'Python', ['kind' => 'hard']);
        $this->legacyMasterItem('skills', 1, ['en' => 'python']);

        $this->runImport();

        $this->assertSame(1, DB::table('reference_skills')->count());
        $this->assertSame([1 => $existing], $this->mapped('skills'));
    }

    public function test_the_mapping_file_wins_over_creating(): void
    {
        $developer = $this->newItem('reference_job_roles', 'developer', 'Developer');
        $this->legacyMasterItem('job_roles', 26, ['en' => 'web developer']);

        $this->runImport(mapping: ['job_roles' => ['26' => 'developer']]);

        $this->assertSame(1, DB::table('reference_job_roles')->count());
        $this->assertSame([26 => $developer], $this->mapped('job_roles'));
    }

    public function test_an_item_that_matches_two_masters_or_names_an_unknown_slug_is_reported_not_created(): void
    {
        $this->newItem('reference_skills', 'sql-a', 'SQL', ['kind' => 'hard']);
        $this->newItem('reference_skills', 'sql-b', 'sql', ['kind' => 'hard']);
        $this->legacyMasterItem('skills', 1, ['en' => 'SQL']);
        $this->legacyMasterItem('skills', 2, ['en' => 'Excel']);

        $this->runImport(mapping: ['skills' => ['2' => 'no-such-slug']]);

        $this->assertSame(2, DB::table('reference_skills')->count());
        $this->assertSame([], $this->mapped('skills'));
        $this->assertSame(['ambiguous', 'override-slug-unknown'], collect($this->report('skills')['unmatched'])->pluck('reason')->all());
    }

    public function test_a_dry_run_reports_what_would_be_created_and_writes_nothing(): void
    {
        $this->legacyMasterItem('skills', 1, ['en' => 'Welding']);
        $this->legacyMasterItem('skills', 2, ['en' => 'welding']);

        $this->runImport(write: false);

        $this->assertSame(0, DB::table('reference_skills')->count());
        $this->assertSame(0, DB::table('legacyimport_id_map')->count());
        $report = $this->report('skills');
        $this->assertSame([2, 2, 0], [$report['read'], $report['imported'], $report['skipped']]);
        $this->assertSame(['created' => 1], $report['notes']);
    }

    public function test_a_second_run_creates_nothing_new(): void
    {
        $this->legacyMasterItem('skills', 1, ['en' => 'Welding']);
        $this->legacyMasterItem('job_roles', 1, ['en' => 'Welder']);

        $this->runImport();
        $first = [$this->mapped('skills'), $this->mapped('job_roles')];
        $this->runImport();

        $this->assertSame([1, 1], [DB::table('reference_skills')->count(), DB::table('reference_job_roles')->count()]);
        $this->assertSame($first, [$this->mapped('skills'), $this->mapped('job_roles')]);
    }

    public function test_job_roles_and_categories_are_created_but_a_category_has_no_image(): void
    {
        $this->legacyMasterItem('job_roles', 1, ['en' => 'DevOps']);
        $this->legacyMasterItem('job_categories', 1, ['en' => 'Internship']);

        $this->runImport();

        $role = DB::table('reference_job_roles')->first();
        $category = DB::table('reference_job_categories')->first();
        $this->assertSame(['devops', true], [$role->slug, (bool) $role->is_active]);
        $this->assertSame(['internship', true, null], [$category->slug, (bool) $category->is_active, $category->image_path]);
        $this->assertSame([1 => (int) $role->id], $this->mapped('job_roles'));
        $this->assertSame([1 => (int) $category->id], $this->mapped('job_categories'));
    }

    public function test_the_other_masters_are_never_created(): void
    {
        $this->legacyMasterItem('job_types', 1, ['en' => 'Freelance Gig']);
        $this->legacyMasterItem('industry_types', 1, ['en' => 'Space Mining']);

        $this->runImport();

        $this->assertSame(0, DB::table('reference_job_types')->count());
        $this->assertSame(0, DB::table('reference_industries')->count());
        $this->assertSame([], $this->mapped('job_types'));
        $this->assertSame('no-match', $this->report('industries')['unmatched'][0]['reason']);
    }

    public function test_a_taken_slug_is_numbered_and_a_long_name_gets_a_slug_within_the_limit(): void
    {
        $this->newItem('reference_skills', 'c', 'C Language', ['kind' => 'hard']);
        $this->legacyMasterItem('skills', 1, ['en' => 'C#']);
        $this->legacyMasterItem('skills', 2, ['en' => str_repeat('Long ', 50)]);

        $this->runImport();

        $created = DB::table('reference_skills')->where('slug', '!=', 'c')->get();
        $this->assertContains('c-2', $created->pluck('slug')->all());
        $long = $created->first(fn ($row): bool => str_starts_with($row->slug, 'long'));
        $this->assertLessThanOrEqual(150, strlen($long->slug));
    }
}
