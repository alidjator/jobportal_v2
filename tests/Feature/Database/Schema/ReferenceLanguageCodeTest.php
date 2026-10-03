<?php

namespace Tests\Feature\Database\Schema;

use Database\Seeders\Reference\LanguageSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Tests\FeatureTestCase;

final class ReferenceLanguageCodeTest extends FeatureTestCase
{
    public function test_the_database_rejects_a_second_language_with_the_same_code(): void
    {
        $this->seed(LanguageSeeder::class);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('reference_languages')->insert([
            'name' => json_encode(['id' => 'Bahasa Lain', 'en' => 'Another Language']),
            'slug' => 'another-language',
            'code' => 'id',
            'sort_order' => 99,
            'is_active' => true,
        ]);
    }
}
