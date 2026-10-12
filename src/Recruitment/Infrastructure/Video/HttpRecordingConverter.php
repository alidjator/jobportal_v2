<?php

namespace Src\Recruitment\Infrastructure\Video;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Src\Recruitment\Application\Video\RecordingConverter;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;

/** Calls the small converter service that runs next to the video server. It authenticates with the same secret as the webhooks. */
final readonly class HttpRecordingConverter implements RecordingConverter
{
    private const int TIMEOUT_SECONDS = 5;

    public function __construct(private Factory $http, private ?string $url, private ?string $secret) {}

    public function request(int $recordingId, string $directory): void
    {
        if (blank($this->url) || blank($this->secret)) {
            throw new ExternalServiceUnavailable('The recording converter is not configured.', JanusVideoRoomGateway::VIDEO_UNAVAILABLE);
        }

        try {
            $response = $this->http->withToken($this->secret)->acceptJson()->timeout(self::TIMEOUT_SECONDS)->asJson()->post($this->url, [
                'recording_id' => $recordingId,
                'directory' => $directory,
            ]);
        } catch (ConnectionException $failure) {
            throw new ExternalServiceUnavailable('The recording converter cannot be reached.', JanusVideoRoomGateway::VIDEO_UNAVAILABLE, $failure);
        }

        if (! $response->successful()) {
            throw new ExternalServiceUnavailable('The recording converter refused the request.', JanusVideoRoomGateway::VIDEO_UNAVAILABLE);
        }
    }
}
