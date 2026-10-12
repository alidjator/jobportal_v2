<?php

namespace Src\Recruitment\Domain\Video;

enum RecordingStatus: string
{
    case Recording = 'recording';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}
