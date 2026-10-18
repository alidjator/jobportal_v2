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
        $row = $this->connection->table('company_companies')->where('id', $companyId)->first(['name', 'logo_path', 'address_line', 'province_id', 'city_id']);

        if ($row === null) {
            return null;
        }

        $city = $row->city_id === null ? null : ($this->locations->cityNames([(int) $row->city_id])[(int) $row->city_id] ?? null);
        $province = $row->province_id === null ? null : ($this->locations->provinceNames([(int) $row->province_id])[(int) $row->province_id] ?? null);
        $address = implode(', ', array_filter([$row->address_line, $city, $province], static fn (mixed $part): bool => is_string($part) && trim($part) !== ''));

        return new CompanyLetterhead((string) $row->name, $address === '' ? null : $address, $this->logo($row->logo_path === null ? null : (string) $row->logo_path));
    }

    private function logo(?string $path): ?string
    {
        $contents = $path === null ? null : $this->files->get($path);

        return $contents === null ? null : 'data:'.CompanyImageFile::mimeType((string) $path).';base64,'.base64_encode($contents);
    }
}
