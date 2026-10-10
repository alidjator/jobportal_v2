<?php

namespace Src\Shared\Infrastructure\Documentation;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

/**
 * The OpenAPI document of the API (Scramble). The two Sanctum guards are documented as two bearer schemes, an admin route lists the permission it needs, and the
 * pages of the document are not served in production (the exported `api.json` is what is handed to the client teams).
 */
final class ApiDocumentation
{
    public const string USER_SCHEME = 'userToken';

    public const string ADMIN_SCHEME = 'adminToken';

    public static function configure(bool $production): void
    {
        $config = Scramble::configure();

        if ($production) {
            $config->expose(false);
        }

        $config->withDocumentTransformers(static function (OpenApi $openApi): void {
            $openApi->info->setDescription(trim($openApi->info->description."\n\n## Errors\n\n".ErrorCatalogDocument::section(new ErrorCatalog(base_path('src')))));
            $openApi->components->addSecurityScheme(self::USER_SCHEME, SecurityScheme::http('bearer')->as(self::USER_SCHEME)->setDescription('Token of a job seeker or a company member (`auth:user`).'));
            $openApi->components->addSecurityScheme(self::ADMIN_SCHEME, SecurityScheme::http('bearer')->as(self::ADMIN_SCHEME)->setDescription('Token of an administrator (`auth:admin`).'));
        });

        $config->withOperationTransformers(static function (Operation $operation, RouteInfo $routeInfo): void {
            $middleware = $routeInfo->route->gatherMiddleware();
            $scheme = match (true) {
                in_array('auth:admin', $middleware, true) => self::ADMIN_SCHEME,
                in_array('auth:user', $middleware, true) => self::USER_SCHEME,
                default => null,
            };

            $operation->security = null;
            $operation->addSecurity($scheme === null ? [] : new SecurityRequirement([$scheme => []]));

            $permissions = array_map(
                static fn (string $item): string => Str::after($item, 'can:'),
                array_values(array_filter(array_filter($middleware, 'is_string'), static fn (string $item): bool => str_starts_with($item, 'can:'))),
            );

            if ($permissions !== []) {
                $operation->description(trim($operation->description."\n\nRequires the permission `".implode('`, `', $permissions).'`.'));
            }
        });
    }
}
