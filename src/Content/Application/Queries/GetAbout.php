<?php

namespace Src\Content\Application\Queries;

use Src\Content\Domain\AboutRepository;
use Src\Shared\Application\RequestContext;

final class GetAbout
{
    public function __construct(private AboutRepository $repository, private RequestContext $context) {}

    /** @return array<string, mixed> */
    public function __invoke(): array
    {
        return [
            'testimonials' => $this->repository->testimonials(),
            'states' => [
                'live_jobs' => $this->repository->liveJobs($this->context->countryId),
                'companies' => $this->repository->companyCount(),
                'candidates' => $this->repository->candidateCount(),
            ],
        ];
    }
}
