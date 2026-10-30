<?php

namespace Src\Job\Application;

use Src\Job\Domain\JobContent;
use Src\Job\Domain\JobWorkPlace;
use Src\Shared\Application\Company\CandidateMessageRules;
use Src\Shared\Application\Company\CompanyLocations;
use Src\Shared\Application\Job\ApplyRequirement;
use Src\Shared\Application\References\ReferenceValues;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * Applies the changes a client sent to the content of a job and checks the result. `$changes` is keyed by content property name; a `tags` entry holds
 * the raw text and is normalised here; a `locationIds` entry (company locations, the main one first) is replaced by the places copied from them, and the first place fills the
 * job's own address. A key that is not a property is a programming error.
 */
final readonly class JobContentChanges
{
    private const int SUMMARY_MIN = 20;

    private const int SUMMARY_MAX = 150;

    private const int SALARY_NOTE_MAX = 150;

    /** The content properties of the job's own address; they cannot be sent together with company locations. */
    private const array ADDRESS_PROPERTIES = ['provinceId', 'cityId', 'subdistrictId', 'villageId', 'postalCodeId', 'addressLine'];

    public function __construct(
        private JobContentRules $rules,
        private CompanyLocations $locations,
        private CandidateMessageRules $messageRules,
        private ReferenceValues $references,
    ) {}

    /**
     * @param  array<string, mixed>  $changes
     * @param  bool  $isNew  true for a new job, where everything counts as changed
     *
     * @throws BusinessRuleViolation `invalid-job` with `errors` per field, `invalid-summary`, `invalid-salary-note`, `invalid-apply-requirements` or `message-link-not-allowed`
     */
    public function apply(JobContent $base, array $changes, bool $isNew, int $companyId): JobContent
    {
        $errors = [];

        $changes = $this->texts($base, $changes, $companyId);
        $this->assertRequirements($base, $changes);

        if (array_key_exists('locationIds', $changes)) {
            /** @var list<int> $ids */
            $ids = $changes['locationIds'];
            unset($changes['locationIds']);
            $changes = [...$changes, ...$this->places($base, $changes, $ids, $companyId, $errors)];
        }

        if (array_key_exists('tags', $changes)) {
            /** @var list<string> $raw */
            $raw = $changes['tags'];
            [$tags, $problems] = JobTags::normalize($raw);
            $changes['tags'] = $tags;

            if ($problems !== []) {
                $errors['tags'] = $problems;
            }
        }

        $content = $base->with($changes);
        $this->rules->check($content, $isNew ? null : $base, $errors);

        return $content;
    }

    /**
     * The extra profile items must be known, each at most once (a language once per language), at most 3 languages, each with an active language and a known minimum proficiency.
     * A language the job already asks for stays valid when an admin deactivated it later.
     *
     * @param  array<string, mixed>  $changes
     *
     * @throws BusinessRuleViolation `invalid-apply-requirements`
     */
    private function assertRequirements(JobContent $base, array $changes): void
    {
        if (! array_key_exists('applyRequirements', $changes)) {
            return;
        }

        /** @var list<ApplyRequirement> $requirements */
        $requirements = $changes['applyRequirements'];
        $held = array_map(static fn (ApplyRequirement $requirement): string => $requirement->code(), $base->applyRequirements);
        $seen = [];
        $languages = 0;

        foreach ($requirements as $requirement) {
            $language = $requirement->item === ApplyRequirement::LANGUAGE;

            if (! in_array($requirement->item, ApplyRequirement::ITEMS, true)) {
                $message = 'The item is not one a job can ask for.';

                throw new BusinessRuleViolation($message, 'invalid-apply-requirements', extensions: ['errors' => ['apply_requirements' => [$message]]]);
            }

            if ($language && ($requirement->languageId === null || ! in_array($requirement->minProficiency, ApplyRequirement::PROFICIENCIES, true))) {
                $message = 'A language needs its id and a minimum proficiency.';

                throw new BusinessRuleViolation($message, 'invalid-apply-requirements', extensions: ['errors' => ['apply_requirements' => [$message]]]);
            }

            if (in_array($requirement->code(), $seen, true)) {
                $message = 'An item can be asked for only once.';

                throw new BusinessRuleViolation($message, 'invalid-apply-requirements', extensions: ['errors' => ['apply_requirements' => [$message]]]);
            }

            if ($language && ++$languages > ApplyRequirement::MAX_LANGUAGES) {
                $message = 'A job can ask for at most :max languages.';

                throw new BusinessRuleViolation($message, 'invalid-apply-requirements', extensions: ['errors' => ['apply_requirements' => [$message]]], parameters: ['max' => ApplyRequirement::MAX_LANGUAGES]);
            }

            if ($language && ! in_array($requirement->code(), $held, true) && ! $this->references->isActive('languages', (int) $requirement->languageId)) {
                $message = 'The language does not exist or is not active.';

                throw new BusinessRuleViolation($message, 'invalid-apply-requirements', extensions: ['errors' => ['apply_requirements' => [$message]]]);
            }

            $seen[] = $requirement->code();
        }
    }

    /**
     * Trims the summary and the salary note and checks the ones that change: a summary of 20 to 150 characters and a salary note of at most 150, plain text on one line, no link.
     *
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     *
     * @throws BusinessRuleViolation `invalid-summary`, `invalid-salary-note` or `message-link-not-allowed`
     */
    private function texts(JobContent $base, array $changes, int $companyId): array
    {
        foreach (['summary' => ['invalid-summary', 'summary'], 'salaryNote' => ['invalid-salary-note', 'salary_note']] as $property => [$code, $field]) {
            if (! array_key_exists($property, $changes)) {
                continue;
            }

            $text = $changes[$property] === null ? null : trim((string) $changes[$property]);
            $text = $text === '' ? null : $text;
            $changes[$property] = $text;

            if ($text === null || $text === $base->{$property}) {
                continue;
            }

            $length = mb_strlen($text);
            $min = $property === 'summary' ? self::SUMMARY_MIN : 1;
            $max = $property === 'summary' ? self::SUMMARY_MAX : self::SALARY_NOTE_MAX;

            if ($length < $min || $length > $max || preg_match('/\\R/u', $text) === 1) {
                $message = $property === 'summary' ? 'The summary must be one line of :min to :max characters.' : 'The salary note must be one line of at most :max characters.';

                throw new BusinessRuleViolation($message, $code, extensions: ['errors' => [$field => [$message]]], parameters: ['min' => $min, 'max' => $max]);
            }

            $this->messageRules->assertAllowed($companyId, $text, $field);
        }

        return $changes;
    }

    /**
     * The places for the company locations the client chose, and the job's own address taken from the first one. A place the job already holds stays when its location is no longer
     * active (an unrelated edit must not fail), a new one must be an active location of the company.
     *
     * @param  array<string, mixed>  $changes
     * @param  list<int>  $ids
     * @param  array<string, list<string>>  $errors
     * @return array<string, mixed>
     */
    private function places(JobContent $base, array $changes, array $ids, int $companyId, array &$errors): array
    {
        if ($ids === []) {
            return ['workPlaces' => []];
        }

        if (array_intersect(self::ADDRESS_PROPERTIES, array_keys($changes)) !== []) {
            $errors['location_ids'][] = 'The locations cannot be combined with an address of the job itself.';

            return [];
        }

        $held = [];

        foreach ($base->workPlaces as $place) {
            $held[$place->locationId] = $place;
        }

        $places = [];

        foreach ($ids as $id) {
            $current = $this->locations->activeWorkPlace($companyId, $id);

            if ($current !== null) {
                $places[] = new JobWorkPlace($current->id, $current->name, $current->type, $current->provinceId, $current->cityId, $current->subdistrictId, $current->villageId, $current->postalCodeId, $current->addressLine);
            } elseif (isset($held[$id])) {
                $places[] = $held[$id];
            } else {
                $errors['location_ids'][] = 'A chosen location does not exist or is not active.';
            }
        }

        if ($errors !== [] || $places === []) {
            return [];
        }

        $main = $places[0];

        return [
            'workPlaces' => $places,
            'provinceId' => $main->provinceId,
            'cityId' => $main->cityId,
            'subdistrictId' => $main->subdistrictId,
            'villageId' => $main->villageId,
            'postalCodeId' => $main->postalCodeId,
            'addressLine' => $main->addressLine,
        ];
    }
}
