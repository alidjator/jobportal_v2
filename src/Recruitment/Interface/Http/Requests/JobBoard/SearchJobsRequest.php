<?php

namespace Src\Recruitment\Interface\Http\Requests\JobBoard;

use Illuminate\Foundation\Http\FormRequest;
use Src\Recruitment\Domain\JobBoard\JobFilters;

/**
 * Query string pencarian lowongan. Aplikasi lama tidak memvalidasi filter ini;
 * nilai yang tidak valid (bukan skalar/angka) diabaikan, bukan menyebabkan error.
 */
class SearchJobsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    public function filters(): JobFilters
    {
        $radius = $this->number('radius');

        return new JobFilters(
            keyword: $this->text('keyword'),
            category: $this->text('category'),
            jobRole: $this->text('job_role'),
            jobType: $this->text('job_type'),
            experience: $this->text('experience'),
            education: $this->text('education'),
            company: $this->text('company'),
            tag: $this->text('tag'),
            location: $this->text('location'),
            lat: $this->number('lat'),
            long: $this->number('long'),
            radius: $radius !== null && $radius > 0 ? $radius : 50,
            priceMin: $this->number('price_min'),
            priceMax: $this->number('price_max'),
            isRemote: filter_var($this->input('is_remote'), FILTER_VALIDATE_BOOLEAN),
            sortBy: $this->text('sort_by'),
        );
    }

    /** `paginate` (1..100); `$default` bila tidak dikirim. */
    public function perPage(int $default): int
    {
        $value = $this->input('paginate');

        return is_numeric($value) ? max(1, min(100, (int) $value)) : $default;
    }

    public function pageNumber(): int
    {
        return max(1, (int) $this->input('page', 1));
    }

    private function text(string $key): ?string
    {
        $value = $this->input($key);

        return is_scalar($value) && trim((string) $value) !== '' ? (string) $value : null;
    }

    private function number(string $key): ?float
    {
        $value = $this->input($key);

        return is_numeric($value) ? (float) $value : null;
    }
}
