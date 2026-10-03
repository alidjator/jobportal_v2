<?php

namespace Tests\Feature\ReferenceData;

use Illuminate\Support\Facades\DB;
use Tests\FeatureTestCase;

class ReferenceDataApiTest extends FeatureTestCase
{
    private function seedLookup(string $table, string $fk, string $translations, array $names): int
    {
        $id = DB::table($table)->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        foreach ($names as $locale => $name) {
            DB::table($translations)->insert([$fk => $id, 'name' => $name, 'locale' => $locale]);
        }

        return $id;
    }

    public function test_job_roles_use_locale_with_fallback(): void
    {
        $id = $this->seedLookup('job_roles', 'job_role_id', 'job_role_translations', ['en' => 'Engineer', 'id' => 'Insinyur']);
        $only = $this->seedLookup('job_roles', 'job_role_id', 'job_role_translations', ['en' => 'Designer']);

        $this->getJson('/api/job-roles', ['X-Language' => 'id'])
            ->assertOk()
            ->assertJsonFragment(['id' => $id, 'name' => 'Insinyur'])
            ->assertJsonFragment(['id' => $only, 'name' => 'Designer']);

        $this->getJson('/api/job-roles')->assertJsonFragment(['id' => $id, 'name' => 'Engineer']);
    }

    public function test_categories_expose_image_and_svg_flag(): void
    {
        $id = DB::table('job_categories')->insertGetId(['image' => 'uploads/x.svg', 'slug' => 'x-test']);
        DB::table('job_category_translations')->insert(['job_category_id' => $id, 'name' => 'Tech', 'locale' => 'en']);

        $item = collect($this->getJson('/api/categories')->assertOk()->json('data'))->firstWhere('id', $id);

        $this->assertSame('Tech', $item['name']);
        $this->assertTrue($item['is_svg']);
        $this->assertStringEndsWith('uploads/x.svg', $item['image']);
    }

    public function test_popular_tags_are_ranked_by_job_count_and_limited_to_flagged(): void
    {
        $hot = DB::table('tags')->insertGetId(['show_popular_list' => 1]);
        $cold = DB::table('tags')->insertGetId(['show_popular_list' => 1]);
        $hidden = DB::table('tags')->insertGetId(['show_popular_list' => 0]);
        foreach ([$hot, $cold, $hidden] as $tag) {
            DB::table('tag_translations')->insert(['tag_id' => $tag, 'name' => "tag-$tag", 'locale' => 'en']);
        }
        DB::table('job_tag')->insert([['job_id' => null, 'tag_id' => $hot], ['job_id' => null, 'tag_id' => $hot], ['job_id' => null, 'tag_id' => $cold]]);

        $data = $this->getJson('/api/popular-tags')->assertOk()->json('data');
        $ids = array_column($data, 'id');

        $this->assertNotContains($hidden, $ids);
        $this->assertLessThan(array_search($cold, $ids), array_search($hot, $ids));
        $this->assertSame(2, collect($data)->firstWhere('id', $hot)['jobs_count']);
    }

    public function test_change_language_unknown_code_returns_error(): void
    {
        $this->getJson('/api/change-language/zz-nope')->assertStatus(400)->assertExactJson(['error' => 'language_not_found']);
    }

    public function test_change_language_and_current_session(): void
    {
        DB::table('languages')->insert(['name' => 'Testish', 'code' => 'tt', 'icon' => 'flag-tt', 'direction' => 'ltr']);

        $this->getJson('/api/change-language/tt')->assertOk()->assertJsonPath('data.current_language.code', 'tt');
        $this->getJson('/api/current-session', ['X-Language' => 'tt'])->assertOk()->assertJsonPath('data.current_language.code', 'tt');
    }

    public function test_countries_and_change_country(): void
    {
        $id = DB::table('countries')->insertGetId(['name' => 'Testland', 'sortname' => 'TL', 'status' => 1]);

        $this->getJson('/api/countries')->assertOk()->assertJsonFragment(['id' => $id, 'name' => 'Testland', 'sortname' => 'TL', 'status' => 1]);
        $this->getJson("/api/change-country/$id")->assertOk()->assertJsonPath('data.current_country.name', 'Testland');
        $this->getJson('/api/current-session', ['selected_country' => (string) $id])->assertJsonPath('data.current_country.id', $id);
    }

    public function test_currencies_and_change_currency(): void
    {
        DB::table('currencies')->insert(['name' => 'Test Coin', 'code' => 'TTC', 'symbol' => 'T', 'rate' => 1]);

        $this->getJson('/api/currencies')->assertOk()->assertJsonFragment(['code' => 'TTC', 'symbol' => 'T']);
        $this->getJson('/api/change-currency/TTC')->assertOk()->assertJsonPath('data.current_currency.code', 'TTC');
    }

    public function test_localization_strings(): void
    {
        $this->getJson('/api/localizations/en/strings')->assertOk()->assertJsonPath('data.job', 'Job');
        $this->getJson('/api/localizations/xx/strings')->assertOk()->assertExactJson(['data' => []]);
        $this->getJson('/api/localizations/..%2Fen/strings')->assertStatus(404);
    }

    public function test_language_translations_bundle(): void
    {
        DB::table('languages')->insert(['name' => 'English T', 'code' => 'en', 'icon' => 'flag-en-t', 'direction' => 'ltr']);

        $json = $this->getJson('/api/language/translations')->assertOk()->json('data');

        $this->assertArrayHasKey('default_language_code', $json);
        $this->assertSame('Job', collect($json['translations'])->firstWhere('code', 'en')['translations']['job']);
    }
}
