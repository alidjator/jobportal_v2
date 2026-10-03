<?php

namespace Src\Content\Domain;

interface CmsPageRepository
{
    /**
     * @param  string  $pageSlug  nilai `cms_contents.page_slug`
     * @param  string  $column  kolom default pada tabel `cms`
     * @param  array<int, string>  $languageCodes  kode bahasa berurutan (prioritas)
     * @return array{content: ?string, default_content: ?string}
     */
    public function page(string $pageSlug, string $column, array $languageCodes): array;
}
