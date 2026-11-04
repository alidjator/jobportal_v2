<?php

namespace Src\Assessment\Application\Authoring;

use Psr\Log\LoggerInterface;
use Src\Assessment\Application\AssessmentSettings;
use Src\Assessment\Domain\Question;
use Src\Assessment\Domain\QuestionImage;
use Src\Assessment\Domain\QuestionImageRepository;
use Src\Assessment\Domain\QuestionImageRules;
use Src\Assessment\Domain\Test;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Throwable;

/**
 * Puts the pictures a question names in their places when it is saved, inside the same transaction: the images must be those uploaded to the same test, not used by another question, and fit the rules of the type.
 * What the question no longer names is deleted (the row now, the file once the transaction is over: {@see self::discard()}). The question's own pictures keep the order they were named in; an option's picture is
 * named by the option's position.
 */
final readonly class QuestionImages
{
    public function __construct(
        private QuestionImageRepository $images,
        private Settings $settings,
        private FileStorage $files,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return list<string> the paths of the files to delete after the transaction (see {@see self::discard()})
     *
     * @throws BusinessRuleViolation `invalid-question`, `invalid-image` or `too-many-images`
     */
    public function place(Test $test, Question $question, QuestionDraft $draft): array
    {
        $questionId = (int) $question->id;
        $optionIds = array_values($draft->optionImageIds);
        $named = [...$draft->imageIds, ...array_filter($optionIds, static fn (?int $id): bool => $id !== null)];

        if (count($named) !== count(array_unique($named))) {
            throw $this->invalid('image_ids', 'A picture can be used only once.');
        }

        QuestionImageRules::check(
            $question->type, count($draft->imageIds), array_map(static fn (?int $id): bool => $id !== null, $optionIds), $this->settings->integer(AssessmentSettings::MAX_QUESTION_IMAGES),
        );

        $found = $this->images->manyById($named);

        foreach ($named as $id) {
            $image = $found[$id] ?? null;

            if ($image === null || $image->testId !== (int) $test->id || ($image->questionId !== null && $image->questionId !== $questionId)) {
                $message = 'The picture does not belong to this test or is used by another question.';

                throw new BusinessRuleViolation($message, 'invalid-image', extensions: ['errors' => ['image_ids' => [$message]]]);
            }
        }

        $current = $this->images->ofQuestion($questionId);
        $this->images->detachFrom($questionId);

        foreach ($draft->imageIds as $position => $id) {
            $this->images->attach($id, $questionId, null, $position);
        }

        foreach ($optionIds as $index => $id) {
            if ($id !== null) {
                $this->images->attach($id, $questionId, $index + 1, 0);
            }
        }

        $gone = array_values(array_filter($current, static fn (QuestionImage $image): bool => ! in_array((int) $image->id, $named, true)));
        $this->images->delete(array_map(static fn (QuestionImage $image): int => (int) $image->id, $gone));

        return array_map(static fn (QuestionImage $image): string => $image->path, $gone);
    }

    /** @param  list<string>  $paths */
    public function discard(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                $this->files->delete($path);
            } catch (Throwable $e) {
                $this->logger->warning('The file of a question picture could not be deleted.', ['path' => $path, 'exception' => $e]);
            }
        }
    }

    private function invalid(string $field, string $message): BusinessRuleViolation
    {
        return new BusinessRuleViolation($message, 'invalid-question', extensions: ['errors' => [$field => [$message]]]);
    }
}
