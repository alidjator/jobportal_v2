<?php

namespace Src\Shared\Application\Recruitment;

/** Lets Assessment move an application on by itself after a final test result (`docs/design/phase-8-assessment-automation.md` section 2). Provided by Recruitment. */
interface ApplicationAdvancer
{
    /**
     * Moves the application from `$fromStageId` to `$toStageId` as the system, the way a recruiter's move would: into an interview stage "schedule later", into an assessment stage with that stage's
     * default test. Nothing happens, and false is returned, when the application is no longer in `$fromStageId`, its job was taken down, the target stage is gone or not an assessment or interview
     * stage of the company, or the move could not be completed (no usable default test in the target stage).
     *
     * @param  string  $note  the text of the history entry
     * @param  int  $senderUserId  the member a test sent by the move is sent in the name of
     * @return bool whether the application moved
     */
    public function advance(int $applicationId, int $fromStageId, int $toStageId, string $note, int $senderUserId): bool;
}
