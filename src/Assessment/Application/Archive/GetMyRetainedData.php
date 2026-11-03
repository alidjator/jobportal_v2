<?php

namespace Src\Assessment\Application\Archive;

use Src\Assessment\Application\AssessmentSettings;
use Src\Assessment\Domain\ExaminationArchiveRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** What the person is told before they confirm the erasure of their account: the raw data of their psychological examinations that stays with the institution, and for how long (`docs/design/phase-8-provider-institutions.md` section 7). */
final readonly class GetMyRetainedData
{
    public function __construct(private ExaminationArchiveRepository $archive, private Settings $settings) {}

    /** @throws AccessDenied `job-seeker-only` */
    public function execute(Actor $actor): MyRetainedData
    {
        if ($actor->kind !== ActorKind::JobSeeker || $actor->id === null) {
            throw new AccessDenied('Only a job seeker can take a test.', 'job-seeker-only');
        }

        if (! $this->settings->boolean(AssessmentSettings::RAW_DATA_RETENTION_ENABLED)) {
            return new MyRetainedData(null, 0);
        }

        return new MyRetainedData($this->settings->integer(AssessmentSettings::RAW_DATA_RETENTION_YEARS), $this->archive->psychologicalAttemptCount($actor->id));
    }
}
