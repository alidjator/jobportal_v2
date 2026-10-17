<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;

/** What a list of interviews asks for. Without `$allOfCompany` only the interviews the member sits on. */
final readonly class MyInterviewQuery
{
    /**
     * @param  bool  $upcoming  upcoming (not over yet, soonest first) or past (over, newest first)
     * @param  bool  $scorecardPending  only the interviews where the member sits on the panel and still owes their scorecard
     * @param  ?string  $rsvp  `pending`: the upcoming interviews where the member sits on the panel and has not answered the invitation; `declined`: the upcoming interviews the member scheduled where a panel member cannot attend
     * @param  ?list<int>  $jobIds  with `$allOfCompany`: the jobs the member may see (null: every job of the company)
     * @param  list<int>  $boundJobIds  with `$allOfCompany`: the jobs where the member is a hiring manager bound to the stage boundary
     */
    public function __construct(
        public int $userId,
        public int $companyId,
        public bool $upcoming,
        public bool $scorecardPending,
        public ?string $rsvp,
        public bool $allOfCompany,
        public ?array $jobIds,
        public array $boundJobIds,
        public DateTimeImmutable $now,
    ) {}
}
