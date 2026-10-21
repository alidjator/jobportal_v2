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
 * The list of error codes a client can receive (the `type` of the problem document is `urn:onesis:error:<code>`). It is read from the source: every
 * `new <DomainException>(...)` with its code, the status of its class and the context it is raised in. A code passed through a helper method (a `$code`
 * parameter) is read from the calls of that method in the same file; a code that cannot be read is left out of the list (the test of the catalog file shows
 * the count of those).
 *
 * It also lists every message template a client can read, the translation keys of `lang/id.json` (`docs/design/phase-8-api-translations.md`): the
 * messages of those errors and of their `errors` member, the texts of the HTTP layer and the status texts used as `title`, and the closure messages of form
 * requests (`$fail('...')->translate()`) and the messages of `ValidationException::withMessages`. A message is read from a literal, a constant, the branches of a ternary or `match`, the values a variable is given
 * in the method, or the literal arguments of the calls of a helper method whose parameter it is (also `"{$entity} not found"`); anything else is counted
 * as unresolved.
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

    /** The texts the renderer writes itself (`ProblemDetailsRenderer`). */
    private const array RENDERER_TEXTS = [
        'The request data is invalid.', 'Authentication is required.', 'You are not allowed to perform this action.', 'The requested resource was not found.',
        'The HTTP method is not allowed for this resource.', 'Too many requests. Try again later.', 'An unexpected error occurred.',
        'The request could not be completed.', 'Error',
    ];

    /** The statuses whose text is a `title` of this API (the statuses of the catalog and of the framework's own HTTP errors). */
    private const array TITLE_STATUSES = [400, 401, 403, 404, 405, 409, 413, 415, 422, 429, 500, 503];

    /** How deep the calls of helper methods are followed to find a message. */
    private const int MAX_DEPTH = 3;

    /** @var array<string, true> the variables whose values are being read (`path:line:name`), so a variable built from itself (`$errors = [...$errors, ...]`) ends */
    private array $visiting = [];

    /** @var array<string, list<Node>> the nodes found per scope and class, so the many lookups of the message rules do not walk the same trees again */
    private array $found = [];

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

            foreach ($this->find($finder, $tree, Expr\New_::class) as $new) {
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

    /**
     * Every message template a client can read, and how many message expressions could not be read.
     *
     * @return array{templates: list<string>, unresolved: list<string>} the unresolved ones as `path:line`
     */
    public function templates(): array
    {
        [$trees, $constants] = $this->parse();
        $finder = new NodeFinder;
        $templates = array_fill_keys(self::RENDERER_TEXTS, true);
        $unresolved = [];

        foreach (self::TITLE_STATUSES as $status) {
            $templates[Response::$statusTexts[$status]] = true;
        }

        foreach ($trees as $path => $tree) {
            $this->self = $finder->findFirstInstanceOf($tree, Class_::class)?->name?->toString();

            foreach ($this->find($finder, $tree, Expr\New_::class) as $new) {
                $class = $this->classOf($new) ?? $this->selfClassOf($new);

                if ($class === null) {
                    continue;
                }

                $method = $this->enclosingMethod($tree, $new, $finder);
                $found = [$this->messagesOf($this->argument($new, 'message', 0), $method, $path, $trees, $constants, $finder, 0)];

                if ($class !== TooManyAttempts::class) {
                    $found[] = $this->errorMessagesOf($this->argument($new, 'extensions', 3), $method, $path, $trees, $constants, $finder);
                }

                foreach ($found as $messages) {
                    if ($messages === null) {
                        $unresolved[] = substr($path, strlen($this->sourceRoot) + 1).':'.$new->getStartLine();

                        continue;
                    }

                    foreach ($messages as $message) {
                        $templates[$message] = true;
                    }
                }
            }

            foreach ($this->find($finder, $tree, Expr\StaticCall::class) as $call) {
                if ($call->class instanceof Node\Name && $call->class->getLast() === 'ValidationException' && $call->name instanceof Node\Identifier
                    && $call->name->toString() === 'withMessages' && ($call->args[0] ?? null) instanceof Node\Arg) {
                    foreach ($this->listMessages($call->args[0]->value, $this->enclosingMethod($tree, $call, $finder), $path, $trees, $constants, $finder) ?? [] as $message) {
                        $templates[$message] = true;
                    }
                }
            }

            foreach ($this->find($finder, $tree, Expr\MethodCall::class) as $call) {
                if ($call->name instanceof Node\Identifier && $call->name->toString() === 'translate' && $call->var instanceof Expr\FuncCall
                    && $call->var->name instanceof Expr\Variable && isset($call->var->args[0]) && $call->var->args[0] instanceof Node\Arg) {
                    $text = $this->literal($call->var->args[0]->value, $constants);

                    if ($text !== null) {
                        $templates[$text] = true;
                    }
                }
            }
        }

        unset($templates['']);
        $list = array_map('strval', array_keys($templates));
        sort($list);

        return ['templates' => $list, 'unresolved' => array_values(array_unique($unresolved))];
    }

    /**
     * The templates of a translation file that has none of them, in the order of the list.
     *
     * @param  array<string, string>  $translations
     * @return list<string>
     */
    public function untranslated(array $translations): array
    {
        return array_values(array_filter($this->templates()['templates'], static fn (string $template): bool => ! isset($translations[$template]) || $translations[$template] === ''));
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
        // New trees: the remembered lookups belong to the old ones (and object ids may be reused).
        $this->found = [];
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

            foreach ($this->find($finder, $tree, ClassLike::class) as $class) {
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

    /**
     * The messages an expression can hold, or null when one of them cannot be read.
     *
     * @param  array<string, array<Node\Stmt>>  $trees
     * @param  array<string, array<string, string>>  $constants
     * @return list<string>|null
     */
    private function messagesOf(?Expr $value, ?ClassMethod $method, string $path, array $trees, array $constants, NodeFinder $finder, int $depth): ?array
    {
        if ($value === null) {
            return [];
        }

        $literal = $this->literal($value, $constants);

        if ($literal !== null) {
            return [$literal];
        }

        if ($this->readsExtensions($value) || ($value instanceof Expr\ConstFetch && strtolower($value->name->toString()) === 'null')) {
            return [];
        }

        if ($value instanceof Expr\Cast\String_) {
            return $this->messagesOf($value->expr, $method, $path, $trees, $constants, $finder, $depth);
        }

        // The first message of a list (`reset($errors)`, `reset($errors)[0]`): the messages of the list.
        while ($value instanceof Expr\ArrayDimFetch && $value->var instanceof Expr\FuncCall) {
            $value = $value->var;
        }

        $function = $value instanceof Expr\FuncCall && $value->name instanceof Node\Name ? $value->name->toString() : null;

        if (in_array($function, ['reset', 'current', 'end'], true) && ($value->args[0] ?? null) instanceof Node\Arg) {
            return $this->listMessages($value->args[0]->value, $method, $path, $trees, $constants, $finder, $depth);
        }

        // `array_map(fn (...) => new ErrorMessage(...), $ids)`: the messages the function makes; `array_map(fn ($text) => [$text], $errors)`: those of the list.
        if ($function === 'array_map' && ($value->args[0] ?? null) instanceof Node\Arg && $value->args[0]->value instanceof Expr\ArrowFunction) {
            $arrow = $value->args[0]->value;
            $parameter = $arrow->params[0]->var ?? null;
            $wraps = $parameter instanceof Expr\Variable && ($arrow->expr instanceof Expr\Variable && $arrow->expr->name === $parameter->name
                || $arrow->expr instanceof Expr\Array_ && count($arrow->expr->items) === 1 && $arrow->expr->items[0]->value instanceof Expr\Variable && $arrow->expr->items[0]->value->name === $parameter->name);

            if ($wraps && ($value->args[1] ?? null) instanceof Node\Arg) {
                return $this->listMessages($value->args[1]->value, $method, $path, $trees, $constants, $finder, $depth);
            }

            return $this->messagesOf($arrow->expr, $method, $path, $trees, $constants, $finder, $depth);
        }

        if ($value instanceof Expr\New_ && $value->class instanceof Node\Name && $value->class->getLast() === 'ErrorMessage') {
            return $this->messagesOf($this->argument($value, 'template', 0), $method, $path, $trees, $constants, $finder, $depth);
        }

        $branches = match (true) {
            $value instanceof Expr\Ternary => [$value->if ?? $value->cond, $value->else],
            $value instanceof Expr\BinaryOp\Coalesce => [$value->left, $value->right],
            $value instanceof Expr\Match_ => array_map(static fn (Node\MatchArm $arm): Expr => $arm->body, $value->arms),
            default => null,
        };

        if ($branches !== null) {
            $messages = [];

            foreach ($branches as $branch) {
                if ($branch instanceof Expr\Throw_) {
                    continue;
                }

                $found = $this->messagesOf($branch, $method, $path, $trees, $constants, $finder, $depth);

                if ($found === null) {
                    return null;
                }

                $messages = [...$messages, ...$found];
            }

            return $messages;
        }

        if ($value instanceof Expr\Variable && is_string($value->name) && $method !== null) {
            $index = $this->parameterIndex($method, $value->name);

            if ($index !== null) {
                return $this->fromCalls($method, $path, $trees, $constants, $finder, $depth, static fn (array $arguments): ?Expr => $arguments[$index] ?? null);
            }

            return $this->assignedMessages($value->name, $method, $path, $trees, $constants, $finder, $depth);
        }

        if ($value instanceof Node\Scalar\InterpolatedString && $method !== null) {
            return $this->interpolated($value, $method, $path, $trees, $constants, $finder, $depth);
        }

        $helper = $this->helperIn($value, $path, $trees, $finder);

        if ($helper !== null && $depth < self::MAX_DEPTH) {
            [$helper, $helperPath] = $helper;
            $messages = [];

            foreach ($this->find($finder, $helper->stmts ?? [], Node\Stmt\Return_::class) as $return) {
                $found = $return->expr === null ? [] : $this->listMessages($return->expr, $helper, $helperPath, $trees, $constants, $finder, $depth + 1);

                if ($found === null) {
                    return null;
                }

                $messages = [...$messages, ...$found];
            }

            return $messages;
        }

        return null;
    }

    /** Whether the expression copies messages out of another error (`$violation->extensions()['errors']['url']`, `$e->template()`): those are read where that error is raised. */
    private function readsExtensions(Expr $value): bool
    {
        while ($value instanceof Expr\ArrayDimFetch) {
            $value = $value->var;
        }

        return $value instanceof Expr\MethodCall && $value->name instanceof Node\Identifier && in_array($value->name->toString(), ['extensions', 'template'], true);
    }

    /**
     * The method that a `$this->name(...)` or `self::name(...)` call runs, with its file, so the messages it returns can be read.
     *
     * @param  array<string, array<Node\Stmt>>  $trees
     * @return array{0: ClassMethod, 1: string}|null
     */
    private function helperIn(Expr $value, string $path, array $trees, NodeFinder $finder): ?array
    {
        if (! ($value instanceof Expr\MethodCall || $value instanceof Expr\StaticCall) || ! $value->name instanceof Node\Identifier) {
            return null;
        }

        $own = ($value instanceof Expr\MethodCall && $value->var instanceof Expr\Variable && $value->var->name === 'this')
            || ($value instanceof Expr\StaticCall && $value->class instanceof Node\Name && in_array($value->class->toString(), ['self', 'static'], true));
        $found = [];

        // A method of another object (`$this->checks->problems()`): the one public method of that name in the source.
        foreach ($own ? [$path => $trees[$path]] : $trees as $treePath => $tree) {
            foreach ($this->find($finder, $tree, ClassMethod::class) as $method) {
                if ($method->name->toString() === $value->name->toString() && ($own || $method->isPublic())) {
                    $found[] = [$method, (string) $treePath];
                }
            }
        }

        return count($found) === 1 ? $found[0] : null;
    }

    /**
     * The messages a `foreach` value takes, when it loops over an array literal given in the method: `foreach ($messages as [$field, $message])`.
     *
     * @param  array<string, array<Node\Stmt>>  $trees
     * @param  array<string, array<string, string>>  $constants
     * @return list<string>|null
     */
    private function loopedMessages(string $name, ClassMethod $method, string $path, array $trees, array $constants, NodeFinder $finder): ?array
    {
        foreach ($this->find($finder, $method->stmts ?? [], Node\Stmt\Foreach_::class) as $loop) {
            $position = null;

            if ($loop->valueVar instanceof Expr\Variable && $loop->valueVar->name === $name) {
                $position = -1;
            } elseif ($loop->valueVar instanceof Expr\List_) {
                foreach ($loop->valueVar->items as $index => $item) {
                    if ($item?->value instanceof Expr\Variable && $item->value->name === $name) {
                        $position = $index;
                    }
                }
            }

            if ($position === null) {
                continue;
            }

            $source = $loop->expr instanceof Expr\BinaryOp\Coalesce ? $loop->expr->left : $loop->expr;

            if ($this->readsExtensions($source)) {
                return [];
            }

            if ($source instanceof Expr\Variable) {
                $list = $source->name;

                foreach ($this->find($finder, $method->stmts ?? [], Expr\Assign::class) as $assign) {
                    if ($assign->var instanceof Expr\Variable && $assign->var->name === $list) {
                        $source = $assign->expr;
                    }
                }
            }

            if (! $source instanceof Expr\Array_) {
                return null;
            }

            $messages = [];

            foreach ($source->items as $item) {
                $value = $position === -1 ? $item->value : ($item->value instanceof Expr\Array_ ? ($item->value->items[$position]->value ?? null) : null);
                $found = $this->messagesOf($value, $method, $path, $trees, $constants, $finder, self::MAX_DEPTH);

                if ($found === null) {
                    return null;
                }

                $messages = [...$messages, ...$found];
            }

            return $messages;
        }

        return null;
    }

    /**
     * The messages of an `extensions` argument: those of its `errors` member, at any depth. No `errors` member: none.
     *
     * @param  array<string, array<Node\Stmt>>  $trees
     * @param  array<string, array<string, string>>  $constants
     * @return list<string>|null
     */
    private function errorMessagesOf(?Expr $extensions, ?ClassMethod $method, string $path, array $trees, array $constants, NodeFinder $finder): ?array
    {
        if (! $extensions instanceof Expr\Array_) {
            return $extensions === null || $extensions instanceof Expr\Variable ? [] : null;
        }

        foreach ($extensions->items as $item) {
            if ($item->key instanceof String_ && $item->key->value === 'errors') {
                return $this->listMessages($item->value, $method, $path, $trees, $constants, $finder);
            }
        }

        return [];
    }

    /**
     * The messages of an `errors` value: an array of arrays of messages, or a variable filled in the method (`$errors[$field][] = '...'`).
     *
     * @param  array<string, array<Node\Stmt>>  $trees
     * @param  array<string, array<string, string>>  $constants
     * @return list<string>|null
     */
    private function listMessages(Expr $value, ?ClassMethod $method, string $path, array $trees, array $constants, NodeFinder $finder, int $depth = 0): ?array
    {
        if ($value instanceof Expr\Array_) {
            $messages = [];

            foreach ($value->items as $item) {
                $found = $item->value instanceof Expr\Array_ || $item->value instanceof Expr\Variable && $this->isList($item->value, $method, $finder)
                    ? $this->listMessages($item->value, $method, $path, $trees, $constants, $finder, $depth)
                    : $this->messagesOf($item->value, $method, $path, $trees, $constants, $finder, $depth);

                if ($found === null) {
                    return null;
                }

                $messages = [...$messages, ...$found];
            }

            return $messages;
        }

        if ($value instanceof Expr\Variable && is_string($value->name) && $method !== null) {
            $key = $path.':'.$method->getStartLine().':'.$value->name;

            if (isset($this->visiting[$key])) {
                return [];
            }

            $this->visiting[$key] = true;
            $messages = $this->assignedListMessages($value->name, $method, $path, $trees, $constants, $finder, $depth);
            unset($this->visiting[$key]);

            return $messages;
        }

        // A list chosen by a `match` or a ternary: the messages of every branch.
        $branches = match (true) {
            $value instanceof Expr\Match_ => array_map(static fn (Node\MatchArm $arm): Expr => $arm->body, $value->arms),
            $value instanceof Expr\Ternary => [$value->if ?? $value->cond, $value->else],
            default => null,
        };

        if ($branches !== null) {
            $messages = [];

            foreach ($branches as $branch) {
                $found = $branch instanceof Expr\Throw_ ? [] : $this->listMessages($branch, $method, $path, $trees, $constants, $finder, $depth);

                if ($found === null) {
                    return null;
                }

                $messages = [...$messages, ...$found];
            }

            return $messages;
        }

        return $this->messagesOf($value, $method, $path, $trees, $constants, $finder, $depth);
    }

    /**
     * The messages written into a list variable in the method.
     *
     * @param  array<string, array<Node\Stmt>>  $trees
     * @param  array<string, array<string, string>>  $constants
     * @return list<string>|null
     */
    private function assignedListMessages(string $name, ClassMethod $method, string $path, array $trees, array $constants, NodeFinder $finder, int $depth): ?array
    {
        $messages = [];

        foreach ($this->find($finder, $method->stmts ?? [], Expr\Assign::class) as $assign) {
            $root = $assign->var;

            while ($root instanceof Expr\ArrayDimFetch) {
                $root = $root->var;
            }

            if (! $root instanceof Expr\Variable || $root->name !== $name) {
                continue;
            }

            $found = $assign->var === $root || $assign->expr instanceof Expr\Array_
                ? $this->listMessages($assign->expr, $method, $path, $trees, $constants, $finder, $depth)
                : $this->messagesOf($assign->expr, $method, $path, $trees, $constants, $finder, $depth);

            if ($found === null) {
                return null;
            }

            $messages = [...$messages, ...$found];
        }

        // A parameter (the caller's errors so far) adds nothing here: its messages are read where they are written.
        return $messages;
    }

    /** Whether the variable is given an array in the method, so it holds messages rather than being one. */
    private function isList(Expr\Variable $variable, ?ClassMethod $method, NodeFinder $finder): bool
    {
        foreach ($this->find($finder, $method->stmts ?? [], Expr\Assign::class) as $assign) {
            if ($assign->var instanceof Expr\Variable && $assign->var->name === $variable->name) {
                return $assign->expr instanceof Expr\Array_;
            }
        }

        return false;
    }

    /**
     * The messages a local variable is given in the method.
     *
     * @param  array<string, array<Node\Stmt>>  $trees
     * @param  array<string, array<string, string>>  $constants
     * @return list<string>|null
     */
    private function assignedMessages(string $name, ClassMethod $method, string $path, array $trees, array $constants, NodeFinder $finder, int $depth): ?array
    {
        $key = $path.':'.$method->getStartLine().':'.$name;

        if (isset($this->visiting[$key])) {
            return [];
        }

        $this->visiting[$key] = true;

        try {
            return $this->readAssigned($name, $method, $path, $trees, $constants, $finder, $depth);
        } finally {
            unset($this->visiting[$key]);
        }
    }

    /**
     * @param  array<string, array<Node\Stmt>>  $trees
     * @param  array<string, array<string, string>>  $constants
     * @return list<string>|null
     */
    private function readAssigned(string $name, ClassMethod $method, string $path, array $trees, array $constants, NodeFinder $finder, int $depth): ?array
    {
        $messages = [];
        $assigned = false;

        foreach ($this->find($finder, $method->stmts ?? [], Expr\Assign::class) as $assign) {
            if ($assign->var instanceof Expr\Variable && $assign->var->name === $name) {
                $assigned = true;
                $found = $this->messagesOf($assign->expr, $method, $path, $trees, $constants, $finder, $depth);

                if ($found === null) {
                    return null;
                }

                $messages = [...$messages, ...$found];
            }
        }

        return $assigned ? $messages : $this->loopedMessages($name, $method, $path, $trees, $constants, $finder);
    }

    /**
     * An interpolated message whose variables are parameters of the method (`"{$entity} not found"`), filled in with the literal arguments of its calls.
     *
     * @param  array<string, array<Node\Stmt>>  $trees
     * @param  array<string, array<string, string>>  $constants
     * @return list<string>|null
     */
    private function interpolated(Node\Scalar\InterpolatedString $value, ClassMethod $method, string $path, array $trees, array $constants, NodeFinder $finder, int $depth): ?array
    {
        $indexes = [];

        foreach ($value->parts as $part) {
            if ($part instanceof Node\InterpolatedStringPart) {
                continue;
            }

            $index = $part instanceof Expr\Variable && is_string($part->name) ? $this->parameterIndex($method, $part->name) : null;

            if ($index === null) {
                return null;
            }

            $indexes[$part->name] = $index;
        }

        $messages = [];

        foreach ($this->callArguments($method, $path, $trees, $finder) as $arguments) {
            $text = '';

            foreach ($value->parts as $part) {
                if ($part instanceof Node\InterpolatedStringPart) {
                    $text .= $part->value;

                    continue;
                }

                $argument = $this->literal($arguments[$indexes[$part->name]] ?? null, $constants);

                if ($argument === null) {
                    return null;
                }

                $text .= $argument;
            }

            $messages[] = $text;
        }

        return $messages;
    }

    /**
     * The messages a parameter of the method receives from its calls.
     *
     * @param  array<string, array<Node\Stmt>>  $trees
     * @param  array<string, array<string, string>>  $constants
     * @param  callable(list<?Expr>): ?Expr  $pick
     * @return list<string>|null
     */
    private function fromCalls(ClassMethod $method, string $path, array $trees, array $constants, NodeFinder $finder, int $depth, callable $pick): ?array
    {
        if ($depth >= self::MAX_DEPTH) {
            return null;
        }

        $messages = [];

        foreach ($this->callArguments($method, $path, $trees, $finder, withSite: true) as [$arguments, $site, $sitePath]) {
            $argument = $pick($arguments);

            if ($argument === null) {
                continue;
            }

            $found = $this->messagesOf($argument, $site, $sitePath, $trees, $constants, $finder, $depth + 1);

            if ($found === null) {
                return null;
            }

            $messages = [...$messages, ...$found];
        }

        return $messages;
    }

    /**
     * The positional arguments of every call of the method: method and static calls by name in its own file, and static calls through its class name in
     * any file.
     *
     * @param  array<string, array<Node\Stmt>>  $trees
     * @return list<mixed>
     */
    private function callArguments(ClassMethod $method, string $path, array $trees, NodeFinder $finder, bool $withSite = false): array
    {
        $name = $method->name->toString();
        $class = $this->classNameAt($trees[$path], $method, $finder);
        $found = [];

        foreach ($trees as $treePath => $tree) {
            $calls = $treePath === $path
                ? [...$this->find($finder, $tree, Expr\MethodCall::class), ...$this->find($finder, $tree, Expr\StaticCall::class)]
                : $this->find($finder, $tree, Expr\StaticCall::class);

            foreach ($calls as $call) {
                if (! $call->name instanceof Node\Identifier || $call->name->toString() !== $name) {
                    continue;
                }

                if ($treePath !== $path && (! $call->class instanceof Node\Name || $call->class->getLast() !== $class)) {
                    continue;
                }

                $arguments = array_map(static fn (mixed $arg): ?Expr => $arg instanceof Node\Arg ? $arg->value : null, $call->args);
                $found[] = $withSite ? [$arguments, $this->enclosingMethod($tree, $call, $finder), $treePath] : $arguments;
            }
        }

        return $found;
    }

    /** @param  array<Node\Stmt>  $tree */
    private function classNameAt(array $tree, ClassMethod $method, NodeFinder $finder): ?string
    {
        foreach ($this->find($finder, $tree, ClassLike::class) as $class) {
            if ($class->getStartLine() <= $method->getStartLine() && $class->getEndLine() >= $method->getEndLine()) {
                return $class->name?->toString();
            }
        }

        return null;
    }

    private function parameterIndex(ClassMethod $method, string $name): ?int
    {
        foreach ($method->params as $index => $param) {
            if ($param->var instanceof Expr\Variable && $param->var->name === $name) {
                return $index;
            }
        }

        return null;
    }

    /** `new self(...)` inside one of the error classes (`EntityNotFound::of`). @return class-string|null */
    private function selfClassOf(Expr\New_ $new): ?string
    {
        if (! $new->class instanceof Node\Name || ! in_array($new->class->toString(), ['self', 'static'], true)) {
            return null;
        }

        foreach (array_keys(self::CLASSES) as $class) {
            if ($this->self === substr($class, (int) strrpos($class, '\\') + 1)) {
                return $class;
            }
        }

        return null;
    }

    /**
     * `NodeFinder::findInstanceOf`, remembered per scope (a whole file or a method body) and class.
     *
     * @template T of Node
     *
     * @param  array<Node>|Node  $nodes
     * @param  class-string<T>  $class
     * @return list<T>
     */
    private function find(NodeFinder $finder, array|Node $nodes, string $class): array
    {
        $first = is_array($nodes) ? (reset($nodes) ?: null) : $nodes;

        if ($first === null) {
            return [];
        }

        $key = spl_object_id($first).':'.(is_array($nodes) ? count($nodes) : 0).':'.$class;

        /** @var list<T> */
        return $this->found[$key] ??= array_values($finder->findInstanceOf($nodes, $class));
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
        $calls = [...$this->find($finder, $tree, Expr\MethodCall::class), ...$this->find($finder, $tree, Expr\StaticCall::class)];

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
    private function enclosingMethod(array $tree, Node $new, NodeFinder $finder): ?ClassMethod
    {
        foreach ($this->find($finder, $tree, ClassMethod::class) as $method) {
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
