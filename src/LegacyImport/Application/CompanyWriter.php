<?php

namespace Src\LegacyImport\Application;

/** Writes companies into the new schema (the Infrastructure implementation writes `company_companies` and `company_members` directly: the documented exception of this context). */
interface CompanyWriter
{
    public function slugTaken(string $slug): bool;

    /** Creates the company and makes its owner the company admin. @return int the id of the new company */
    public function insert(NewCompany $company): int;

    /** Sets the logo or banner of a company that has none (`$kind` is `logo` or `banner`); false when it already had one (nothing is overwritten). */
    public function setImageIfEmpty(int $companyId, string $kind, string $path): bool;
}
