<?php

namespace Src\JobSeeker\Application;

use Src\JobSeeker\Domain\LanguageEntry;
use Src\JobSeeker\Domain\ProfileListRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\References\ReferenceValues;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** A job seeker sets their whole list of languages with a proficiency each: at most 10, one entry per language, every one an active language. */
final readonly class ReplaceMyLanguages
{
    public const int MAX = 10;

    public function __construct(
        private JobSeekerGuard $guard,
        private ProfileListRepository $lists,
        private ReferenceValues $references,
        private TransactionManager $transactions,
    ) {}

    /**
     * @param  list<LanguageEntry>  $languages
     *
     * @throws AccessDenied `job-seeker-only`
     * @throws BusinessRuleViolation `invalid-languages` with `errors` per `languages.<n>.language_id` or `languages`
     */
    public function execute(Actor $actor, array $languages): void
    {
        $userId = $this->guard->userId($actor);
        $errors = [];
        $ids = array_map(static fn (LanguageEntry $entry): int => $entry->languageId, $languages);

        if (count($languages) > self::MAX) {
            $errors['languages'][] = 'A profile can have at most '.self::MAX.' languages.';
        }

        if (count($ids) !== count(array_unique($ids))) {
            $errors['languages'][] = 'A language can be listed only once.';
        }

        foreach ($ids as $index => $languageId) {
            if (! $this->references->isActive('languages', $languageId)) {
                $errors["languages.{$index}.language_id"][] = 'This language does not exist or is not active.';
            }
        }

        if ($errors !== []) {
            throw new BusinessRuleViolation('The languages are not valid.', 'invalid-languages', extensions: ['errors' => $errors]);
        }

        $this->transactions->run(fn () => $this->lists->replaceLanguages($userId, $languages));
    }
}
