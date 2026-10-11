<?php

namespace Src\Assessment\Domain;

enum DrawingSource: string
{
    case Canvas = 'canvas';
    case Upload = 'upload';
}
