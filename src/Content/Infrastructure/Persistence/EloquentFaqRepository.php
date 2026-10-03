<?php

namespace Src\Content\Infrastructure\Persistence;

use Src\Content\Domain\FaqRepository;
use Src\Content\Infrastructure\Persistence\Eloquent\FaqCategory;

final class EloquentFaqRepository implements FaqRepository
{
    public function categoriesWithFaqs(string $languageCode): array
    {
        return FaqCategory::with(['faqs' => fn ($q) => $q->where('code', $languageCode)])->get()->toArray();
    }
}
