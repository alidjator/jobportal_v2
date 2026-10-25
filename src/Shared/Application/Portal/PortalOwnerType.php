<?php

namespace Src\Shared\Application\Portal;

/** Who a portal setting belongs to: the platform (the identity of the portal), a group or one company (a brand). */
enum PortalOwnerType: string
{
    case Platform = 'platform';
    case Group = 'group';
    case Company = 'company';
}
