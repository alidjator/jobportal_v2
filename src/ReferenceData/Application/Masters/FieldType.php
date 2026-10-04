<?php

namespace Src\ReferenceData\Application\Masters;

enum FieldType: string
{
    case Text = 'text';
    case Integer = 'integer';
    case Choice = 'choice';
}
