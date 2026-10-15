<?php

namespace Src\Shared\Infrastructure\Documentation;

/** The text of the error catalog: the same Markdown is the file in `docs/api` and the "Errors" section of the OpenAPI document. */
final class ErrorCatalogDocument
{
    public static function section(ErrorCatalog $catalog): string
    {
        return <<<MD
        Every error is an RFC 9457 problem document (`application/problem+json`) with `type`, `title`, `status` and `detail`. The `type` is `urn:onesis:error:<code>`: a client decides what to do from the code, never from the text of `detail`, which may change. A `422` with `validation-failed` also carries `errors`, a map of field to messages; some business rules (`422`) carry the same member. A `429` carries a `Retry-After` header. The list is generated from the source (`php artisan api:error-catalog`).

        {$catalog->markdown()}
        MD;
    }

    public static function render(ErrorCatalog $catalog): string
    {
        return "# Error codes\n\n".self::section($catalog);
    }
}
