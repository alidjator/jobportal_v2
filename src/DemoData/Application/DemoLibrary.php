<?php

namespace Src\DemoData\Application;

use Src\Shared\Application\Actor;

/** The system library of skill tests (`DemoLibraryContent`): the platform admin writes and publishes them, so every company can send them. */
final readonly class DemoLibrary
{
    public function __construct(private DemoTestWriter $writer) {}

    /** @return int how many tests were published */
    public function build(Actor $admin): int
    {
        foreach (DemoLibraryContent::tests() as $content) {
            $this->writer->write($admin, $content);
        }

        return count(DemoLibraryContent::tests());
    }
}
