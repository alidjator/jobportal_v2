<?php

namespace Src\JobSeeker\Domain;

/** The three lists of a profile that are always replaced as a whole: skills, languages and links. */
interface ProfileListRepository
{
    /** @return list<int> the skill ids in their order */
    public function skillIds(int $userId): array;

    /** @param  list<int>  $skillIds */
    public function replaceSkills(int $userId, array $skillIds): void;

    /** @return list<LanguageEntry> */
    public function languages(int $userId): array;

    /** @param  list<LanguageEntry>  $languages */
    public function replaceLanguages(int $userId, array $languages): void;

    /** @return list<ProfileLink> */
    public function links(int $userId): array;

    /** @param  list<ProfileLink>  $links */
    public function replaceLinks(int $userId, array $links): void;
}
