<?php

namespace Src\Assessment\Domain;

/** What a recording is, read from its first bytes. The browser's claim is not trusted. */
final class VideoContent
{
    /** @return array{mime: string, extension: string}|null null when the bytes are neither WebM nor MP4 */
    public static function detect(string $firstBytes): ?array
    {
        if (str_starts_with($firstBytes, "\x1A\x45\xDF\xA3")) {
            return ['mime' => 'video/webm', 'extension' => 'webm'];
        }

        if (strlen($firstBytes) >= 12 && substr($firstBytes, 4, 4) === 'ftyp') {
            return ['mime' => 'video/mp4', 'extension' => 'mp4'];
        }

        return null;
    }
}
