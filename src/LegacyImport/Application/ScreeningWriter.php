<?php

namespace Src\LegacyImport\Application;

/** Writes library questions and their attachment to jobs into the tables of the Recruitment context (the documented exception of this context). */
interface ScreeningWriter
{
    /** A question of the company's library; it is always a `text` question, and `$archivedAt` is set for one that was made for a single job. @return int the id of the new question */
    public function insertQuestion(int $companyId, string $text, int $creatorUserId, ?string $archivedAt, string $createdAt, string $updatedAt): int;

    /** How many questions are attached to the job so far. */
    public function attachedCount(int $jobId): int;

    /** Whether the question is already attached to the job (a pair can be attached once). */
    public function isAttached(int $jobId, int $questionId): bool;

    /** Attaches a question to a job (no ideal answer, never a must-have). @return int the id of the attachment */
    public function attach(int $jobId, int $questionId, int $position, bool $required, string $createdAt): int;
}
