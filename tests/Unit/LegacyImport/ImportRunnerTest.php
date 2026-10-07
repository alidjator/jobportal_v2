<?php

namespace Tests\Unit\LegacyImport;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Src\LegacyImport\Application\ImportContext;
use Src\LegacyImport\Application\Importer;
use Src\LegacyImport\Application\ImportRunner;
use Src\LegacyImport\Application\ReportWriter;
use Src\LegacyImport\Domain\IdMap;
use Src\Shared\Application\Clock;

final class ImportRunnerTest extends TestCase
{
    /** @var list<string> */
    private array $ran = [];

    /** @var list<bool> */
    private array $writes = [];

    /** @var array<string, mixed>|null */
    private ?array $written = null;

    /** @param  list<string>  $dependsOn */
    private function importer(string $entity, array $dependsOn = []): Importer
    {
        $test = $this;

        return new class($entity, $dependsOn, function (string $entity, ImportContext $context) use ($test): void {
            $test->record($entity, $context);
        }) implements Importer
        {

            /** @param  list<string>  $dependsOn */
            public function __construct(private string $entity, private array $dependsOn, private $callback) {}

            public function entity(): string
            {
                return $this->entity;
            }

            public function dependsOn(): array
            {
                return $this->dependsOn;
            }

            public function run(ImportContext $context): void
            {
                ($this->callback)($this->entity, $context);
            }
        };
    }

    public function record(string $entity, ImportContext $context): void
    {
        $this->ran[] = $entity;
        $this->writes[] = $context->write;
        $context->report->read($entity, 2);
        $context->report->imported($entity);
        $context->report->skipped($entity, 7, 'no-match');
    }

    /** @param  list<Importer>  $importers */
    private function runner(array $importers): ImportRunner
    {
        $reports = new class($this) implements ReportWriter
        {
            public function __construct(private ImportRunnerTest $test) {}

            public function write(array $report, string $runName): string
            {
                $this->test->setWritten($report);

                return "reports/{$runName}.json";
            }
        };
        $clock = new class implements Clock
        {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-10-07 09:30:00');
            }
        };
        $idMap = new class implements IdMap
        {
            public function find(string $entity, int $legacyId): ?int
            {
                return null;
            }

            public function findMany(string $entity, array $legacyIds): array
            {
                return [];
            }

            public function remember(string $entity, int $legacyId, int $newId, DateTimeImmutable $at): void {}

            public function replace(string $entity, array $newIdsByLegacyId, DateTimeImmutable $at): void {}
        };

        return new ImportRunner($importers, $idMap, $clock, $reports);
    }

    /** @param  array<string, mixed>  $report */
    public function setWritten(array $report): void
    {
        $this->written = $report;
    }

    public function test_all_runs_every_importer_after_the_ones_it_depends_on(): void
    {
        $runner = $this->runner([$this->importer('applications', ['jobs', 'accounts']), $this->importer('jobs', ['accounts']), $this->importer('accounts')]);

        $result = $runner->run(null, false);

        $this->assertSame(['accounts', 'jobs', 'applications'], $this->ran);
        $this->assertSame(['accounts', 'jobs', 'applications'], $runner->entities());
        $this->assertSame('reports/20261007-093000-all-dry-run.json', $result['path']);
    }

    public function test_one_entity_runs_alone(): void
    {
        $runner = $this->runner([$this->importer('accounts'), $this->importer('jobs', ['accounts'])]);

        $result = $runner->run('jobs', true);

        $this->assertSame(['jobs'], $this->ran);
        $this->assertSame([true], $this->writes);
        $this->assertSame('reports/20261007-093000-jobs-write.json', $result['path']);
    }

    public function test_a_run_does_not_write_unless_asked_to(): void
    {
        $this->runner([$this->importer('accounts')])->run(null, false);

        $this->assertSame([false], $this->writes);
        $this->assertSame('dry-run', $this->written['mode']);
    }

    public function test_the_report_counts_and_names_skipped_rows_by_id_and_reason(): void
    {
        $this->runner([$this->importer('accounts')])->run(null, true);

        $this->assertSame('write', $this->written['mode']);
        $this->assertSame(
            ['read' => 2, 'imported' => 1, 'skipped' => 1, 'skipped_rows' => [['legacy_id' => 7, 'reason' => 'no-match']], 'notes' => [], 'unmatched' => []],
            $this->written['entities']['accounts'],
        );
    }

    public function test_an_unknown_entity_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown entity "nothing"');

        $this->runner([$this->importer('accounts')])->run('nothing', false);
    }

    public function test_a_missing_dependency_or_a_circle_is_refused(): void
    {
        try {
            $this->runner([$this->importer('jobs', ['accounts'])])->run(null, false);
            $this->fail('A missing dependency was accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('"accounts", which does not exist', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('circle');

        $this->runner([$this->importer('a', ['b']), $this->importer('b', ['a'])])->run(null, false);
    }
}
