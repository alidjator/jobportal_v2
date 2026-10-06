<?php

namespace Src\JobSeeker\Domain;

/** The values match the check constraint of `jobseeker_languages.proficiency`. */
enum LanguageProficiency: string
{
    case Basic = 'basic';
    case Conversational = 'conversational';
    case Fluent = 'fluent';
    case Native = 'native';
}
