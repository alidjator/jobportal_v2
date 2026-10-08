<?php

namespace Tests\Feature\LegacyImport;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesReferenceRows;
use Tests\Concerns\UsesLegacyDatabase;
use Tests\FeatureTestCase;

final class LegacyImportCommandTest extends FeatureTestCase
{
    use CreatesReferenceRows;
    use UsesLegacyDatabase;

    private int $fullTime;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->setUpLegacyDatabase();

        $this->fullTime = (int) DB::table('reference_job_types')->insertGetId(['name' => json_encode(['id' => 'Penuh Waktu', 'en' => 'Full Time']), 'slug' => 'full-time', 'sort_order' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->legacyMasterItem('job_types', 1, ['en' => 'Full Time']);
        $this->legacyMasterItem('job_types', 2, ['en' => 'Freelance Gig', 'id' => 'Lepas']);
    }

    /** @return array<string, mixed> the report written by the last run */
    private function report(): array
    {
        $files = Storage::disk('local')->allFiles('legacy-import');
        $this->assertCount(1, $files);

        return json_decode((string) Storage::disk('local')->get($files[0]), true);
    }

    // ----- a run is a dry run unless --write is given -----

    public function test_by_default_a_run_reads_and_reports_but_writes_nothing(): void
    {
        $this->artisan('legacy:import', ['entity' => 'masters'])
            ->expectsOutputToContain('master.job_types: read 2, imported 1, skipped 1, unmatched 1')
            ->expectsOutputToContain('Dry run: nothing was written')
            ->assertSuccessful();

        $this->assertSame(0, DB::table('legacyimport_id_map')->count());

        $report = $this->report();
        $this->assertSame('dry-run', $report['mode']);
        $this->assertSame(['read' => 2, 'imported' => 1, 'skipped' => 1], array_intersect_key($report['entities']['master.job_types'], array_flip(['read', 'imported', 'skipped'])));
        $this->assertSame([['legacy_id' => 2, 'names' => ['Freelance Gig', 'Lepas'], 'reason' => 'no-match']], $report['entities']['master.job_types']['unmatched']);
    }

    public function test_write_remembers_the_matched_masters_in_the_id_map(): void
    {
        $this->artisan('legacy:import', ['entity' => 'masters', '--write' => true])->assertSuccessful();

        $this->assertSame([['entity' => 'master.job_types', 'legacy_id' => 1, 'new_id' => $this->fullTime]], DB::table('legacyimport_id_map')->get(['entity', 'legacy_id', 'new_id'])->map(fn ($row): array => (array) $row)->all());
        $this->assertSame('write', $this->report()['mode']);
    }

    public function test_a_corrected_mapping_file_takes_effect_at_the_next_run(): void
    {
        $this->artisan('legacy:import', ['entity' => 'masters', '--write' => true])->assertSuccessful();

        $path = tempnam(sys_get_temp_dir(), 'map');
        file_put_contents($path, json_encode(['job_types' => ['2' => 'full-time']]));

        try {
            Storage::disk('local')->deleteDirectory('legacy-import');
            $this->artisan('legacy:import', ['entity' => 'masters', '--write' => true, '--map' => ['masters='.$path]])->assertSuccessful();
        } finally {
            unlink($path);
        }

        $this->assertSame([1 => $this->fullTime, 2 => $this->fullTime], DB::table('legacyimport_id_map')->where('entity', 'master.job_types')->pluck('new_id', 'legacy_id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame(0, $this->report()['entities']['master.job_types']['skipped']);
    }

    public function test_running_it_again_does_not_duplicate_anything(): void
    {
        $this->artisan('legacy:import', ['entity' => 'masters', '--write' => true])->assertSuccessful();
        $this->artisan('legacy:import', ['entity' => 'masters', '--write' => true])->assertSuccessful();

        $this->assertSame(1, DB::table('legacyimport_id_map')->count());
    }

    public function test_the_masters_check_command_is_read_only_and_lists_what_did_not_match(): void
    {
        $this->artisan('legacy:masters:check')->expectsOutputToContain('unmatched 1')->assertSuccessful();

        $this->assertSame(0, DB::table('legacyimport_id_map')->count());
    }

    public function test_the_report_holds_no_row_content_only_ids_and_reasons(): void
    {
        $this->artisan('legacy:import', ['--all' => true])->assertSuccessful();

        $skipped = $this->report()['entities']['master.job_types']['skipped_rows'];

        $this->assertSame([['legacy_id' => 2, 'reason' => 'no-match']], $skipped);
    }

    // ----- the arguments -----

    public function test_it_needs_one_entity_or_all_but_not_both(): void
    {
        $this->artisan('legacy:import')->expectsOutputToContain('Name one entity')->assertFailed();
        $this->artisan('legacy:import', ['entity' => 'masters', '--all' => true])->expectsOutputToContain('Name one entity')->assertFailed();
    }

    public function test_an_unknown_entity_is_refused_with_the_list_of_known_ones(): void
    {
        $this->artisan('legacy:import', ['entity' => 'nothing'])->expectsOutputToContain('Unknown entity "nothing". Known: masters')->assertFailed();
    }

    public function test_a_bad_mapping_file_is_refused(): void
    {
        $this->artisan('legacy:import', ['entity' => 'masters', '--map' => ['masters=/no/such/file.json']])->expectsOutputToContain('must be name=path to an existing file')->assertFailed();

        $path = tempnam(sys_get_temp_dir(), 'map');
        file_put_contents($path, 'not json');

        try {
            $this->artisan('legacy:import', ['entity' => 'masters', '--map' => ['masters='.$path]])->expectsOutputToContain('is not a JSON object')->assertFailed();
        } finally {
            unlink($path);
        }
    }

    public function test_a_group_mapping_with_a_duplicate_key_or_an_invalid_entry_is_refused_before_the_run(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'groups');

        try {
            file_put_contents($path, '{"142": {"stage": "interview"}, "142": {"stage": "offer"}}');
            $this->artisan('legacy:import', ['entity' => 'applications', '--write' => true, '--map' => ['groups='.$path]])->expectsOutputToContain('"142" more than once')->assertFailed();

            file_put_contents($path, '{"142": {"stage": "dinner"}}');
            $this->artisan('legacy:import', ['entity' => 'applications', '--write' => true, '--map' => ['groups='.$path]])->expectsOutputToContain('Group mapping entry "142"')->assertFailed();
        } finally {
            unlink($path);
        }

        $this->assertSame(0, DB::table('legacyimport_id_map')->count());
    }

    // ----- the connection -----

    public function test_a_legacy_connection_that_is_not_configured_is_refused(): void
    {
        config(['database.connections.legacy.database' => '']);
        DB::purge('legacy');

        $this->artisan('legacy:import', ['entity' => 'masters'])->expectsOutputToContain('not configured')->assertFailed();
    }

    public function test_a_legacy_connection_that_points_at_the_application_database_is_refused(): void
    {
        config(['database.connections.legacy' => config('database.connections.'.config('database.default'))]);
        DB::purge('legacy');

        $this->artisan('legacy:import', ['entity' => 'masters'])->expectsOutputToContain('same database')->assertFailed();
        $this->assertSame(0, DB::table('legacyimport_id_map')->count());
    }
}
