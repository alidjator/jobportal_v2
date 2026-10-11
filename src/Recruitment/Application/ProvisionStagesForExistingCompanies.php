<?php

namespace Src\Recruitment\Application;

use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Recruitment\DefaultStageProvisioner;
use Src\Shared\Application\TransactionManager;

/** Gives the default stages to every company that lacks them: all of them to a company that has none, the `assessment` one to a company created before that type existed. Safe to run again. */
final readonly class ProvisionStagesForExistingCompanies
{
    private const int BATCH = 200;

    public function __construct(
        private CompanyDirectory $companies,
        private DefaultStageProvisioner $provisioner,
        private TransactionManager $transactions,
    ) {}

    /** @return int The number of companies that were checked. */
    public function execute(): int
    {
        $checked = 0;
        $after = 0;

        while (($ids = $this->companies->idsAfter($after, self::BATCH)) !== []) {
            foreach ($ids as $id) {
                $this->transactions->run(fn () => $this->provisioner->provision($id));
            }

            $checked += count($ids);
            $after = $ids[array_key_last($ids)];
        }

        return $checked;
    }
}
