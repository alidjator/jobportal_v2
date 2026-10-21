<?php

namespace Src\Shared\Interface\Http\Problem;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\AuthenticationFailed;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\DomainException;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\ErrorMessage;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Turns every exception raised while serving an API request into an RFC 9457 problem details response
 * (`application/problem+json`). It is the single place that maps domain errors to HTTP statuses.
 *
 * `title`, `detail` and the messages of a domain error's `errors` member are translated into the request language with the JSON translations
 * (`lang/id.json`), the English text being the key; a text without a translation stays English. Validation messages come translated from the validator
 * (`lang/id/validation.php`); they pass the same lookup, so a message a middleware writes itself (`ValidationException::withMessages`) is translated too.
 */
final class ProblemDetailsRenderer
{
    public const string TYPE_PREFIX = 'urn:onesis:error:';

    public const string CONTENT_TYPE = 'application/problem+json';

    /** @param  ?Closure(): Translator  $translator  resolved when a problem is rendered (the renderer is built before the container is ready); null keeps English */
    public function __construct(private readonly ?Closure $translator = null) {}

    /** Returns null for requests that are not API requests, so the framework keeps its default rendering. */
    public function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        if ($e instanceof ValidationException) {
            return $this->problem(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'validation-failed',
                $this->translate('The request data is invalid.'),
                ['errors' => array_map(fn (array $messages): array => array_map(fn (string $message): string => $this->translate($message), $messages), $e->errors())],
            );
        }

        if ($e instanceof TooManyAttempts) {
            return $this->problem(
                Response::HTTP_TOO_MANY_REQUESTS,
                $e->errorCode(),
                $this->translate($e->template(), $e->parameters()),
                headers: ['Retry-After' => (string) $e->retryAfterSeconds],
            );
        }

        if ($e instanceof DomainException) {
            return $this->problem($this->statusOf($e), $e->errorCode(), $this->translate($e->template(), $e->parameters()), $this->translatedExtensions($e));
        }

        if ($e instanceof AuthenticationException) {
            return $this->problem(Response::HTTP_UNAUTHORIZED, 'unauthenticated', $this->translate('Authentication is required.'));
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();

            return $this->problem($status, $this->codeOfHttpStatus($status), $this->translate($this->detailOfHttpStatus($status)), headers: $e->getHeaders());
        }

        return $this->problem(Response::HTTP_INTERNAL_SERVER_ERROR, 'internal-error', $this->translate('An unexpected error occurred.'));
    }

    /**
     * The extensions with every message of `errors` translated; the placeholders take the parameters of the error, or those of an `ErrorMessage`.
     *
     * @return array<string, mixed>
     */
    private function translatedExtensions(DomainException $e): array
    {
        $extensions = $e->extensions();

        if (isset($extensions['errors']) && is_array($extensions['errors'])) {
            array_walk_recursive($extensions['errors'], function (mixed &$message) use ($e): void {
                if ($message instanceof ErrorMessage) {
                    $message = $this->translate($message->template, $message->parameters);
                } elseif (is_string($message)) {
                    $message = $this->translate($message, $e->parameters());
                }
            });
        }

        return $extensions;
    }

    /** @param  array<string, string|int|float>  $parameters */
    private function translate(string $text, array $parameters = []): string
    {
        if ($this->translator === null) {
            return DomainException::fill($text, $parameters);
        }

        $translated = ($this->translator)()->get($text, array_map('strval', $parameters));

        return is_string($translated) ? $translated : DomainException::fill($text, $parameters);
    }

    private function statusOf(DomainException $e): int
    {
        return match (true) {
            $e instanceof EntityNotFound => Response::HTTP_NOT_FOUND,
            $e instanceof AuthenticationFailed => Response::HTTP_UNAUTHORIZED,
            $e instanceof ExternalServiceUnavailable => Response::HTTP_SERVICE_UNAVAILABLE,
            $e instanceof AccessDenied => Response::HTTP_FORBIDDEN,
            $e instanceof StateConflict => Response::HTTP_CONFLICT,
            $e instanceof BusinessRuleViolation => Response::HTTP_UNPROCESSABLE_ENTITY,
            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };
    }

    private function codeOfHttpStatus(int $status): string
    {
        return match ($status) {
            Response::HTTP_FORBIDDEN => 'forbidden',
            Response::HTTP_NOT_FOUND => 'not-found',
            Response::HTTP_METHOD_NOT_ALLOWED => 'method-not-allowed',
            Response::HTTP_TOO_MANY_REQUESTS => 'too-many-requests',
            default => 'http-error',
        };
    }

    private function detailOfHttpStatus(int $status): string
    {
        return match ($status) {
            Response::HTTP_FORBIDDEN => 'You are not allowed to perform this action.',
            Response::HTTP_NOT_FOUND => 'The requested resource was not found.',
            Response::HTTP_METHOD_NOT_ALLOWED => 'The HTTP method is not allowed for this resource.',
            Response::HTTP_TOO_MANY_REQUESTS => 'Too many requests. Try again later.',
            default => Response::$statusTexts[$status] ?? 'The request could not be completed.',
        };
    }

    /**
     * @param  array<string, mixed>  $extensions
     * @param  array<string, string>  $headers
     */
    private function problem(int $status, string $code, string $detail, array $extensions = [], array $headers = []): JsonResponse
    {
        $body = [
            'type' => self::TYPE_PREFIX.$code,
            'title' => $this->translate(Response::$statusTexts[$status] ?? 'Error'),
            'status' => $status,
            'detail' => $detail,
            ...$extensions,
        ];

        return new JsonResponse($body, $status, [...$headers, 'Content-Type' => self::CONTENT_TYPE]);
    }
}
