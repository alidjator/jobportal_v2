<?php

namespace Src\Assessment\Domain;

enum LicenceStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
