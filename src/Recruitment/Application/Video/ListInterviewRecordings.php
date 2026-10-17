<?php

namespace Src\Recruitment\Application\Video;

use Src\Recruitment\Application\TeamInterviewLookup;
use Src\Recruitment\Domain\Video\InterviewRecording;
use Src\Recruitment\Domain\Video\RecordingRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The recordings of an interview, newest first, for the members who may see its application. */
final readonly class ListInterviewRecordings
{
    public function __construct(private TeamInterviewLookup $lookup, private RecordingRepository $recordings) {}

    /**
     * @return list<InterviewRecording>
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $interviewId): array
    {
        $this->lookup->assertSatOn($this->lookup->find($actor, $interviewId, permission: CompanyPermission::InterviewsManage, panelOpens: true));

        return $this->recordings->forInterview($interviewId);
    }
}
