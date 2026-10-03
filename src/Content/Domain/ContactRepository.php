<?php

namespace Src\Content\Domain;

interface ContactRepository
{
    /** @param  array{name: string, email: string, subject: string, message: string}  $contact */
    public function store(array $contact): void;
}
