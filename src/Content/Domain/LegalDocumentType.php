<?php

namespace Src\Content\Domain;

enum LegalDocumentType: string
{
    case Terms = 'terms';
    case Privacy = 'privacy';
}
