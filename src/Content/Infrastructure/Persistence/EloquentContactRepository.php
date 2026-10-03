<?php

namespace Src\Content\Infrastructure\Persistence;

use Src\Content\Domain\ContactRepository;
use Src\Content\Infrastructure\Persistence\Eloquent\Contact;

final class EloquentContactRepository implements ContactRepository
{
    public function store(array $contact): void
    {
        Contact::create($contact);
    }
}
