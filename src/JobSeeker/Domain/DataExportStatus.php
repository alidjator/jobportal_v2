<?php

namespace Src\JobSeeker\Domain;

enum DataExportStatus: string
{
    case Building = 'building';
    case Ready = 'ready';
    case Expired = 'expired';
    case Failed = 'failed';
}
