<?php

namespace Src\Company\Application\Commands;

use Illuminate\Http\UploadedFile;
use Src\Company\Domain\CompanyRepository;
use Src\Shared\Application\Contracts\FileStorage;

/** Satu langkah pengisian profil perusahaan (account progress). */
final class SubmitAccountProgress
{
    private const SUCCESS = 'Company profile updated successfully.';

    public function __construct(private CompanyRepository $companies, private FileStorage $storage) {}

    /**
     * Langkah 1 (profil): kembalikan `CompanyResource` terbaru.
     *
     * @return array<string,mixed>
     */
    public function company(int $userId, ?string $name, string $bio, ?UploadedFile $logo, ?UploadedFile $banner): array
    {
        $this->companies->idForUser($userId);
        $current = $this->companies->images($userId);

        $logoPath = $logo ? $this->storage->storeImage($logo, 'images/company') : null;
        $bannerPath = $banner ? $this->storage->storeImage($banner, 'images/company') : null;

        $this->companies->saveBasics($userId, $name, $bio, $logoPath, $bannerPath);

        if ($logoPath !== null) {
            $this->storage->delete($current['logo']);
        }
        if ($bannerPath !== null) {
            $this->storage->delete($current['banner']);
        }

        return $this->companies->profile($userId);
    }

    /**
     * @param  array{organization_type:string,industry_type:string,establishment_date:?string,team_size_id:?int,website:?string,vision:string}  $data
     */
    public function founding(int $userId, array $data): string
    {
        $this->companies->idForUser($userId);
        $this->companies->saveFounding($userId, $data);

        return self::SUCCESS;
    }

    /** @param  list<array{social_media:string,url:string}>  $links */
    public function social(int $userId, array $links): string
    {
        $this->companies->idForUser($userId);
        $this->companies->replaceSocialLinks($userId, $links);

        return self::SUCCESS;
    }

    /**
     * @param  array<string,mixed>  $contact
     * @param  array<string,mixed>  $location
     */
    public function contact(int $userId, array $contact, array $location): string
    {
        $this->companies->idForUser($userId);
        $this->companies->saveContact($userId, $contact, $location);

        return self::SUCCESS;
    }
}
