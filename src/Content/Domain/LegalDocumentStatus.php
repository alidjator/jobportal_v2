<?php

namespace Src\Content\Domain;

enum LegalDocumentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
