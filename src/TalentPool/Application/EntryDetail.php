<?php

namespace Src\TalentPool\Application;

use Src\Shared\Application\Recruitment\PoolApplicationRef;

/**
 * One entry with everything the entry page shows. A findable candidate is opened through the candidate search with `$view->candidateSlug` (their live profile, with
 * its own contact rules); a person available through consent alone has `$profile`, the snapshot of their latest consenting application without contact data.
 * `$applications` are the applications of the person to the company that the member may see.
 */
final readonly class EntryDetail
{
    /**
     * @param  array<string, mixed>|null  $profile
     * @param  list<PoolApplicationRef>  $applications
     */
    public function __construct(public EntryView $view, public ?array $profile, public array $applications) {}
}
