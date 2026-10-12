<?php

namespace Src\Recruitment\Infrastructure\Video;

use Src\Recruitment\Application\Video\RecordingVolume;

/**
 * The shared volume as a local directory. `$videoDirectory` is where the video server sees the volume and `$localDirectory` where this application sees it
 * (the same volume, mounted at two places). Only names the converter writes (`ready/{id}.webm` or `.mp4`) can be opened, so a name from a webhook can never
 * point outside the volume.
 */
final readonly class LocalRecordingVolume implements RecordingVolume
{
    private const string RAW = 'raw';

    private const string READY = 'ready';

    public function __construct(private ?string $videoDirectory, private ?string $localDirectory) {}

    public function rawDirectory(int $recordingId): string
    {
        return rtrim((string) $this->videoDirectory, '/').'/'.self::RAW.'/'.$recordingId;
    }

    public function open(string $file)
    {
        if ($this->localDirectory === null || preg_match(self::FILE_PATTERN, $file) !== 1) {
            return null;
        }

        $path = rtrim($this->localDirectory, '/').'/'.$file;
        $stream = is_file($path) ? @fopen($path, 'rb') : false;

        return $stream === false ? null : $stream;
    }

    public function discard(int $recordingId): void
    {
        if ($this->localDirectory === null) {
            return;
        }

        $base = rtrim($this->localDirectory, '/');

        foreach (['webm', 'mp4'] as $extension) {
            $converted = "{$base}/".self::READY."/{$recordingId}.{$extension}";

            if (is_file($converted)) {
                unlink($converted);
            }
        }

        $raw = "{$base}/".self::RAW."/{$recordingId}";

        foreach (glob($raw.'/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($raw)) {
            rmdir($raw);
        }
    }
}
