<?php

namespace Src\ReferenceData\Application\Masters;

use Src\Shared\Application\Pagination\PageRequest;

/** What to read from a master list. The sort is `sort_order` (the curated order, default), `name` or `-name` (by the name in the request language). */
final readonly class MasterQuery
{
    public const string SORT_CURATED = 'sort_order';

    public const string SORT_NAME = 'name';

    public const string SORT_NAME_DESCENDING = '-name';

    /** @var list<string> */
    public const array SORTS = [self::SORT_CURATED, self::SORT_NAME, self::SORT_NAME_DESCENDING];

    /** @param  array<string, string>  $filters choice column mapped to the wanted value */
    public function __construct(
        public bool $onlyActive,
        public string $language,
        public ?string $search = null,
        public array $filters = [],
        public string $sort = self::SORT_CURATED,
        public ?PageRequest $page = null,
    ) {}
}
