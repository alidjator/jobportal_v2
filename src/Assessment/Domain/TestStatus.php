<?php

namespace Src\Assessment\Domain;

enum TestStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
