<?php

namespace Src\LegacyImport\Application;

/**
 * Writes the job seeker tables of the new schema (the Infrastructure implementation writes `jobseeker_*` directly: the documented exception of this context).
 * A row is given by its kind (`profile`, `experience`, `education`, `skill`, `language`, `link`, `resume`) and its columns; the database constraints guard the rest.
 */
interface JobSeekerWriter
{
    public const array KINDS = ['profile', 'experience', 'education', 'skill', 'language', 'link', 'resume'];

    /**
     * @param  array<string, mixed>  $columns
     * @return int the id of the new row
     */
    public function insert(string $kind, array $columns): int;

    /** How many rows of the kind the user already has. */
    public function count(string $kind, int $userId): int;
}
