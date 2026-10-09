<?php

namespace Tests\Feature\Shared;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Spatie\Translatable\HasTranslations;
use Tests\FeatureTestCase;

/**
 * Verifies spatie/laravel-translatable against a JSON `name` column (`{"id": "...", "en": "..."}`) of a master table,
 * using a throwaway model (the real Eloquent models arrive with the ReferenceData context).
 */
final class SpatieTranslatableCompatibilityTest extends FeatureTestCase
{
    private function makeModel(): Model
    {
        return new class extends Model
        {
            use HasTranslations;

            protected $table = 'reference_job_categories';

            protected $guarded = [];

            /** @var list<string> */
            public array $translatable = ['name'];
        };
    }

    public function test_translations_are_stored_as_json_and_read_by_locale(): void
    {
        $category = $this->makeModel();
        $category->setTranslations('name', ['id' => 'Teknik', 'en' => 'Engineering']);
        $category->slug = 'engineering';
        $category->save();

        $stored = $this->makeModel()->newQuery()->findOrFail($category->getKey());

        // The column is `jsonb`, which does not keep the order of the keys.
        $this->assertEquals(['id' => 'Teknik', 'en' => 'Engineering'], $stored->getTranslations('name'));

        App::setLocale('id');
        $this->assertSame('Teknik', $stored->name);

        App::setLocale('en');
        $this->assertSame('Engineering', $stored->name);
    }

    public function test_a_missing_locale_falls_back_to_the_fallback_locale(): void
    {
        $category = $this->makeModel();
        $category->setTranslation('name', 'en', 'Finance');
        $category->slug = 'finance';
        $category->save();

        App::setLocale('id');

        $this->assertSame('Finance', $category->fresh()->name);
    }

    public function test_the_json_name_can_be_searched_per_locale(): void
    {
        $category = $this->makeModel();
        $category->setTranslations('name', ['id' => 'Pemasaran', 'en' => 'Marketing']);
        $category->slug = 'marketing';
        $category->save();

        $found = $this->makeModel()->newQuery()->where('name->id', 'Pemasaran')->first();

        $this->assertSame($category->getKey(), $found?->getKey());
        $this->assertNull($this->makeModel()->newQuery()->where('name->en', 'Pemasaran')->first());
    }
}
