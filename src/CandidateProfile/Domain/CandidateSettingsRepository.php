<?php

namespace Src\CandidateProfile\Domain;

interface CandidateSettingsRepository
{
    /** @return array<string,mixed> */
    public function personal(int $userId, int $candidateId): array;

    /** @return array<string,mixed> */
    public function personalSummary(int $userId, int $candidateId): array;

    /** @return array<string,mixed> */
    public function profile(int $candidateId): array;

    /** @return array<string,mixed> */
    public function social(int $userId): array;

    /** @return list<array{id:int,social_media:string,url:string}> */
    public function socialLinks(int $userId): array;

    /** @return array<string,mixed> */
    public function contact(int $userId, int $candidateId): array;

    /** Path foto yang tersimpan di DB (bukan URL). */
    public function storedPhoto(int $candidateId): ?string;

    /**
     * @param  array{name:string,title:string,experience_id:int,education_id:int,website:?string,birth_date:string}  $data
     */
    public function updatePersonal(int $userId, int $candidateId, array $data, ?string $newPhoto): void;

    /**
     * @param  array<string,mixed>  $data  gender, marital_status, bio, profession, status, available_in
     * @param  list<string>  $skills  id atau nama skill
     * @param  list<int>  $languages
     */
    public function updateProfile(int $candidateId, array $data, array $skills, array $languages): void;

    /** @param  list<array{social_media:string,url:string}>  $links */
    public function replaceSocial(int $userId, array $links): void;

    /**
     * @param  array<string,mixed>  $data
     */
    public function updateContact(int $userId, int $candidateId, array $data): void;
}
