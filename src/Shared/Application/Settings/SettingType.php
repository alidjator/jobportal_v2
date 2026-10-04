<?php

namespace Src\Shared\Application\Settings;

enum SettingType: string
{
    case Integer = 'integer';
    case Boolean = 'boolean';
    case Text = 'text';
    /** A list of strings taken from a fixed set of allowed values (for example the active CV templates). */
    case Choices = 'choices';
}
