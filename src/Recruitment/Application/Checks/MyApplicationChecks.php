<?php

namespace Src\Recruitment\Application\Checks;

use Src\Recruitment\Domain\ApplicationCheck;
use Src\Recruitment\Domain\ApplicationCheckRepository;
use Src\Recruitment\Domain\CheckTypeRepository;

/** The checks of one of the applicant's own applications, for the detail of the application. Empty while the feature is switched off. */
final readonly class MyApplicationChecks
{
    public function __construct(private ChecksAccess $access, private ApplicationCheckRepository $checks, private CheckTypeRepository $types) {}

    /** @return list<MyCheck> */
    public function of(int $applicationId): array
    {
        if (! $this->access->enabled()) {
            return [];
        }

        $checks = $this->checks->ofApplication($applicationId);
        $types = $this->types->byIds(array_values(array_unique(array_map(static fn (ApplicationCheck $check): int => $check->checkTypeId, $checks))));
        $mine = [];

        foreach ($checks as $check) {
            $type = $types[$check->checkTypeId] ?? null;

            if ($type !== null) {
                $mine[] = new MyCheck(
                    (int) $check->id, $type->name, $type->kind, $check->status, $check->scheduledAt, $check->place, $check->vendor, $type->instructions, $check->noteToCandidate,
                    $check->consentRequired, $check->consentedAt, $check->consentRefusedAt,
                );
            }
        }

        return $mine;
    }
}
