<?php

namespace Src\Content\Interface\Http\Requests;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Content\Application\Announcements\AnnouncementInput;
use Src\Content\Domain\AnnouncementAudience;
use Src\Content\Domain\AnnouncementLevel;

/** The body of an announcement; the 90-day cap on its window is a business rule (422 `invalid-announcement`). */
final class AnnouncementRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:500'],
            'link_url' => ['nullable', 'string', 'max:500', 'url:https'],
            'audience' => ['required', 'string', Rule::enum(AnnouncementAudience::class)],
            'level' => ['required', 'string', Rule::enum(AnnouncementLevel::class)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ];
    }

    public function announcement(): AnnouncementInput
    {
        return new AnnouncementInput(
            trim($this->string('title')->toString()),
            trim($this->string('body')->toString()),
            $this->filled('link_url') ? trim($this->string('link_url')->toString()) : null,
            AnnouncementAudience::from($this->string('audience')->toString()),
            AnnouncementLevel::from($this->string('level')->toString()),
            $this->time('starts_at'),
            $this->time('ends_at'),
        );
    }

    private function time(string $key): DateTimeImmutable
    {
        return (new DateTimeImmutable($this->string($key)->toString()))->setTimezone(new DateTimeZone('UTC'));
    }
}
