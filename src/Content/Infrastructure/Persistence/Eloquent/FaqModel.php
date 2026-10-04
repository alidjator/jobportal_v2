<?php

namespace Src\Content\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * Eloquent record of `content_faqs`. Never leaves Infrastructure.
 *
 * @property int $id
 * @property int $category_id
 * @property int $sort_order
 * @property bool $is_active
 */
final class FaqModel extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['question', 'answer'];

    protected $table = 'content_faqs';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
