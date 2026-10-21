<?php

namespace Tests\Unit\Shared;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Tests\TestCase;

/**
 * Every field key a form request validates has an Indonesian name in `lang/id/validation.php` (`docs/design/phase-8-api-translations.md`), so a
 * validation message never shows a raw key such as `filter.q`. The rules are read from each request built on an empty route.
 */
final class ValidationAttributesTest extends TestCase
{
    /** @return array<string, list<string>> the field keys, with the requests that validate them */
    private function ruleKeys(): array
    {
        $root = base_path('src');
        $keys = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $path = $file->getPathname();

            if ($file->getExtension() !== 'php' || ! str_contains($path, '/Interface/Http/Requests/')) {
                continue;
            }

            $class = 'Src\\'.str_replace(['/', '.php'], ['\\', ''], substr($path, strlen($root) + 1));
            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(FormRequest::class)) {
                continue;
            }

            /** @var FormRequest $request */
            $request = $class::create('/probe', 'POST');
            $request->setContainer($this->app);
            $route = (new Route('POST', '/probe', []))->bind($request);
            $request->setRouteResolver(fn (): Route => $route);

            foreach (array_keys($this->app->call([$request, 'rules'])) as $key) {
                $keys[(string) $key][] = $reflection->getShortName();
            }
        }

        return $keys;
    }

    public function test_every_validated_field_has_an_indonesian_name(): void
    {
        $names = (require base_path('lang/id/validation.php'))['attributes'];
        $keys = $this->ruleKeys();
        $missing = array_diff_key($keys, $names);

        $this->assertGreaterThan(400, count($keys), 'the rules of the form requests are read');
        $this->assertSame([], array_map(static fn (array $requests): string => implode(', ', array_unique($requests)), $missing), 'fields without an Indonesian name');
    }

    public function test_the_indonesian_lines_cover_every_english_rule(): void
    {
        $english = require base_path('lang/en/validation.php');
        $indonesian = require base_path('lang/id/validation.php');

        foreach ($english as $rule => $line) {
            if (in_array($rule, ['custom', 'attributes'], true)) {
                continue;
            }

            $this->assertArrayHasKey($rule, $indonesian, $rule);

            if (is_array($line)) {
                $this->assertSame(array_keys($line), array_keys($indonesian[$rule]), $rule);
            }
        }
    }
}
