<?php

namespace Src\Content\Domain;

interface LanguageCodes
{
    public function exists(string $code): bool;
}
