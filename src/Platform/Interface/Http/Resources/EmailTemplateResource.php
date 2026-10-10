<?php

namespace Src\Platform\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Platform\Application\Email\EmailTemplateView;
use Src\Shared\Application\Email\EmailVariable;
use Src\Shared\Domain\TranslatedText;

/**
 * An email template for admins: its variables and, per language, the default text and the override if one exists.
 *
 * @property EmailTemplateView $resource
 */
final class EmailTemplateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $definition = $this->resource->definition;
        $languages = [];

        foreach (TranslatedText::LANGUAGES as $language) {
            $override = $this->resource->overrides[$language] ?? null;

            $languages[$language] = [
                'default' => [
                    'subject' => $definition->defaultSubject->toArray()[$language],
                    'body' => $definition->defaultBody->toArray()[$language],
                ],
                'override' => $override === null ? null : [
                    'subject' => $override->subject,
                    'body' => $override->body,
                    'updated_at' => $override->updatedAt->format(DateTimeInterface::ATOM),
                    'updated_by_admin_id' => $override->updatedByAdminId,
                ],
                'is_customized' => $override !== null,
            ];
        }

        return [
            'key' => $definition->key,
            'description' => $definition->description,
            /** @var list<array{name: string, description: string, sample: string, required: bool}> */
            'variables' => array_map(fn (EmailVariable $variable): array => [
                'name' => $variable->name,
                'description' => $variable->description,
                'sample' => $variable->sample,
                'required' => $variable->required,
            ], $definition->variables),
            'languages' => $languages,
        ];
    }
}
