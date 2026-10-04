<?php

namespace Src\Platform\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Platform\Application\Settings\SettingView;
use Src\Shared\Application\Settings\SettingType;

/**
 * A setting for admins: how it is typed and constrained, its default, and the global value in force.
 *
 * @property SettingView $resource
 */
final class AdminSettingResource extends JsonResource
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
            'value' => $this->resource->value,
            'is_default' => $this->resource->isDefault,
            'overridable_per_company' => $definition->overridable,
            'constraints' => match ($definition->type) {
                SettingType::Integer => ['min' => $definition->min, 'max' => $definition->max],
                SettingType::Text => ['max_length' => $definition->max],
                SettingType::Choices => ['allowed_values' => $definition->allowedValues, 'min_items' => $definition->minItems],
                SettingType::Boolean => (object) [],
            },
            'updated_at' => $this->resource->updatedAt?->format(DateTimeInterface::ATOM),
            'updated_by_admin_id' => $this->resource->updatedByAdminId,
        ];
    }
}
