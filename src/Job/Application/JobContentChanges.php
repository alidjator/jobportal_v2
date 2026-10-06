<?php

namespace Src\Job\Application;

use Src\Job\Domain\JobContent;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * Applies the changes a client sent to the content of a job and checks the result. `$changes` is keyed by content property name; a `tags` entry holds
 * the raw text and is normalised here. A key that is not a property is a programming error.
 */
final readonly class JobContentChanges
{
    public function __construct(private JobContentRules $rules) {}

    /**
     * @param  array<string, mixed>  $changes
     * @param  bool  $isNew  true for a new job, where everything counts as changed
     *
     * @throws BusinessRuleViolation `invalid-job` with `errors` per field
     */
    public function apply(JobContent $base, array $changes, bool $isNew): JobContent
    {
        $errors = [];

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
}
