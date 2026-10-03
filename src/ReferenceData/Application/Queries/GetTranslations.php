<?php

namespace Src\ReferenceData\Application\Queries;

use Src\ReferenceData\Domain\ReferenceDataRepository;

final class GetTranslations
{
    public function __construct(private ReferenceDataRepository $repository) {}

    public function __invoke(): array
    {
        $languages = collect($this->repository->languages());

        return [
            'default_language_code' => config('jobportal.default_language'),
            'languages' => $languages->map(fn ($l) => ['name' => $l['name'], 'code' => $l['code']])->all(),
            'translations' => $languages->map(fn ($l) => [
                'code' => $l['code'],
                'translations' => (new GetLocalizationStrings)($l['code']),
            ])->all(),
        ];
    }
}
