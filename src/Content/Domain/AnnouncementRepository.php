<?php

namespace Src\Content\Domain;

use DateTimeImmutable;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface AnnouncementRepository
{
    public function find(int $id): ?Announcement;

    /** Inserts (no id) or updates the announcement and returns it with its id. */
    public function save(Announcement $announcement): Announcement;

    public function delete(int $id): void;

    /** @return Page<Announcement> newest start first */
    public function page(PageRequest $request): Page;

    /**
     * The announcements live at `$now` for these audiences, important ones first, then the latest start.
     *
     * @param  list<AnnouncementAudience>  $audiences
     * @return list<Announcement>
     */
    public function live(array $audiences, DateTimeImmutable $now, int $limit): array;
}
