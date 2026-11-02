<?php

namespace Src\Assessment\Application\Example;

use Src\Assessment\Application\Catalog\GetCatalogTest;
use Src\Assessment\Domain\ExampleReportRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A company member downloads the example report of a battery they may send; a battery they may not use, one without an example and a file that has gone are the same 404 as a test that does not exist. */
final readonly class DownloadExampleReport
{
    public function __construct(private GetCatalogTest $catalog, private ExampleReportRepository $examples, private FileStorage $files) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $testId): ExampleReportFile
    {
        $this->catalog->execute($actor, $testId);

        $example = $this->examples->find($testId) ?? throw EntityNotFound::of('Example report', $testId);
        $contents = $this->files->get($example->path) ?? throw EntityNotFound::of('Example report', $testId);

        return new ExampleReportFile($example->originalName, $contents);
    }
}
