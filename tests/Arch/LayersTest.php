<?php

/*
 | The layers and the boundaries of the bounded contexts (`CLAUDE.md`, "Code standard"; `docs/design/architecture.md`), kept by Pest's architecture tests.
 |
 | Inside a context the dependencies point inwards: `Interface` and `Infrastructure` know `Application` and `Domain`, `Application` knows `Domain`, `Domain` knows nothing of the framework or of
 | the outer layers. Between contexts a class reaches another context only through `src/Shared` (ports and value objects), with one accepted exception: an `Infrastructure` listener or service
 | provider subscribes to the `Domain` events of another context. `src/Shared` knows no context. `src/LegacyImport` and `src/DemoData` are operational tools that reach into other contexts by
 | design (CLAUDE.md, "Code standard") and are not checked. The route files are not classes, so these tests do not see them.
 */

$contexts = array_values(array_filter(
    array_map('basename', glob(__DIR__.'/../../src/*', GLOB_ONLYDIR) ?: []),
    static fn (string $name): bool => ! in_array($name, ['Shared', 'LegacyImport', 'DemoData'], true),
));

foreach ($contexts as $context) {
    $others = array_values(array_diff($contexts, [$context]));
    $of = static fn (array $layers): array => array_merge(...array_map(
        static fn (string $other): array => array_map(static fn (string $layer): string => "Src\\{$other}\\{$layer}", $layers),
        $others,
    ));

    arch("{$context}: the domain knows no framework class, no outer layer and no other context")
        ->expect("Src\\{$context}\\Domain")
        ->not->toUse([
            'Illuminate',
            'App',
            "Src\\{$context}\\Application",
            "Src\\{$context}\\Infrastructure",
            "Src\\{$context}\\Interface",
            'Src\\Shared\\Infrastructure',
            'Src\\Shared\\Interface',
            ...$of(['Domain', 'Application', 'Infrastructure', 'Interface']),
        ]);

    arch("{$context}: the application layer knows no outer layer, no HTTP class and no other context")
        ->expect("Src\\{$context}\\Application")
        ->not->toUse([
            "Src\\{$context}\\Infrastructure",
            "Src\\{$context}\\Interface",
            'Illuminate\\Http',
            'Illuminate\\Routing',
            'Illuminate\\Foundation\\Http',
            ...$of(['Domain', 'Application', 'Infrastructure', 'Interface']),
        ]);

    arch("{$context}: the infrastructure reaches another context only through its domain events")
        ->expect("Src\\{$context}\\Infrastructure")
        ->not->toUse([
            ...$of(['Application', 'Infrastructure', 'Interface']),
        ]);

    arch("{$context}: only the service provider of the infrastructure knows the interface layer")
        ->expect("Src\\{$context}\\Infrastructure")
        ->not->toUse("Src\\{$context}\\Interface")
        ->ignoring("Src\\{$context}\\Infrastructure\\{$context}ServiceProvider");

    arch("{$context}: the interface layer knows no infrastructure class and no other context")
        ->expect("Src\\{$context}\\Interface")
        ->not->toUse([
            "Src\\{$context}\\Infrastructure",
            ...$of(['Domain', 'Application', 'Infrastructure', 'Interface']),
        ]);
}

arch('the shared kernel knows no context')
    ->expect('Src\\Shared')
    ->not->toUse(array_map(static fn (string $context): string => "Src\\{$context}", $contexts));

arch('no debugging function stays in the code')
    ->expect(['dd', 'ddd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
