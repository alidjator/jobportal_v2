<?php

namespace Src\Assessment\Application\Authoring;

use Psr\Log\LoggerInterface;
use Src\Assessment\Application\AssessmentSettings;
use Src\Assessment\Domain\QuestionImage;
use Src\Assessment\Domain\QuestionImageRepository;
use Src\Assessment\Domain\QuestionImageRules;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\Images\ImageProcessor;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Throwable;

/**
 * The author uploads a picture to a test that can still change. The picture is decoded and encoded again (no metadata, nothing hidden), shrunk to fit 1600 pixels, and kept on the private disk; it is not on a question
 * until a question names it, and one that no question names within a day is purged.
 */
final readonly class UploadQuestionImage
{
    private const string DIRECTORY = 'assessment/question-images';

    private const int MAX_SIDE = 1600;

    public function __construct(
        private AuthoringAccess $access,
        private QuestionImageRepository $images,
        private ImageProcessor $processor,
        private FileStorage $files,
        private Settings $settings,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     * @throws StateConflict `test-archived` or `test-locked`
     * @throws BusinessRuleViolation `invalid-image` or `file-too-large`
     */
    public function execute(Actor $actor, int $testId, string $contents, string $alt): QuestionImage
    {
        $author = $this->access->author($actor);
        $this->access->editableTest($author, $testId);

        $alt = trim($alt);

        if ($alt === '' || mb_strlen($alt) > QuestionImageRules::ALT_MAX) {
            $message = 'The text for screen readers must have between 1 and :max characters.';

            throw new BusinessRuleViolation($message, 'invalid-image', extensions: ['errors' => ['alt' => [$message]]], parameters: ['max' => QuestionImageRules::ALT_MAX]);
        }

        $maxMb = $this->settings->integer(AssessmentSettings::MAX_QUESTION_IMAGE_MEGABYTES);

        if (strlen($contents) > $maxMb * 1024 * 1024) {
            throw new BusinessRuleViolation('The picture is larger than the allowed :max MB.', 'file-too-large', extensions: ['max_size_mb' => $maxMb], parameters: ['max' => $maxMb]);
        }

        $image = $this->processor->process($contents, self::MAX_SIDE, self::MAX_SIDE);
        $path = self::DIRECTORY.'/'.$testId.'/'.bin2hex(random_bytes(16)).'.'.$image->extension;

        $this->files->put($path, $image->contents);

        try {
            return $this->images->create(new QuestionImage(null, $testId, null, null, 0, $path, $image->mimeType, strlen($image->contents), $image->width, $image->height, $alt));
        } catch (Throwable $e) {
            try {
                $this->files->delete($path);
            } catch (Throwable $inner) {
                $this->logger->warning('The file of a question picture could not be deleted.', ['path' => $path, 'exception' => $inner]);
            }

            throw $e;
        }
    }
}
