<?php

namespace Src\Job\Application\Review;

/** What made a job look suspicious: a configured `phrase` or a `contact` detail (phone number, email address), the text that matched and the field it is in. */
final readonly class JobFlag
{
    public const string PHRASE = 'phrase';

    public const string CONTACT = 'contact';

    public function __construct(
        public string $kind,
        public string $value,
        public string $field,
    ) {}
}
