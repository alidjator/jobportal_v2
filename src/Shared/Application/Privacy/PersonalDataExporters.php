<?php

namespace Src\Shared\Application\Privacy;

/** Every personal data exporter of every context. */
interface PersonalDataExporters
{
    /** @return list<PersonalDataExporter> */
    public function all(): array;
}
