<?php

namespace Src\Recruitment\Application\Video;

use Src\Recruitment\Application\TeamInterviewLookup;
use Src\Recruitment\Domain\Video\InterviewRecording;
use Src\Recruitment\Domain\Video\RecordingRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The recordings of an interview, newest first, for the members who may see its application. */
final readonly class ListInterviewRecordings
{
    public function __construct(private TeamInterviewLookup $lookup, private RecordingRepository $recordings) {}

    /**
     * @return list<InterviewRecording>
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $interviewId): array
    {
        $this->lookup->find($actor, $interviewId);

        return $this->recordings->forInterview($interviewId);
    }
}
