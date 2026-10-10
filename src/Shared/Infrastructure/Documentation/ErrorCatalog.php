<?php

namespace Src\Shared\Infrastructure\Documentation;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\AuthenticationFailed;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;
use Symfony\Component\HttpFoundation\Response;

/**
 * The list of error codes a client can receive (the `type` of the problem document is `urn:jobportal:error:<code>`). It is read from the source: every
 * `new <DomainException>(...)` with its code, the status of its class and the context it is raised in. A code passed through a helper method (a `$code`
 * parameter) is read from the calls of that method in the same file; a code that cannot be read is left out of the list (the test of the catalog file shows
 * the count of those).
 */
final class ErrorCatalog
{
    /** @var array<class-string, array{0: int, 1: int}> status and the position of the code among the positional arguments */
    private const array CLASSES = [
        AccessDenied::class => [Response::HTTP_FORBIDDEN, 1],
        AuthenticationFailed::class => [Response::HTTP_UNAUTHORIZED, 1],
        BusinessRuleViolation::class => [Response::HTTP_UNPROCESSABLE_ENTITY, 1],
        EntityNotFound::class => [Response::HTTP_NOT_FOUND, 1],
        ExternalServiceUnavailable::class => [Response::HTTP_SERVICE_UNAVAILABLE, 1],
        StateConflict::class => [Response::HTTP_CONFLICT, 1],
        TooManyAttempts::class => [Response::HTTP_TOO_MANY_REQUESTS, 2],
    ];

    /** The codes the HTTP layer itself produces. */
    private const array GENERIC = [
        ['validation-failed', 422, 'The request data is invalid. The `errors` member maps each field to its messages.'],
        ['unauthenticated', 401, 'Authentication is required.'],
        ['forbidden', 403, 'You are not allowed to perform this action.'],
        ['not-found', 404, 'The requested resource was not found.'],
        ['method-not-allowed', 405, 'The HTTP method is not allowed for this resource.'],
        ['too-many-requests', 429, 'Too many requests. Try again later. The `Retry-After` header tells how long to wait.'],
        ['internal-error', 500, 'An unexpected error occurred.'],
    ];

    /** The class of the file being read, so `self::CODE` can be resolved. */
    private ?string $self = null;

    public function __construct(private readonly string $sourceRoot) {}

    /** @return list<array{code: string, statuses: list<int>, contexts: list<string>, message: string}> sorted by code */
    public function entries(): array
    {
        $found = [];

        foreach (self::GENERIC as [$code, $status, $message]) {
            $found[$code] = ['code' => $code, 'statuses' => [$status => $status], 'contexts' => ['Platform'], 'message' => $message];
        }

        [$trees, $constants] = $this->parse();
        $finder = new NodeFinder;

        foreach ($trees as $path => $tree) {
            $context = explode('/', ltrim(substr($path, strlen($this->sourceRoot)), '/'))[0];
            $this->self = $finder->findFirstInstanceOf($tree, Class_::class)?->name?->toString();

            foreach ($finder->findInstanceOf($tree, Expr\New_::class) as $new) {
                $class = $this->classOf($new);

                if ($class === null) {
                    continue;
                }

                [$status, $position] = self::CLASSES[$class];
                $message = $this->literal($this->argument($new, 'message', 0), $constants);

                foreach ($this->codesOf($new, $position, $tree, $constants, $finder) as $code) {
                    $found[$code] ??= ['code' => $code, 'statuses' => [], 'contexts' => [], 'message' => (string) $message];
                    $found[$code]['statuses'][$status] = $status;
                    $found[$code]['contexts'][$context] = $context;

                    if ($found[$code]['message'] === '' && $message !== null) {
                        $found[$code]['message'] = $message;
                    }
                }
            }
        }

        ksort($found);

        return array_values(array_map(static function (array $entry): array {
            $contexts = array_values($entry['contexts']);
            $statuses = array_values($entry['statuses']);
            sort($contexts);
            sort($statuses);

            return [...$entry, 'contexts' => $contexts, 'statuses' => $statuses];
        }, $found));
    }

    public function markdown(): string
    {
        $lines = [
            '| Code | Status | Raised in | Message |',
            '|---|---|---|---|',
        ];

        foreach ($this->entries() as $entry) {
            $lines[] = sprintf('| `%s` | %s | %s | %s |', $entry['code'], implode(', ', $entry['statuses']), implode(', ', $entry['contexts']), str_replace('|', '\\|', $entry['message']));
        }

        return implode("\n", $lines)."\n";
    }

    /** @return array{0: array<string, array<Node\Stmt>>, 1: array<string, array<string, string>>} */
    private function parse(): array
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $finder = new NodeFinder;
        $trees = [];
        $constants = [];
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->sourceRoot, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        foreach ($files as $path) {
            $tree = $parser->parse((string) file_get_contents($path)) ?? [];
            $trees[$path] = $tree;

            foreach ($finder->findInstanceOf($tree, ClassLike::class) as $class) {
                foreach ($class->getConstants() as $constant) {
                    foreach ($constant->consts as $const) {
                        if ($const->value instanceof String_ && $class->name !== null) {
                            $constants[$class->name->toString()][$const->name->toString()] = $const->value->value;
                        }
                    }
                }
            }
        }

        return [$trees, $constants];
    }

    /** @return class-string|null */
    private function classOf(Expr\New_ $new): ?string
    {
        if (! $new->class instanceof Node\Name) {
            return null;
        }

        foreach (array_keys(self::CLASSES) as $class) {
            if ($new->class->getLast() === substr($class, (int) strrpos($class, '\\') + 1)) {
                return $class;
            }
        }

        return null;
    }

    private function argument(Expr\New_ $new, string $name, int $position): ?Expr
    {
        foreach ($new->args as $index => $arg) {
            if (! $arg instanceof Node\Arg) {
                continue;
            }

            if ($arg->name?->toString() === $name || ($arg->name === null && $index === $position)) {
                return $arg->value;
            }
        }

        return null;
    }

    /**
     * @param  array<Node\Stmt>  $tree
     * @param  array<string, array<string, string>>  $constants
     * @return list<string>
     */
    private function codesOf(Expr\New_ $new, int $position, array $tree, array $constants, NodeFinder $finder): array
    {
        $class = $this->classOf($new);
        $value = $this->argument($new, 'errorCode', $position);

        if ($value === null) {
            $default = constant($class.'::DEFAULT_CODE');

            return [(string) $default];
        }

        $literal = $this->literal($value, $constants);

        if ($literal !== null) {
            return [$literal];
        }

        if (! $value instanceof Expr\Variable || ! is_string($value->name)) {
            return [];
        }

        // A parameter of the enclosing method: read the literal codes of the calls of that method in this file.
        $method = $this->enclosingMethod($tree, $new, $finder);

        if ($method === null) {
            return [];
        }

        $index = null;

        foreach ($method->params as $i => $param) {
            if ($param->var instanceof Expr\Variable && $param->var->name === $value->name) {
                $index = $i;
            }
        }

        if ($index === null) {
            return [];
        }

        $codes = [];
        $calls = [...$finder->findInstanceOf($tree, Expr\MethodCall::class), ...$finder->findInstanceOf($tree, Expr\StaticCall::class)];

        foreach ($calls as $call) {
            if ($call->name instanceof Node\Identifier && $call->name->toString() === $method->name->toString() && isset($call->args[$index]) && $call->args[$index] instanceof Node\Arg) {
                $code = $this->literal($call->args[$index]->value, $constants);

                if ($code !== null) {
                    $codes[] = $code;
                }
            }
        }

        return $codes;
    }

    /** @param  array<Node\Stmt>  $tree */
    private function enclosingMethod(array $tree, Expr\New_ $new, NodeFinder $finder): ?ClassMethod
    {
        foreach ($finder->findInstanceOf($tree, ClassMethod::class) as $method) {
            if ($method->getStartLine() <= $new->getStartLine() && $method->getEndLine() >= $new->getEndLine()) {
                return $method;
            }
        }

        return null;
    }

    /** @param  array<string, array<string, string>>  $constants */
    private function literal(?Expr $value, array $constants): ?string
    {
        if ($value instanceof String_) {
            return $value->value;
        }

        if ($value instanceof Expr\ClassConstFetch && $value->class instanceof Node\Name && $value->name instanceof Node\Identifier) {
            $class = $value->class->getLast();
            $class = in_array($class, ['self', 'static'], true) ? (string) $this->self : $class;

            $name = $value->name->toString();

            if (isset($constants[$class][$name])) {
                return $constants[$class][$name];
            }

            // `self::NAME` of a class that implements or extends another one: the constant is read when its name belongs to one class only.
            $owners = array_filter($constants, static fn (array $list): bool => isset($list[$name]));

            return $class === (string) $this->self && count($owners) === 1 ? array_values($owners)[0][$name] : null;
        }

        return null;
    }
}
