<?php

namespace Src\JobSeeker\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\JobSeeker\Domain\LanguageEntry;
use Src\JobSeeker\Domain\LanguageProficiency;
use Src\JobSeeker\Domain\LinkType;
use Src\JobSeeker\Domain\ProfileLink;
use Src\JobSeeker\Domain\ProfileListRepository;
use stdClass;

final readonly class DatabaseProfileListRepository implements ProfileListRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function skillIds(int $userId): array
    {
        return array_map('intval', $this->connection->table('jobseeker_skills')->where('user_id', $userId)->orderBy('position')->orderBy('id')->pluck('skill_id')->all());
    }

    public function replaceSkills(int $userId, array $skillIds): void
    {
        $this->connection->table('jobseeker_skills')->where('user_id', $userId)->delete();

        if ($skillIds !== []) {
            $this->connection->table('jobseeker_skills')->insert(array_map(
                static fn (int $skillId, int $index): array => ['user_id' => $userId, 'skill_id' => $skillId, 'position' => $index + 1],
                $skillIds, array_keys($skillIds),
            ));
        }
    }

    public function languages(int $userId): array
    {
        return $this->connection->table('jobseeker_languages')->where('user_id', $userId)->orderBy('id')->get()
            ->map(static fn (stdClass $row): LanguageEntry => new LanguageEntry((int) $row->language_id, LanguageProficiency::from($row->proficiency)))
            ->all();
    }

    public function replaceLanguages(int $userId, array $languages): void
    {
        $this->connection->table('jobseeker_languages')->where('user_id', $userId)->delete();

        if ($languages !== []) {
            $this->connection->table('jobseeker_languages')->insert(array_map(
                static fn (LanguageEntry $entry): array => ['user_id' => $userId, 'language_id' => $entry->languageId, 'proficiency' => $entry->proficiency->value],
                $languages,
            ));
        }
    }

    public function links(int $userId): array
    {
        return $this->connection->table('jobseeker_links')->where('user_id', $userId)->orderBy('id')->get()
            ->map(static fn (stdClass $row): ProfileLink => new ProfileLink(LinkType::from($row->type), $row->url))
            ->all();
    }

    public function replaceLinks(int $userId, array $links): void
    {
        $this->connection->table('jobseeker_links')->where('user_id', $userId)->delete();

        if ($links !== []) {
            $this->connection->table('jobseeker_links')->insert(array_map(
                static fn (ProfileLink $link): array => ['user_id' => $userId, 'type' => $link->type->value, 'url' => $link->url],
                $links,
            ));
        }
    }
}
