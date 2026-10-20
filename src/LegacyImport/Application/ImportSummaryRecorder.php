<?php

namespace Src\LegacyImport\Application;

/**
 * What the import did or did not carry for one job seeker or one company, collected while the importers run and written at the end of a real run
 * (`docs/design/phase-8-activation.md` section 5). An item is a code with a count and, optionally, counts per reason (`detail`); never a name, an email or any other
 * personal content. Keyed by the new user id or the new company id.
 */
final class ImportSummaryRecorder
{
    /** @var array<int, array<string, array{count: int, detail: array<string, int>}>> */
    private array $jobSeekers = [];

    /** @var array<int, array<string, array{count: int, detail: array<string, int>}>> */
    private array $companies = [];

    /** Every imported job seeker gets a summary (the static notes apply to all), also when nothing was left out. */
    public function openJobSeeker(int $userId): void
    {
        $this->jobSeekers[$userId] ??= [];
    }

    public function openCompany(int $companyId): void
    {
        $this->companies[$companyId] ??= [];
    }

    /** Adds `$count` to the item, and to its `$detail` when given; with `$inTotal` false only the detail grows (a property of rows the item already counts). */
    public function jobSeeker(int $userId, string $code, ?string $detail = null, int $count = 1, bool $inTotal = true): void
    {
        $this->add($this->jobSeekers, $userId, $code, $detail, $count, $inTotal);
    }

    public function company(int $companyId, string $code, ?string $detail = null, int $count = 1, bool $inTotal = true): void
    {
        $this->add($this->companies, $companyId, $code, $detail, $count, $inTotal);
    }

    /** @return array<int, list<array{code: string, count: int, detail?: array<string, int>}>> the items per user id */
    public function jobSeekers(): array
    {
        return array_map($this->list(...), $this->jobSeekers);
    }

    /** @return array<int, list<array{code: string, count: int, detail?: array<string, int>}>> the items per company id */
    public function companies(): array
    {
        return array_map($this->list(...), $this->companies);
    }

    /** @param  array<int, array<string, array{count: int, detail: array<string, int>}>>  $store */
    private function add(array &$store, int $ownerId, string $code, ?string $detail, int $count, bool $inTotal): void
    {
        $store[$ownerId][$code] ??= ['count' => 0, 'detail' => []];
        $store[$ownerId][$code]['count'] += $inTotal ? $count : 0;

        if ($detail !== null) {
            $store[$ownerId][$code]['detail'][$detail] = ($store[$ownerId][$code]['detail'][$detail] ?? 0) + $count;
        }
    }

    /**
     * @param  array<string, array{count: int, detail: array<string, int>}>  $items
     * @return list<array{code: string, count: int, detail?: array<string, int>}>
     */
    private function list(array $items): array
    {
        $list = [];

        foreach ($items as $code => $item) {
            $list[] = $item['detail'] === [] ? ['code' => $code, 'count' => $item['count']] : ['code' => $code, 'count' => $item['count'], 'detail' => $item['detail']];
        }

        return $list;
    }
}
