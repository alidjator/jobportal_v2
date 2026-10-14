<?php

namespace Src\Company\Domain;

/** Whether a membership works. A company admin suspends one temporarily; it is separate from the account status a platform admin sets, and either blocks. */
enum MemberStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
