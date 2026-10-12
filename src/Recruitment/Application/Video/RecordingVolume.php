<?php

namespace Src\Recruitment\Application\Video;

/**
 * The volume the video server and this application share: the video server writes the raw tracks of a recording there, a converter turns them into one file,
 * and the application moves that file to the private disk. File names are relative to the volume.
 */
interface RecordingVolume
{
    /** The names a converter writes: `ready/{recording id}.webm` or `.mp4`. */
    public const string FILE_PATTERN = '#^ready/\d+\.(webm|mp4)$#';

    /** The directory the video server writes the raw tracks of the recording to, as the video server sees it. */
    public function rawDirectory(int $recordingId): string;

    /**
     * A readable stream of a converted file, or null when there is no such file or the name is not one a converter writes. The caller closes it.
     *
     * @return resource|null
     */
    public function open(string $file);

    /** Removes the raw tracks and the converted file of the recording; what is already gone is ignored. */
    public function discard(int $recordingId): void;
}
