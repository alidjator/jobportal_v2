<?php

namespace Src\Content\Domain;

interface FaqRepository
{
    /** @return array<int, mixed> */
    public function categoriesWithFaqs(string $languageCode): array;
}
