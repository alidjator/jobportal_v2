<?php

namespace Src\Assessment\Application\Register;

use Src\Assessment\Application\Review\ReviewAccess;
use Src\Assessment\Domain\RegisterFilter;
use Src\Assessment\Domain\ReportRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The report register of the psychologist's provider: its signed and automatically issued reports, newest signature first (`docs/design/phase-8-provider-tools.md` section 8). */
final readonly class ListReportRegister
{
    public function __construct(
        private ReviewAccess $access,
        private ReportRepository $reports,
        private RegisterItems $items,
    ) {}

    /**
     * @return Page<RegisterItem>
     *
     * @throws AccessDenied `not-a-psychologist`, `psychologist-suspended` or `provider-suspended`
     */
    public function execute(Actor $actor, RegisterFilter $filter, PageRequest $request): Page
    {
        $psychologist = $this->access->psychologist($actor);

        return $this->items->of($this->reports->registerOfProvider($psychologist->providerId, $filter, $request));
    }
}
