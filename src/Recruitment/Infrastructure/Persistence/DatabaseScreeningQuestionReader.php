<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Application\ScreeningQuestionListQuery;
use Src\Recruitment\Application\ScreeningQuestionReader;
use Src\Recruitment\Domain\ScreeningQuestionRepository;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Infrastructure\Persistence\TextSearch;

final readonly class DatabaseScreeningQuestionReader implements ScreeningQuestionReader
{
    public function __construct(private ConnectionInterface $connection, private ScreeningQuestionRepository $questions) {}

    public function page(ScreeningQuestionListQuery $query, PageRequest $request): Page
    {
        $filtered = $this->connection->table('recruitment_screening_questions')->where('company_id', $query->companyId);

        $query->archived ? $filtered->whereNotNull('archived_at') : $filtered->whereNull('archived_at');

        if ($query->search !== null && $query->search !== '') {
            TextSearch::contains($filtered, 'text', $query->search);
        }

        $total = (clone $filtered)->count();
        $ids = array_map('intval', $filtered->orderByDesc('id')->offset($request->offset())->limit($request->perPage)->pluck('id')->all());
        $found = $this->questions->findMany($ids);

        return new Page(array_values(array_filter(array_map(static fn (int $id) => $found[$id] ?? null, $ids))), $total, $request);
    }
}
