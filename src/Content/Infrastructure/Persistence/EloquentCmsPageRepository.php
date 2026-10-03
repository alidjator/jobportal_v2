<?php

namespace Src\Content\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Content\Domain\CmsPageRepository;

final class EloquentCmsPageRepository implements CmsPageRepository
{
    public function page(string $pageSlug, string $column, array $languageCodes): array
    {
        $default = DB::table('cms')->value($column);

        foreach ($languageCodes as $code) {
            $row = DB::table('cms_contents')
                ->where('translation_code', $code)
                ->where('page_slug', $pageSlug)
                ->first();

            if ($row) {
                return ['content' => $row->text, 'default_content' => $default];
            }
        }

        return ['content' => $default, 'default_content' => $default];
    }
}
