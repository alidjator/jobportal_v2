<?php

namespace Src\Shared\Interface\Http;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use JsonSerializable;
use Symfony\Component\HttpFoundation\Response;

/**
 * Format respons API. Kompatibel dengan aplikasi lama (F9Web ApiResponseHelpers):
 * sukses `{data: ...}`, error `{error: "..."}`, validasi `{message, errors}` (422).
 */
final class ApiResponse
{
    public static function success(mixed $data = null): JsonResponse
    {
        return response()->json(['data' => self::normalize($data)]);
    }

    public static function raw(array $body, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json($body, $status);
    }

    public static function created(mixed $data = null): JsonResponse
    {
        return response()->json(['data' => self::normalize($data)], Response::HTTP_CREATED);
    }

    public static function message(string $message, bool $status = true): JsonResponse
    {
        return self::success(['message' => $message, 'status' => $status]);
    }

    public static function error(?string $message = null, int $status = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        return response()->json(['error' => $message ?? 'Error'], $status);
    }

    public static function unauthenticated(?string $message = null): JsonResponse
    {
        return self::error($message ?? 'Unauthenticated', Response::HTTP_UNAUTHORIZED);
    }

    public static function forbidden(?string $message = null): JsonResponse
    {
        return self::error($message ?? 'Forbidden', Response::HTTP_FORBIDDEN);
    }

    public static function notFound(?string $message = null): JsonResponse
    {
        return self::error($message ?? 'Not Found', Response::HTTP_NOT_FOUND);
    }

    private static function normalize(mixed $data): mixed
    {
        if ($data instanceof Arrayable) {
            return $data->toArray();
        }

        if ($data instanceof JsonSerializable) {
            return $data->jsonSerialize();
        }

        return $data;
    }
}
