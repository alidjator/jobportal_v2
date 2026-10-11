<?php

namespace Src\Assessment\Domain;

interface AnswerRepository
{
    /** @return array<int, Answer> question id => answer */
    public function answersOf(int $attemptId): array;

    /** Stores the answer, replacing the one of the same question. */
    public function saveAnswer(int $attemptId, Answer $answer): void;

    public function deleteAnswer(int $attemptId, int $questionId): void;

    /** @param  array<int, float|null>  $scores  question id => score */
    public function saveScores(int $attemptId, array $scores): void;

    /** Stores the score a person gave to an answer (a long-text answer, by its assessor). */
    public function saveAssessorScore(int $attemptId, int $questionId, float $score, int $scoredByUserId): void;

    public function saveColumn(int $attemptId, SpeedColumnResult $result): void;

    /** @return list<SpeedColumnResult> by section and column */
    public function columnsOf(int $attemptId): array;

    /** Stores the drawing and returns the path of the one it replaced, if any. */
    public function saveDrawing(int $attemptId, Drawing $drawing): ?string;

    /** @return array<int, Drawing> question id => drawing */
    public function drawingsOf(int $attemptId): array;

    /** Stores the psychologist's score and note for a drawing. */
    public function scoreDrawing(int $attemptId, int $questionId, ?float $score, ?string $note): void;
}
