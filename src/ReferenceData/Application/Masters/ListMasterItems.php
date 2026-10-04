<?php

namespace Src\ReferenceData\Application\Masters;

use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

/** Reads one master list. A paginated master (`skills`, `job-roles`, `tags`) returns a `Page`; every other master returns the whole list. */
final readonly class ListMasterItems
{
    public function __construct(private MasterRepository $masters) {}

    /** @return Page<MasterItem>|list<MasterItem> */
    public function execute(MasterDefinition $definition, MasterQuery $query): Page|array
    {
        if (! $definition->paginated) {
            return $this->masters->all($definition, $query);
        }

        return $this->masters->page($definition, new MasterQuery(
            $query->onlyActive,
            $query->language,
            $query->search,
            $query->filters,
            $query->sort,
            $query->page ?? new PageRequest,
        ));
    }
}
