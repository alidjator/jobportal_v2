<?php

namespace Tests\Feature\LegacyImport;

use DateTimeImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Src\LegacyImport\Domain\IdMap;
use Tests\FeatureTestCase;

final class IdMapTest extends FeatureTestCase
{
    private function map(): IdMap
    {
        return $this->app->make(IdMap::class);
    }

    private function at(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-07 09:00:00');
    }

    public function test_a_remembered_row_can_be_found_by_entity_and_legacy_id(): void
    {
        $this->map()->remember('user', 12, 345, $this->at());

        $this->assertSame(345, $this->map()->find('user', 12));
        $this->assertNull($this->map()->find('user', 13));
        $this->assertNull($this->map()->find('company', 12));
        $this->assertSame('2026-10-07 09:00:00', DB::table('legacyimport_id_map')->value('imported_at'));
    }

    public function test_a_legacy_row_keeps_its_first_answer(): void
    {
        $this->map()->remember('user', 12, 345, $this->at());
        $this->map()->remember('user', 12, 999, $this->at());

        $this->assertSame(345, $this->map()->find('user', 12));
        $this->assertSame(1, DB::table('legacyimport_id_map')->count());
    }

    public function test_several_rows_are_found_in_one_call(): void
    {
        $this->map()->remember('job', 1, 10, $this->at());
        $this->map()->remember('job', 2, 20, $this->at());
        $this->map()->remember('user', 3, 30, $this->at());

        $this->assertSame([1 => 10, 2 => 20], $this->map()->findMany('job', [1, 2, 3]));
        $this->assertSame([], $this->map()->findMany('job', []));
    }

    public function test_replacing_an_entity_swaps_its_whole_map_and_leaves_the_others(): void
    {
        $this->map()->remember('master.skills', 1, 100, $this->at());
        $this->map()->remember('master.skills', 2, 200, $this->at());
        $this->map()->remember('user', 1, 5, $this->at());

        $this->map()->replace('master.skills', [2 => 201, 3 => 300], $this->at());

        $this->assertSame([2 => 201, 3 => 300], $this->map()->findMany('master.skills', [1, 2, 3]));
        $this->assertSame(5, $this->map()->find('user', 1));
    }

    public function test_the_table_itself_refuses_a_duplicate_legacy_row(): void
    {
        DB::table('legacyimport_id_map')->insert(['entity' => 'user', 'legacy_id' => 1, 'new_id' => 2, 'imported_at' => now()]);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('legacyimport_id_map')->insert(['entity' => 'user', 'legacy_id' => 1, 'new_id' => 3, 'imported_at' => now()]);
    }
}
