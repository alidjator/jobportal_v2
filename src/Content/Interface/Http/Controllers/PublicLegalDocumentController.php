<?php

namespace Src\Content\Interface\Http\Controllers;

use Src\Content\Application\LegalDocuments\GetActiveLegalDocument;
use Src\Content\Application\LegalDocuments\GetLegalDocumentVersion;
use Src\Content\Domain\LegalDocumentType;
use Src\Content\Interface\Http\Resources\LegalDocumentResource;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Interface\Http\ApiController;

final class PublicLegalDocumentController extends ApiController
{
    public function active(string $type, GetActiveLegalDocument $getActive): LegalDocumentResource
    {
        return new LegalDocumentResource($getActive->execute($this->type($type)));
    }

    public function version(string $type, int $version, GetLegalDocumentVersion $getVersion): LegalDocumentResource
    {
        return new LegalDocumentResource($getVersion->execute($this->type($type), $version));
    }

    private function type(string $type): LegalDocumentType
    {
        return LegalDocumentType::tryFrom($type) ?? throw EntityNotFound::of('Legal document', $type);
    }
}
