<?php

namespace Src\Content\Infrastructure\Persistence\Eloquent;

use Src\Content\Domain\LegalDocumentStatus;
use Src\Shared\Application\Legal\ActiveLegalVersions;

/** One light query (no document bodies): the type and version of every published document. */
final class DatabaseActiveLegalVersions implements ActiveLegalVersions
{
    public function active(): array
    {
        $versions = [];

        foreach (LegalDocumentModel::query()->where('status', LegalDocumentStatus::Published->value)->get(['type', 'version']) as $document) {
            $versions[$document->type] = $document->version;
        }

        return $versions;
    }
}
