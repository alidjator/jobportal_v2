<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\ScorecardEraser;

final readonly class DatabaseScorecardEraser implements ScorecardEraser
{
    public function __construct(private ConnectionInterface $connection) {}

    public function clearNotes(int $userId): void
    {
        $scorecards = $this->connection->table('recruitment_interview_scorecards')->whereIn(
            'interview_id',
            $this->connection->table('recruitment_interviews')->whereIn('application_id', $this->connection->table('recruitment_applications')->where('user_id', $userId)->select('id'))->select('id'),
        );

        $this->connection->table('recruitment_interview_scorecard_answers')->whereIn('scorecard_id', (clone $scorecards)->select('id'))->whereNotNull('note')->update(['note' => null]);
        $this->connection->table('recruitment_interview_scorecard_revisions')->whereIn('scorecard_id', (clone $scorecards)->select('id'))->delete();
        $scorecards->whereNotNull('note')->update(['note' => null]);
    }
}
