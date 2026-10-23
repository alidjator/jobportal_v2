<?php

namespace Src\Shared\Infrastructure\Documentation;

use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;

/**
 * Scramble documents the literals `true` and `false` of a PHPDoc shape as a plain boolean, so the two shapes of the `applicant` block of
 * `CompanyApplicationDetailResource` (anonymised and full) could not be told apart by `is_deleted`. This pins the flag of each shape to its one value.
 */
final class ApplicantBlockDiscriminator
{
    private const string SCHEMA = 'CompanyApplicationDetailResource';

    public static function apply(OpenApi $openApi): void
    {
        $applicant = ($openApi->components->schemas[self::SCHEMA] ?? null)?->type->properties['applicant'] ?? null;

        if (! $applicant instanceof AnyOf) {
            return;
        }

        foreach ($applicant->items as $item) {
            if ($item instanceof ObjectType && isset($item->properties['is_deleted'])) {
                $item->properties['is_deleted']->enum([isset($item->properties['contact_hidden']) ? false : true]);
            }
        }
    }
}
