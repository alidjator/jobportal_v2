<?php

namespace Src\ReferenceData\Interface\Http\Controllers;

use Src\ReferenceData\Application\Queries\FindCountry;
use Src\ReferenceData\Application\Queries\FindCurrency;
use Src\ReferenceData\Application\Queries\FindLanguage;
use Src\ReferenceData\Application\Queries\GetCurrentSession;
use Src\ReferenceData\Application\Queries\GetLocalizationStrings;
use Src\ReferenceData\Application\Queries\GetTranslations;
use Src\ReferenceData\Application\Queries\ListCountries;
use Src\ReferenceData\Application\Queries\ListCurrencies;
use Src\ReferenceData\Application\Queries\ListJobCategories;
use Src\ReferenceData\Application\Queries\ListLanguages;
use Src\ReferenceData\Application\Queries\ListLookups;
use Src\ReferenceData\Application\Queries\ListPopularTags;
use Src\ReferenceData\Domain\LookupType;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\ApiResponse;

class ReferenceDataController extends ApiController
{
    public function languages(ListLanguages $query)
    {
        return ApiResponse::success($query());
    }

    /** Stateless: tidak menyimpan apa pun; klien mengirim `X-Language` pada request berikutnya. */
    public function changeLanguage(string $code, FindLanguage $query)
    {
        return ApiResponse::success([
            'message' => 'Language changed successfully',
            'current_language' => $query($code),
        ]);
    }

    public function translations(GetTranslations $query)
    {
        return ApiResponse::success($query());
    }

    public function localizationStrings(string $locale, GetLocalizationStrings $query)
    {
        return ApiResponse::success($query($locale));
    }

    public function countries(ListCountries $query)
    {
        return ApiResponse::success($query());
    }

    /** Stateless: klien mengirim header `selected_country` / `X-Country`. */
    public function changeCountry(int $countryId, FindCountry $query)
    {
        return ApiResponse::success([
            'message' => 'Country changed successfully',
            'current_country' => $query($countryId),
        ]);
    }

    public function currencies(ListCurrencies $query)
    {
        return ApiResponse::success($query());
    }

    /** Stateless: klien mengirim header `X-Currency`. */
    public function changeCurrency(string $code, FindCurrency $query)
    {
        return ApiResponse::success([
            'message' => 'Currency changed successfully',
            'current_currency' => $query($code),
        ]);
    }

    public function categories(ListJobCategories $query)
    {
        return ApiResponse::success($query());
    }

    public function jobRoles(ListLookups $query)
    {
        return ApiResponse::success($query(LookupType::JobRole));
    }

    public function experiences(ListLookups $query)
    {
        return ApiResponse::success($query(LookupType::Experience));
    }

    public function educations(ListLookups $query)
    {
        return ApiResponse::success($query(LookupType::Education));
    }

    public function jobTypes(ListLookups $query)
    {
        return ApiResponse::success($query(LookupType::JobType));
    }

    public function professions(ListLookups $query)
    {
        return ApiResponse::success($query(LookupType::Profession));
    }

    public function popularTags(ListPopularTags $query)
    {
        return ApiResponse::success($query());
    }

    public function currentSession(GetCurrentSession $query)
    {
        return ApiResponse::success($query());
    }
}
