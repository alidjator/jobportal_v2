<?php

namespace Src\Shared\Application\Recruitment;

/** Port of Recruitment for Assessment: the facts of an application that choose a norm group (`docs/design/phase-8-provider-tools.md` section 1). */
interface ApplicantNormData
{
    /** The age at the time of applying and the highest education rank of the application; both null for an application that does not exist or does not carry them. */
    public function of(int $applicationId): ApplicantNormFacts;
}
