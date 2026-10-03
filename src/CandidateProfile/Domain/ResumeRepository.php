<?php

namespace Src\CandidateProfile\Domain;

interface ResumeRepository
{
    /** @return list<array<string,mixed>> terbaru lebih dulu */
    public function listFor(int $candidateId): array;

    /** @return array<string,mixed>|null null bila bukan milik kandidat */
    public function findOwned(int $candidateId, int $resumeId): ?array;

    /** @return array<string,mixed> (seperti model lama) */
    public function create(int $candidateId, string $name, string $file): array;

    /** @return array<string,mixed> */
    public function update(int $resumeId, string $name, ?string $file): array;

    public function delete(int $resumeId): void;
}
