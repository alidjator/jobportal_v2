<?php

namespace Src\Content\Application\Queries;

use Src\Content\Application\ContentLanguage;
use Src\Content\Domain\CmsPageRepository;

final class GetCmsPage
{
    public const PRIVACY = ['privacy_page', 'privary_page'];

    public const TERMS = ['terms_condition_page', 'terms_page'];

    public const REFUND = ['refund_page', 'refund_page'];

    public function __construct(private CmsPageRepository $repository, private ContentLanguage $language) {}

    /**
     * @param  array{0: string, 1: string}  $page  [page_slug, kolom default di tabel cms]
     * @return array{content: ?string, default_content: ?string}
     */
    public function __invoke(array $page): array
    {
        return $this->repository->page($page[0], $page[1], $this->language->priority());
    }
}
