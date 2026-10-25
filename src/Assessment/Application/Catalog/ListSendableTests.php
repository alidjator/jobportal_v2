<?php

namespace Src\Assessment\Application\Catalog;

use Src\Assessment\Domain\SectionRepository;
use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestOwner;
use Src\Assessment\Domain\TestRepository;
use Src\Assessment\Domain\TestStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * The tests a member who may send tests can choose from, in one list: the company's own published skill tests, the published tests of the system library and, when the company may
 * use batteries, the batteries granted to it. These are exactly the tests `InvitationChecks` accepts. At most 100 of each source are listed, by title; `$search` narrows them by title.
 */
final readonly class ListSendableTests
{
    public function __construct(
        private CatalogAccess $access,
        private TestRepository $tests,
        private SectionRepository $sections,
    ) {}

    /**
     * @return list<SendableTest>
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     */
    public function execute(Actor $actor, ?string $search): array
    {
        $companyId = $this->access->companyId($actor);
        $actor->membership?->require(CompanyPermission::AssessmentsSend);

        $page = new PageRequest(1, PageRequest::MAX_PER_PAGE);
        $found = [
            SendableTest::COMPANY => $this->tests->pageOwnedBy(TestOwner::company($companyId), TestStatus::Published, $search, $page)->items,
            SendableTest::LIBRARY => $this->tests->pageLibrary($search, $page)->items,
            SendableTest::BATTERY => $this->access->psychologicalEnabled($companyId) ? $this->tests->pageGrantedTo($companyId, $search, $page)->items : [],
        ];

        $totals = $this->sections->totalsOf(array_values(array_map(
            static fn (Test $test): int => (int) $test->id,
            array_merge(...array_values($found)),
        )));

        $list = [];

        foreach ($found as $source => $tests) {
            usort($tests, static fn (Test $a, Test $b): int => [mb_strtolower($a->title()), $a->id] <=> [mb_strtolower($b->title()), $b->id]);

            foreach ($tests as $test) {
                $total = $totals[(int) $test->id] ?? ['sections' => 0, 'seconds' => 0];
                $list[] = new SendableTest((int) $test->id, $test->title(), $test->kind()->value, $source, $test->description(), $total['sections'], $total['seconds']);
            }
        }

        return $list;
    }
}
