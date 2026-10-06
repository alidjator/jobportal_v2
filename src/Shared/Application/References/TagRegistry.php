<?php

namespace Src\Shared\Application\References;

/**
 * Tags are free keywords that a company types while posting a job. The registry turns the text into tag ids (creating the tags that do not exist
 * yet) and back. Provided by ReferenceData, which owns the tag table.
 */
interface TagRegistry
{
    /**
     * The id of each tag, in the order given; a tag that does not exist yet is created. A tag is identified by its slug (the text with `+` as
     * `plus`, `#` as `sharp`, in lower case ASCII), so two spellings that give the same slug are one tag. The names must already be normalised.
     * Runs inside the caller's transaction.
     *
     * @param  list<string>  $names
     * @return list<int>
     */
    public function idsFor(array $names): array;

    /**
     * The name of each tag as it was first written, keyed by tag id; an unknown id is missing.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function names(array $ids): array;

    /**
     * The ids of the tags whose name contains the text, whatever the letter case (`%`, `_` and `\` count as themselves), at most `$limit`. Used by the job
     * search to find the jobs that carry a tag the keyword matches.
     *
     * @return list<int>
     */
    public function idsMatching(string $text, int $limit): array;
}
