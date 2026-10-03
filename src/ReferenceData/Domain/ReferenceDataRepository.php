<?php

namespace Src\ReferenceData\Domain;

interface ReferenceDataRepository
{
    /** @return list<array{id:int,name:?string}> */
    public function lookups(LookupType $type): array;

    /** @return list<array{id:int,name:?string,image:string,is_svg:bool}> */
    public function jobCategories(): array;

    /** @return list<array{id:int,name:?string,slug:null,jobs_count:int,tags_count:int}> */
    public function popularTags(int $limit): array;

    /** @return list<array{id:int,name:string,sortname:?string,status:int}> */
    public function countries(): array;

    /** @return array<string,mixed>|null seluruh kolom negara */
    public function country(int $id): ?array;

    /** @return array<string,mixed>|null negara pertama (default) */
    public function firstCountry(): ?array;

    /** @return list<array{id:int,name:?string,code:?string,symbol:?string}> */
    public function currencies(): array;

    /** @return array<string,mixed>|null */
    public function currency(string $code): ?array;

    /** @return list<array<string,mixed>> */
    public function languages(): array;

    /** @return array<string,mixed>|null */
    public function language(string $code): ?array;
}
