<?php

namespace Src\Company\Application\NameChange;

use Src\Company\Application\Documents\DocumentFileContent;
use Src\Company\Domain\Events\CompanyNameChangeDocumentViewed;
use Src\Company\Domain\NameChangeRequestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** An admin holding `companies.manage` opens the proof of a name change. Every opening that returns the file is audited. The file is gone 90 days after the decision. */
final readonly class GetNameChangeDocumentForAdmin
{
    public function __construct(
        private NameChangeRequestRepository $requests,
        private FileStorage $files,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound when the request or its file does not exist (any more)
     */
    public function execute(Actor $actor, int $requestId, ?string $ipAddress, ?string $userAgent): DocumentFileContent
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can review name change requests.');
        }

        $request = $this->requests->find($requestId) ?? throw EntityNotFound::of('Name change request', $requestId);
        $contents = $request->documentPath === null ? null : $this->files->get($request->documentPath);

        if ($contents === null) {
            throw EntityNotFound::of('Name change document', $requestId);
        }

        $this->events->dispatch([new CompanyNameChangeDocumentViewed($request->companyId, $requestId, (int) $actor->id, $ipAddress, $userAgent, $this->clock->now())]);

        return new DocumentFileContent($contents, 'application/pdf', 'name-change-'.$requestId.'.pdf');
    }
}
