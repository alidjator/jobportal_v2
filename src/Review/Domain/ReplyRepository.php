<?php

namespace Src\Review\Domain;

use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface ReplyRepository
{
    /** Stores a new reply or the changes of an existing one (one reply per review). */
    public function save(Reply $reply): Reply;

    public function delete(int $reviewId): void;

    public function find(int $reviewId, bool $forUpdate = false): ?Reply;

    /**
     * @param  list<int>  $reviewIds
     * @return array<int, Reply> by review id
     */
    public function forReviews(array $reviewIds): array;

    /** @return Page<Reply> newest first; a null status means every status */
    public function page(?ReplyStatus $status, PageRequest $request): Page;
}
