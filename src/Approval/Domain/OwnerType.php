<?php

namespace Src\Approval\Domain;

/** Who owns an approval flow: a group (its default flows) or a company, an internal tenant (flows that replace the default for itself). */
enum OwnerType: string
{
    case Group = 'group';
    case Company = 'company';
}
