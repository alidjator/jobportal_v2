<?php

namespace Src\Shared\Interface\Http\Problem;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\AuthenticationFailed;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\DomainException;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Turns every exception raised while serving an API request into an RFC 9457 problem details response
 * (`application/problem+json`). It is the single place that maps domain errors to HTTP statuses.
 */
final class ProblemDetailsRenderer
{
    public const string TYPE_PREFIX = 'urn:onesis:error:';

    public const string CONTENT_TYPE = 'application/problem+json';

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
                'The request data is invalid.',
                ['errors' => $e->errors()],
            );
        }

        if ($e instanceof TooManyAttempts) {
            return $this->problem(
                Response::HTTP_TOO_MANY_REQUESTS,
                $e->errorCode(),
                $e->getMessage(),
                headers: ['Retry-After' => (string) $e->retryAfterSeconds],
            );
        }

        if ($e instanceof DomainException) {
            return $this->problem($this->statusOf($e), $e->errorCode(), $e->getMessage(), $e->extensions());
        }

        if ($e instanceof AuthenticationException) {
            return $this->problem(Response::HTTP_UNAUTHORIZED, 'unauthenticated', 'Authentication is required.');
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();

            return $this->problem($status, $this->codeOfHttpStatus($status), $this->detailOfHttpStatus($status), headers: $e->getHeaders());
        }

        return $this->problem(Response::HTTP_INTERNAL_SERVER_ERROR, 'internal-error', 'An unexpected error occurred.');
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
            'title' => Response::$statusTexts[$status] ?? 'Error',
            'status' => $status,
            'detail' => $detail,
            ...$extensions,
        ];

        return new JsonResponse($body, $status, [...$headers, 'Content-Type' => self::CONTENT_TYPE]);
    }
}
