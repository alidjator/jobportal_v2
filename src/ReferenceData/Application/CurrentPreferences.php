<?php

namespace Src\ReferenceData\Application;

use Src\ReferenceData\Domain\ReferenceDataRepository;
use Src\Shared\Application\RequestContext;

/**
 * Menerjemahkan preferensi request (header) menjadi bahasa/negara/mata uang aktif,
 * dengan fallback ke default sistem. Menggantikan helper berbasis session di aplikasi lama.
 */
final class CurrentPreferences
{
    public function __construct(
        private ReferenceDataRepository $repository,
        private RequestContext $context,
    ) {}

    public function language(): ?array
    {
        $language = $this->context->language ? $this->repository->language($this->context->language) : null;

        return $language ?? $this->repository->language(config('jobportal.default_language'));
    }

    public function country(): ?array
    {
        $country = $this->context->countryId ? $this->repository->country($this->context->countryId) : null;

        return $country ?? $this->repository->firstCountry();
    }

    public function currency(): ?array
    {
        $currency = $this->context->currency ? $this->repository->currency($this->context->currency) : null;

        return $currency ?? $this->repository->currency(config('jobportal.default_currency'));
    }
}
