<?php

namespace Src\Company\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Company\Application\Images\CompanyImageFile;
use Src\Shared\Application\Company\CompanyLetterhead;
use Src\Shared\Application\Company\CompanyLetterheads;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\References\LocationReferences;

final readonly class RepositoryCompanyLetterheads implements CompanyLetterheads
{
    public function __construct(private ConnectionInterface $connection, private FileStorage $files, private LocationReferences $locations) {}

    public function for(int $companyId): ?CompanyLetterhead
    {
        $row = $this->connection->table('company_companies')->where('id', $companyId)->first(['name', 'logo_path']);

        if ($row === null) {
            return null;
        }

        // The letterhead carries the street, city and province of the main work location.
        $main = $this->connection->table('company_locations')->where('company_id', $companyId)->where('is_main', true)->first(['address_line', 'province_id', 'city_id']);

        $city = $main?->city_id === null ? null : ($this->locations->cityNames([(int) $main->city_id])[(int) $main->city_id] ?? null);
        $province = $main?->province_id === null ? null : ($this->locations->provinceNames([(int) $main->province_id])[(int) $main->province_id] ?? null);
        $address = implode(', ', array_filter([$main?->address_line, $city, $province], static fn (mixed $part): bool => is_string($part) && trim($part) !== ''));

        return new CompanyLetterhead((string) $row->name, $address === '' ? null : $address, $this->logo($row->logo_path === null ? null : (string) $row->logo_path));
    }

    private function logo(?string $path): ?string
    {
        $contents = $path === null ? null : $this->files->get($path);

        return $contents === null ? null : 'data:'.CompanyImageFile::mimeType((string) $path).';base64,'.base64_encode($contents);
    }
}
