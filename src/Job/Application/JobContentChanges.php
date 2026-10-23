<?php

namespace Src\Job\Application;

use Src\Job\Domain\JobContent;
use Src\Job\Domain\JobWorkPlace;
use Src\Shared\Application\Company\CandidateMessageRules;
use Src\Shared\Application\Company\CompanyLocations;
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
    ) {}

    /**
     * @param  array<string, mixed>  $changes
     * @param  bool  $isNew  true for a new job, where everything counts as changed
     *
     * @throws BusinessRuleViolation `invalid-job` with `errors` per field, `invalid-summary`, `invalid-salary-note` or `message-link-not-allowed`
     */
    public function apply(JobContent $base, array $changes, bool $isNew, int $companyId): JobContent
    {
        $errors = [];

        $changes = $this->texts($base, $changes, $companyId);

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
