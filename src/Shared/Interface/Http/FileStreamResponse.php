<?php

namespace Src\Shared\Interface\Http;

use Illuminate\Http\Request;
use Src\Shared\Application\StreamedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Streams a stored video the way a video player asks for it: the whole file, or one byte range of it (so a player can seek). It is never cached and never a download. */
final class FileStreamResponse
{
    private const int CHUNK_BYTES = 262_144;

    public static function make(Request $request, StreamedFile $file): Response
    {
        $headers = [
            'Content-Type' => $file->mimeType,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'no-store, private',
            'Content-Disposition' => 'inline; filename="'.$file->name.'"',
            'X-Content-Type-Options' => 'nosniff',
        ];

        $range = self::range((string) $request->header('Range'), $file->size);

        if ($range === false) {
            fclose($file->stream);

            return new Response('', Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE, ['Content-Range' => 'bytes */'.$file->size] + $headers);
        }

        [$start, $end] = $range ?? [0, $file->size - 1];
        $partial = $range !== null;
        $headers['Content-Length'] = (string) ($end - $start + 1);

        if ($partial) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$file->size}";
        }

        return new StreamedResponse(function () use ($file, $start, $end): void {
            try {
                self::skip($file->stream, $start);

                for ($left = $end - $start + 1; $left > 0 && ! feof($file->stream); $left -= strlen($chunk)) {
                    $chunk = (string) fread($file->stream, min(self::CHUNK_BYTES, $left));

                    if ($chunk === '') {
                        break;
                    }

                    echo $chunk;
                    flush();
                }
            } finally {
                fclose($file->stream);
            }
        }, $partial ? Response::HTTP_PARTIAL_CONTENT : Response::HTTP_OK, $headers);
    }

    /**
     * The first and last byte asked for; null when the whole file is wanted (no header, or one this does not understand); false when the range lies outside the file.
     *
     * @return array{int, int}|false|null
     */
    private static function range(string $header, int $size): array|false|null
    {
        if (preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $match) !== 1 || ($match[1] === '' && $match[2] === '')) {
            return null;
        }

        if ($match[1] === '') {
            $length = (int) $match[2];

            return $length === 0 ? false : [max(0, $size - $length), $size - 1];
        }

        $start = (int) $match[1];
        $end = $match[2] === '' ? $size - 1 : min((int) $match[2], $size - 1);

        return $start > $end || $start >= $size ? false : [$start, $end];
    }

    /** @param  resource  $stream */
    private static function skip($stream, int $bytes): void
    {
        if ($bytes === 0 || fseek($stream, $bytes) === 0) {
            return;
        }

        // A stream that cannot seek is read and thrown away up to the start.
        for ($left = $bytes; $left > 0 && ! feof($stream); $left -= strlen($chunk)) {
            $chunk = (string) fread($stream, min(self::CHUNK_BYTES, $left));
        }
    }
}
