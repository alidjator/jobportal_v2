<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\Norm;
use Src\Assessment\Domain\NormRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The psychologist reads the norm table of a battery of their provider. */
final readonly class GetNorms
{
    public function __construct(private AuthoringAccess $access, private NormRepository $norms) {}

    /**
     * @return list<Norm>
     *
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $testId): array
    {
        $this->access->ownedTest($this->access->author($actor), $testId);

        return $this->norms->ofTest($testId);
    }
}
