<?php

namespace Src\Company\Domain;

interface CompanyRepository
{
    /** ID perusahaan milik user. @throws \Src\Shared\Domain\Exceptions\EntityNotFound bila user tidak punya perusahaan. */
    public function idForUser(int $userId): int;

    /**
     * Bentuk `CompanyResource` aplikasi lama; `[]` bila user tidak punya perusahaan.
     *
     * @return array<string,mixed>
     */
    public function profile(int $userId): array;

    /** @return array<string,mixed> */
    public function dashboard(int $companyId): array;

    /** @return array<string,mixed> */
    public function accountProgress(int $userId): array;

    /** @return list<array{id:int,social_media:string,url:string}> */
    public function socialLinks(int $userId): array;

    /** @return array{logo:?string,banner:?string} path gambar yang tersimpan saat ini */
    public function images(int $userId): array;

    public function saveBasics(int $userId, ?string $name, string $bio, ?string $logo, ?string $banner): void;

    /**
     * @param  array{organization_type:string,industry_type:string,establishment_date:?string,team_size_id:?int,website:?string,vision:string}  $data
     */
    public function saveFounding(int $userId, array $data): void;

    /** @param  list<array{social_media:string,url:string}>  $links */
    public function replaceSocialLinks(int $userId, array $links): void;

    /**
     * @param  array<string,mixed>  $contact  phone, email, secondary_phone?, secondary_email?
     * @param  array<string,mixed>  $location  kolom lokasi perusahaan yang dikirim klien
     */
    public function saveContact(int $userId, array $contact, array $location): void;
}
