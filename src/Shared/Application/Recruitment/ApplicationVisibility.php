<?php

namespace Src\Shared\Application\Recruitment;

/** Lets another context respect the stage boundary of the hiring managers (`docs/design/phase-8-hiring-managers.md` section 2) when it lists things about applications. Provided by Recruitment. */
interface ApplicationVisibility
{
    /**
     * The applications among these that a member bound to the stage boundary on `$boundJobIds` may see: all of those on other jobs, and on a bound job the ones that carry the marker.
     *
     * @param  list<int>  $applicationIds
     * @param  list<int>  $boundJobIds
     * @return list<int>
     */
    public function visibleAmong(array $applicationIds, array $boundJobIds): array;
}
