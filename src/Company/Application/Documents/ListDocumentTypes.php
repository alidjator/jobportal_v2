<?php

namespace Src\Company\Application\Documents;

use Src\Company\Domain\DocumentType;
use Src\Company\Domain\DocumentTypeRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** A platform admin lists every document type, inactive ones included. The list is short, so it is not paginated. */
final readonly class ListDocumentTypes
{
    public function __construct(private DocumentTypeRepository $types) {}

    /**
     * @return list<DocumentType>
     *
     * @throws AccessDenied when the actor is not an admin
     */
    public function execute(Actor $actor): array
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can manage document types.');
        }

        return $this->types->all();
    }
}
