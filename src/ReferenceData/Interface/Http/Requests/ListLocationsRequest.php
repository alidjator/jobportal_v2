<?php

namespace Src\ReferenceData\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\ReferenceData\Application\Locations\LocationCatalog;
use Src\ReferenceData\Application\Locations\LocationLevel;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The only allowed filter is the parent column of the level (`filter[province_id]` for cities, and so on); the provinces have none. */
final class ListLocationsRequest extends FormRequest
{
    public function level(): LocationLevel
    {
        $key = $this->route('level');

        return (is_string($key) ? LocationCatalog::find($key) : null) ?? throw EntityNotFound::of('Location level', is_string($key) ? $key : null);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        // Without a `level` route parameter (the API documentation reads the rules this way) the filter is left open.
        if ($this->route('level') === null) {
            return ['filter' => ['nullable', 'array']];
        }

        $parent = $this->level()->parentColumn;

        if ($parent === null) {
            return ['filter' => ['prohibited']];
        }

        return [
            'filter' => ['nullable', 'array:'.$parent],
            "filter.{$parent}" => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function parentId(): ?int
    {
        $parent = $this->level()->parentColumn;

        return $parent !== null && $this->filled("filter.{$parent}") ? $this->integer("filter.{$parent}") : null;
    }
}
