<?php

namespace Src\LegacyImport\Application;

/** Writes a job with its skills, benefits and its one assignee into the tables of the Job context (the documented exception of this context). */
interface JobWriter
{
    /** @return int the id of the new job */
    public function insert(NewJob $job): int;
}
