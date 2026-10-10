<?php

namespace Src\Training\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Log;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Training\Application\TrainingCatalog;
use Src\Training\Domain\Course;
use Src\Training\Domain\EnrolledCourse;

/**
 * Reads the Moodle web service. Every call is a POST with the token in the form body, never in the URL (the legacy site put it in the query string, where it
 * ends up in access logs). Moodle answers 200 with an `exception` payload for most errors, so the body is checked too.
 */
final readonly class MoodleTrainingCatalog implements TrainingCatalog
{
    /** The front-page "course" every Moodle site has. */
    private const int SITE_COURSE_ID = 1;

    private const int TIMEOUT_SECONDS = 5;

    public function __construct(
        private Factory $http,
        private ?string $baseUrl,
        private ?string $token,
        private ?string $courseUrlTemplate,
    ) {}

    public function courses(): array
    {
        $payload = $this->call('core_course_get_courses_by_field');
        $rows = is_array($payload['courses'] ?? null) ? $payload['courses'] : [];
        $courses = [];

        foreach ($rows as $row) {
            if (is_array($row) && is_int($row['id'] ?? null) && $row['id'] !== self::SITE_COURSE_ID) {
                $courses[] = $this->course($row);
            }
        }

        return $courses;
    }

    public function enrollmentsOf(string $email): array
    {
        $users = $this->call('core_user_get_users_by_field', ['field' => 'email', 'values' => [$email]]);
        $user = $users[0] ?? null;

        if (! is_array($user) || ! is_int($user['id'] ?? null)) {
            return [];
        }

        $enrolled = [];

        foreach ($this->call('core_enrol_get_users_courses', ['userid' => $user['id']]) as $row) {
            if (is_array($row) && is_int($row['id'] ?? null) && $row['id'] !== self::SITE_COURSE_ID) {
                $progress = $row['progress'] ?? null;
                $enrolled[] = new EnrolledCourse($this->course($row), is_numeric($progress) ? round((float) $progress, 1) : null, $this->moment($row['lastaccess'] ?? null));
            }
        }

        return $enrolled;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function course(array $row): Course
    {
        $id = (int) $row['id'];
        $image = $row['courseimage'] ?? null;

        return new Course(
            $id,
            trim((string) ($row['fullname'] ?? '')),
            $this->plainText((string) ($row['summary'] ?? '')),
            is_string($image) && $image !== '' ? $image : null,
            $this->moment($row['startdate'] ?? null),
            $this->moment($row['enddate'] ?? null),
            str_replace('{id}', (string) $id, (string) $this->courseUrlTemplate),
        );
    }

    /** Moodle sends Unix seconds, and 0 for "not set". */
    private function moment(mixed $seconds): ?DateTimeImmutable
    {
        return is_numeric($seconds) && (int) $seconds > 0 ? (new DateTimeImmutable('@'.(int) $seconds))->setTimezone(new DateTimeZone('UTC')) : null;
    }

    private function plainText(string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<mixed>
     *
     * @throws ExternalServiceUnavailable
     */
    private function call(string $function, array $parameters = []): array
    {
        if (blank($this->baseUrl) || blank($this->token) || blank($this->courseUrlTemplate)) {
            throw new ExternalServiceUnavailable('The training platform is not configured.', self::UNAVAILABLE);
        }

        try {
            $response = $this->http->asForm()->timeout(self::TIMEOUT_SECONDS)->retry(1, 200, throw: false)->post($this->baseUrl, [
                'wstoken' => $this->token,
                'wsfunction' => $function,
                'moodlewsrestformat' => 'json',
                ...$parameters,
            ]);
        } catch (ConnectionException $e) {
            Log::warning('Training platform unreachable.', ['function' => $function, 'reason' => $e->getMessage()]);

            throw new ExternalServiceUnavailable('The training platform is unavailable.', self::UNAVAILABLE, $e);
        }

        $body = $response->json();

        if ($response->failed() || ! is_array($body) || isset($body['exception'])) {
            Log::warning('Training platform refused a call.', ['function' => $function, 'status' => $response->status(), 'error' => is_array($body) ? ($body['errorcode'] ?? $body['exception'] ?? null) : null]);

            throw new ExternalServiceUnavailable('The training platform is unavailable.', self::UNAVAILABLE);
        }

        return $body;
    }
}
