<?php

namespace Src\Content\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * Eloquent record of `content_faq_categories`. Never leaves Infrastructure.
 *
 * @property int $id
 * @property int $sort_order
 * @property bool $is_active
 */
final class FaqCategoryModel extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name'];

    protected $table = 'content_faq_categories';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
