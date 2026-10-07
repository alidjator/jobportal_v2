<?php

namespace Tests\Feature\LegacyImport;

use Illuminate\Support\Facades\DB;
use Src\LegacyImport\Application\MasterCatalog;
use Src\LegacyImport\Application\MasterMatcher;
use Src\LegacyImport\Application\MasterSpec;
use Tests\Concerns\CreatesReferenceRows;
use Tests\Concerns\UsesLegacyDatabase;
use Tests\FeatureTestCase;

final class MasterMatcherTest extends FeatureTestCase
{
    use CreatesReferenceRows;
    use UsesLegacyDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyDatabase();
    }

    private function spec(string $key): MasterSpec
    {
        return collect(MasterCatalog::all())->first(fn (MasterSpec $spec): bool => $spec->key === $key);
    }

    /** A new master item with its names in both languages. */
    private function newItem(string $table, string $slug, string $id, string $en, array $extra = []): int
    {
        return (int) DB::table($table)->insertGetId(['name' => json_encode(['id' => $id, 'en' => $en]), 'slug' => $slug, 'sort_order' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(), ...$extra]);
    }

    private function matcher(): MasterMatcher
    {
        return $this->app->make(MasterMatcher::class);
    }

    public function test_an_item_matches_by_its_english_or_its_indonesian_name(): void
    {
        $fullTime = $this->newItem('reference_job_types', 'full-time', 'Penuh Waktu', 'Full Time');
        $contract = $this->newItem('reference_job_types', 'contract', 'Kontrak', 'Contract');
        $this->legacyMasterItem('job_types', 1, ['en' => 'Full Time']);
        $this->legacyMasterItem('job_types', 2, ['id' => 'Kontrak']);

        $match = $this->matcher()->match($this->spec('job_types'));

        $this->assertSame([1 => $fullTime, 2 => $contract], $match->matched);
        $this->assertSame([], $match->unmatched);
    }

    public function test_letter_case_spacing_punctuation_and_ampersands_do_not_matter(): void
    {
        $support = $this->newItem('reference_skills', 'it-support', 'Dukungan TI', 'Hardware & Software Troubleshooting', ['kind' => 'hard']);
        $this->legacyMasterItem('skills', 5, ['en' => 'hardware  and software-troubleshooting']);

        $this->assertSame([5 => $support], $this->matcher()->match($this->spec('skills'))->matched);
    }

    public function test_an_item_with_several_languages_matches_through_any_of_them(): void
    {
        $senior = $this->newItem('reference_job_levels', 'senior', 'Senior', 'Senior Level');
        $this->legacyMasterItem('job_levels', 3, ['en' => 'Something else', 'id' => 'Senior Level']);

        $this->assertSame([3 => $senior], $this->matcher()->match($this->spec('job_levels'))->matched);
    }

    public function test_an_item_with_no_counterpart_is_listed_by_name(): void
    {
        $this->newItem('reference_job_types', 'full-time', 'Penuh Waktu', 'Full Time');
        $this->legacyMasterItem('job_types', 1, ['en' => 'Freelance Gig', 'id' => 'Lepas']);

        $match = $this->matcher()->match($this->spec('job_types'));

        $this->assertSame([], $match->matched);
        $this->assertSame([['legacy_id' => 1, 'names' => ['Freelance Gig', 'Lepas'], 'reason' => 'no-match']], $match->unmatched);
    }

    public function test_an_item_that_matches_two_new_items_is_not_guessed(): void
    {
        $this->newItem('reference_job_types', 'part-time', 'Paruh Waktu', 'Part Time');
        $this->newItem('reference_job_types', 'part-time-2', 'Paruh Waktu 2', 'Part-Time');
        $this->legacyMasterItem('job_types', 4, ['en' => 'part time']);

        $match = $this->matcher()->match($this->spec('job_types'));

        $this->assertSame([], $match->matched);
        $this->assertSame('ambiguous', $match->unmatched[0]['reason']);
    }

    public function test_a_mapping_file_overrides_the_names_and_an_unknown_slug_is_reported(): void
    {
        $intern = $this->newItem('reference_job_types', 'internship', 'Magang', 'Internship');
        $this->newItem('reference_job_types', 'full-time', 'Penuh Waktu', 'Full Time');
        $this->legacyMasterItem('job_types', 1, ['en' => 'Full Time']);
        $this->legacyMasterItem('job_types', 2, ['en' => 'Nothing alike']);
        $this->legacyMasterItem('job_types', 3, ['en' => 'Whatever']);

        $match = $this->matcher()->match($this->spec('job_types'), [1 => 'internship', 2 => 'internship', 3 => 'no-such-slug']);

        $this->assertSame([1 => $intern, 2 => $intern], $match->matched);
        $this->assertSame([['legacy_id' => 3, 'names' => ['Whatever'], 'reason' => 'override-slug-unknown']], $match->unmatched);
    }

    public function test_languages_are_matched_by_their_plain_name(): void
    {
        $english = $this->referenceId('reference_languages', 'english', ['code' => 'en']);
        DB::table('reference_languages')->where('id', $english)->update(['name' => json_encode(['id' => 'Inggris', 'en' => 'English'])]);
        $this->legacyLanguage(1, 'English');
        $this->legacyLanguage(2, 'inggris');
        $this->legacyLanguage(3, 'Klingon');

        $match = $this->matcher()->match($this->spec('languages'));

        $this->assertSame([1 => $english, 2 => $english], $match->matched);
        $this->assertSame(3, $match->unmatched[0]['legacy_id']);
        $this->assertSame(['Klingon'], $match->unmatched[0]['names']);
    }

    public function test_an_empty_legacy_master_has_nothing_to_match(): void
    {
        $match = $this->matcher()->match($this->spec('job_roles'));

        $this->assertSame([], $match->matched);
        $this->assertSame([], $match->unmatched);
    }
}
