<?php

namespace Src\Shared\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Application\Portal\PortalBrandValues;

/** The whole brand of a career site, as the draft of a company or group; a blank text clears it (the site shows its default), a missing appearance setting is its default. Shared by the company and group screens. */
final class PortalBrandRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:'.PortalBrandValues::NAME_MAX],
            'tagline_id' => ['nullable', 'string', 'max:'.PortalBrandValues::TAGLINE_MAX],
            'tagline_en' => ['nullable', 'string', 'max:'.PortalBrandValues::TAGLINE_MAX],
            'welcome_id' => ['nullable', 'string', 'max:'.PortalBrandValues::WELCOME_MAX],
            'welcome_en' => ['nullable', 'string', 'max:'.PortalBrandValues::WELCOME_MAX],
            'preset' => ['required', 'integer', 'between:1,'.PortalBrandValues::PRESETS],
            'theme_mode' => ['sometimes', 'string', 'in:'.implode(',', PortalBrandValues::THEME_MODES)],
            'contrast' => ['sometimes', 'boolean'],
            'width' => ['sometimes', 'string', 'in:'.implode(',', PortalBrandValues::WIDTHS)],
            'header_color' => ['sometimes', 'boolean'],
        ];
    }

    public function brand(): PortalBrandValues
    {
        return PortalBrandValues::fromArray($this->validated());
    }
}
