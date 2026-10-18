<?php

namespace Src\Recruitment\Application;

/** Reads the hires of a company for the export. It checks nothing about who asks: the use case decides which jobs the member may see. */
interface HireExportReader
{
    /** How many applications the filter selects. */
    public function count(HireExportFilter $filter): int;

    /**
     * The first `$limit` hires the filter selects, oldest hire first, with the accepted offer; read in chunks as the caller iterates.
     *
     * @return iterable<HireExportRow>
     */
    public function export(HireExportFilter $filter, int $limit): iterable;
}
