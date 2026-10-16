<?php

namespace Src\Organization\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Shared\Application\Settings\GroupEditableSetting;

/** @property GroupEditableSetting $resource */
final class GroupSettingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $setting = $this->resource;

        return [
            'key' => $setting->key,
            'type' => $setting->type,
            'description' => $setting->description,
            'value' => $setting->value,
            'default' => $setting->default,
            'min' => $setting->min,
            'max' => $setting->max,
            'allowed_values' => $setting->type === 'choices' ? $setting->allowedValues : null,
        ];
    }
}
