<?php

namespace Src\Content\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * Eloquent record of `content_legal_documents`. Never leaves Infrastructure.
 *
 * @property int $id
 * @property string $type
 * @property int $version
 * @property string $status
 * @property Carbon|null $published_at
 * @property int|null $created_by_admin_id
 */
final class LegalDocumentModel extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['title', 'body'];

    protected $table = 'content_legal_documents';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['published_at' => 'immutable_datetime'];
    }
}
