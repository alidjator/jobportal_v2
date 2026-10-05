<?php

namespace Src\Platform\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Platform\Application\Settings\CompanySettingView;
use Src\Shared\Application\Settings\SettingType;

/**
 * A setting a company may override, as an admin sees it for one company: how it is typed and constrained, the global value, the company's own
 * value (null when it has none) and the value that applies to the company.
 *
 * @property CompanySettingView $resource
 */
final class AdminCompanySettingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $definition = $this->resource->definition;

        return [
            'key' => $definition->key,
            'type' => $definition->type->value,
            'description' => $definition->description,
            'default' => $definition->default,
            'global_value' => $this->resource->globalValue,
            'override' => $this->resource->override,
            'has_override' => $this->resource->hasOverride,
            'value' => $this->resource->effectiveValue,
            'constraints' => match ($definition->type) {
                SettingType::Integer => ['min' => $definition->min, 'max' => $definition->max],
                SettingType::Text => ['max_length' => $definition->max],
                SettingType::Choices => ['allowed_values' => $definition->allowedValues, 'min_items' => $definition->minItems],
                SettingType::Boolean => (object) [],
            },
        ];
    }
}
