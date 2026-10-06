<?php

namespace Src\Job\Application\Browse;

/** One word of the keyword, with the tags and the companies whose name contains it (found through ReferenceData and Company): a job matches the word through its title, one of those tags or one of those companies. */
final readonly class SearchWord
{
    /**
     * @param  list<int>  $tagIds
     * @param  list<int>  $companyIds
     */
    public function __construct(
        public string $text,
        public array $tagIds,
        public array $companyIds,
    ) {}
}
