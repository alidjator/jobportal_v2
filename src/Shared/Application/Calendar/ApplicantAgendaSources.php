<?php

namespace Src\Shared\Application\Calendar;

/** The sources of the agenda of a job seeker. Each context registers its own from its service provider. */
final class ApplicantAgendaSources
{
    /** @var list<ApplicantAgendaSource> */
    private array $sources = [];

    public function add(ApplicantAgendaSource ...$sources): void
    {
        foreach ($sources as $source) {
            $this->sources[] = $source;
        }
    }

    /** @return list<ApplicantAgendaSource> */
    public function all(): array
    {
        return $this->sources;
    }
}
