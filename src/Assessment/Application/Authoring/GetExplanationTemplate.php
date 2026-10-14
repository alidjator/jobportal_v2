<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\ReportTemplateRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The psychologist reads the explanation template of a battery of their provider, or null while it has none. */
final readonly class GetExplanationTemplate
{
    public function __construct(private AuthoringAccess $access, private ReportTemplateRepository $templates) {}

    /**
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $testId): ?string
    {
        $this->access->ownedTest($this->access->author($actor), $testId);

        return $this->templates->explanationTemplateOf($testId);
    }
}
