<?php

namespace Src\Approval\Domain;

/** What an approver of a step names: a member of a tenant, every holder of a group permission, or a relative role the subject's context resolves (the hiring manager). */
enum ApproverKind: string
{
    case Member = 'member';
    case GroupRole = 'group_role';
    case Relative = 'relative';
}
