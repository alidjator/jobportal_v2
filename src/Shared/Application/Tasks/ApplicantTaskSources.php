<?php

namespace Src\Shared\Application\Tasks;

/** The sources of the to-do list of a job seeker. Each context registers its own from its service provider. */
final class ApplicantTaskSources
{
    /** @var list<ApplicantTaskSource> */
    private array $sources = [];

    public function add(ApplicantTaskSource ...$sources): void
    {
        foreach ($sources as $source) {
            $this->sources[] = $source;
        }
    }

    /** @return list<ApplicantTaskSource> */
    public function all(): array
    {
        return $this->sources;
    }
}
