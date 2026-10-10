<?php

namespace Src\Newsletter\Domain;

enum IssueStatus: string
{
    case Draft = 'draft';
    case Sending = 'sending';
    case Sent = 'sent';
}
